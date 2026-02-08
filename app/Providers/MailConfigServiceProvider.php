<?php

namespace App\Providers;

use App\Settings\MailSettings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessing;

class MailConfigServiceProvider extends ServiceProvider
{
    protected static $defaultConfig = null;

    public function boot(): void
    {
        if (!Schema::hasTable('settings')) return;

        if (static::$defaultConfig === null) {
            static::$defaultConfig = [
                'default' => config('mail.default'),
                'mailer'  => config('mail.mailers.smtp'),
                'from'    => config('mail.from'),
            ];
        }

        $this->syncMailConfig();

        Queue::before(function (JobProcessing $event) {
            $jobName = $event->job->resolveName();
            if ($this->isMailJob($jobName)) {
                $this->syncMailConfig();
            }
        });
    }

    protected function syncMailConfig(): void
    {
        try {
            // 1. REFRESH DEI SETTINGS (Spatie)
            $settings = app(MailSettings::class)->refresh();

            if ($settings->mail_enabled && !empty($settings->mail_host)) {
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.host', $settings->mail_host);
                Config::set('mail.mailers.smtp.port', (int) $settings->mail_port);
                Config::set('mail.mailers.smtp.username', $settings->mail_username);
                Config::set('mail.mailers.smtp.encryption', $settings->mail_encryption === 'null' ? null : $settings->mail_encryption);
                Config::set('mail.from.address', $settings->mail_from_address);
                Config::set('mail.from.name', $settings->mail_from_name);

                if ($settings->mail_password) {
                    try {
                        Config::set('mail.mailers.smtp.password', Crypt::decryptString($settings->mail_password));
                    } catch (\Exception $e) {
                        Config::set('mail.mailers.smtp.password', $settings->mail_password);
                    }
                }
            } else {
                Config::set('mail.default', static::$defaultConfig['default']);
                Config::set('mail.mailers.smtp', static::$defaultConfig['mailer']);
                Config::set('mail.from', static::$defaultConfig['from']);
            }

            // 2. RESET DEL SOLO MAIL MANAGER
            // Usiamo 'mail.manager' per resettare le istanze dei driver già aperti
            // senza toccare il resto del container di Laravel.
            if (app()->resolved('mail.manager')) {
                app('mail.manager')->forgetMailers();
            }

        } catch (\Exception $e) {
            // Se fallisce, forziamo il log driver per evitare crash
            Config::set('mail.default', 'log');
            Log::error("MailConfig Sync Error: " . $e->getMessage());
        }
    }

    protected function isMailJob(string $jobName): bool
    {
        $patterns = [
            'Illuminate\Notifications\SendQueuedNotifications',
            'Illuminate\Mail\SendQueuedMailable',
            'App\Mail\\',
            'App\Notifications\\',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($jobName, $pattern)) return true;
        }
        return false;
    }
}