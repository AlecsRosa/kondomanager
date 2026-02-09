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
            'password_set' => !empty($settings->mail_password),
        ]);
    }

    public function update(Request $request, MailSettings $settings)
    {
        // 1. Definiamo le regole base
        $rules = [
            'mail_enabled' => 'boolean',
            'mail_host' => 'required_if:mail_enabled,true',
            'mail_port' => 'required|numeric',
            'mail_from_address' => 'required|email',
        ];

        // 2. Logica condizionale per la Password
        // Se abiliti la mail E non hai già una password salvata nel DB, allora è obbligatoria.
        // Altrimenti (se c'è già o se disabiliti), è opzionale (nullable).
        if ($request->mail_enabled && empty($settings->mail_password)) {
            $rules['mail_password'] = 'required';
        } else {
            $rules['mail_password'] = 'nullable';
        }

        // 3. Validazione
        $request->validate($rules);

        try {
            $settings->mail_enabled = $request->mail_enabled;
            $settings->mail_host = $request->mail_host;
            $settings->mail_port = (int) $request->mail_port;
            $settings->mail_username = $request->mail_username;
            $settings->mail_encryption = $request->mail_encryption;
            $settings->mail_from_address = $request->mail_from_address;
            $settings->mail_from_name = $request->mail_from_name;

            // Salviamo la password solo se l'utente ne ha digitata una nuova
            if ($request->filled('mail_password')) {
                $settings->mail_password = Crypt::encryptString($request->mail_password);
            }

            $settings->save();

            return back()->with($this->flashSuccess(__('impostazioni.success_save_mail_settings')));

        } catch (\Exception $e) {

            return back()->with($this->flashError(__('impostazioni.error_save_mail_settings')));
            
        }
    }

    public function testConnection(Request $request, MailSettings $settings)
    {
        try {
            // RECUPERO INTELLIGENTE PASSWORD
            // Usiamo quella del form se c'è, altrimenti proviamo a decriptare quella del DB
            $password = $request->mail_password;

            if (empty($password) && !empty($settings->mail_password)) {
                try {
                    $password = Crypt::decryptString($settings->mail_password);
                } catch (\Exception $e) {
                    // Fallback se non era criptata (vecchi dati o errore)
                    $password = $settings->mail_password;
                }
            }

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $request->mail_host);
            Config::set('mail.mailers.smtp.port', (int) $request->mail_port);
            Config::set('mail.mailers.smtp.username', $request->mail_username);
            Config::set('mail.mailers.smtp.password', $password); // Usiamo la variabile risolta sopra
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
