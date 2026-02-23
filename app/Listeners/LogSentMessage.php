<?php

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LogSentMessage
{
    /**
     * Gestisce l'evento di invio mail.
     */
    public function handle(MessageSent $event): void
    {
        try {
            // In Laravel 12, $event->message è un oggetto Symfony\Component\Mime\Email
            $message = $event->message;

            // 1. Estrazione del Destinatario
            // Symfony Mailer restituisce un array di oggetti Address
            $to = $message->getTo();
            $recipient = 'Sconosciuto';

            if (is_array($to) && count($to) > 0) {
                // Prendiamo il primo destinatario della lista
                $recipient = $to[0]->getAddress();
            }

            // 2. Determiniamo il driver utilizzato (SMTP o Log)
            // Se il nostro Provider ha lavorato bene, qui avremo 'smtp' o 'log'
            $driver = Config::get('mail.default');
            
            // 3. Salvataggio nel Database
            MailLog::create([
                'recipient' => $recipient,
                'subject'   => $message->getSubject() ?? '(Nessun oggetto)',
                'mailer'    => $driver,
                // Se il driver è 'log', segniamo lo stato come 'logged' (simulato)
                'status'    => ($driver === 'log') ? 'logged' : 'sent',
                'sent_at'   => now(),
            ]);

        } catch (\Throwable $e) {
            // Se il salvataggio del log fallisce, NON dobbiamo bloccare l'invio della mail.
            // Scriviamo l'errore nel file di log di sistema (storage/logs/laravel.log)
            Log::error("Errore salvataggio MailLog: " . $e->getMessage());
        }
    }
}