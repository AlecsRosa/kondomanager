<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Settings\MailSettings;
use App\Traits\HandleFlashMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class MailSettingsController extends Controller
{
    use HandleFlashMessages;

    /**
     * Visualizza la pagina di configurazione
     */
    public function edit(MailSettings $settings)
    {
        return Inertia::render('impostazioni/impostazioniMail', [
            'settings' => $settings,
            'mail_host_env' => config('mail.mailers.smtp.host'),
        ]);
    }

    public function update(Request $request, MailSettings $settings)
    {
        $request->validate([
            'mail_enabled' => 'boolean',
            'mail_host' => 'required_if:mail_enabled,true',
            'mail_port' => 'required|numeric',
            'mail_from_address' => 'required|email',
        ]);

        try {
            $settings->mail_enabled = $request->mail_enabled;
            $settings->mail_host = $request->mail_host;
            $settings->mail_port = (int) $request->mail_port;
            $settings->mail_username = $request->mail_username;
            $settings->mail_encryption = $request->mail_encryption;
            $settings->mail_from_address = $request->mail_from_address;
            $settings->mail_from_name = $request->mail_from_name;

            if ($request->filled('mail_password')) {

                $settings->mail_password = Crypt::encryptString($request->mail_password);

            }

            $settings->save();

            return back()->with($this->flashSuccess(__('impostazioni.success_save_mail_settings')));

        } catch (\Exception $e) {

            return back()->with($this->flashError(__('impostazioni.error_save_mail_settings')));
            
        }
    }

    public function testConnection(Request $request)
    {
        try {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $request->mail_host);
            Config::set('mail.mailers.smtp.port', (int) $request->mail_port);
            Config::set('mail.mailers.smtp.username', $request->mail_username);
            Config::set('mail.mailers.smtp.password', $request->mail_password);
            Config::set('mail.mailers.smtp.encryption', $request->mail_encryption);
            Config::set('mail.from.address', $request->mail_from_address);
            Config::set('mail.from.name', $request->mail_from_name ?? 'Kondomanager Test');

            Mail::raw("Test SMTP Kondomanager riuscito!", function ($m) use ($request) {
                $m->to($request->test_email)->subject("Test Connessione");
            });

            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }
    }
}
