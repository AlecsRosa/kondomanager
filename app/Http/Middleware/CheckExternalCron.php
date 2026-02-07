<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Settings\GeneralSettings; 
use Illuminate\Support\Facades\Cache;

class CheckExternalCron
{
    protected $settings;

    public function __construct(GeneralSettings $settings)
    {
        $this->settings = $settings;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->settings->external_cron_enabled) {
            abort(404);
        }

        if ($request->query('token') !== $this->settings->external_cron_token) {
            abort(401, 'Token di sicurezza non valido.');
        }

        // 1. Cerchiamo nella Cache (aggiornata dal comando automatico)
        // 2. Se la Cache è vuota/scaduta, usiamo la lista statica come salvagente
        $allowedIps = Cache::get('cronjob_allowed_ips', [
            '116.203.134.67', 
            '116.203.129.16', 
            '23.88.105.37', 
            '128.140.8.200', 
            '91.99.23.109'
        ]);

        // Fix per Localhost
        if (app()->environment('local')) {
            $allowedIps[] = '127.0.0.1';
            $allowedIps[] = '::1';
        }

        if (!in_array($request->ip(), $allowedIps)) {
            logger()->warning("Scheduler bloccato. IP: {$request->ip()}");
            abort(403, 'Indirizzo IP non autorizzato.');
        }

        return $next($request);
    }
}