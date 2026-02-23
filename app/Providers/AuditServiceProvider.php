<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Mail\SendQueuedMailable;
use App\Models\MailLog;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AuditServiceProvider
 * * Questo Provider serve a colmare una lacuna del sistema di logging standard:
 * mentre il listener "LogSentMessage" registra le mail inviate con successo,
 * questo provider intercetta specificamente le mail FALLITE nelle CODE (Queue).
 */
class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Ascoltiamo l'evento globale "JobFailed".
        // Questo evento scatta SOLO quando un job ha esaurito tutti i tentativi (tries)
        // definiti nello scheduler ed è stato marcato definitivamente come "fallito".
        Queue::failing(function (JobFailed $event) {
            
            // ------------------------------------------------------------------
            // 1. SAFETY CHECK: La tabella esiste?
            // ------------------------------------------------------------------
            // Questo controllo è vitale. Durante un'installazione fresca o un
            // "migrate:fresh", Laravel avvia i provider PRIMA di creare le tabelle.
            // Senza questo if, il comando migrate fallirebbe cercando 'mail_logs'.
            if (!Schema::hasTable('mail_logs')) {
                return;
            }

            // Usiamo un try-catch per "blindare" il logger.
            // Se qualcosa va storto mentre logghiamo l'errore, non vogliamo
            // generare un'eccezione a cascata che blocchi il worker.
            try {
                // Recuperiamo i dati grezzi del job dal database/redis
                $data = $event->job->payload();
                
                // ------------------------------------------------------------------
                // 2. DESERIALIZZAZIONE
                // ------------------------------------------------------------------
                // I job in coda sono oggetti PHP "congelati" (serializzati) in stringhe.
                // Dobbiamo usare unserialize() per riportarli in vita e leggerne le proprietà
                // (come il destinatario o l'oggetto della mail).
                $commandObject = isset($data['data']['command']) 
                    ? unserialize($data['data']['command']) 
                    : null;

                // ------------------------------------------------------------------
                // 3. FILTRO: È una mail?
                // ------------------------------------------------------------------
                // Le code gestiscono tanti lavori (resize immagini, calcoli, pdf).
                // A noi interessa loggare solo se il job fallito era una Mail (SendQueuedMailable).
                if ($commandObject instanceof SendQueuedMailable) {
                    
                    // Estraiamo l'oggetto Mailable originale
                    $mailable = $commandObject->mailable;
                    $recipient = 'Sconosciuto';

                    // ------------------------------------------------------------------
                    // 4. ESTRAZIONE DESTINATARIO (Logica Robusta)
                    // ------------------------------------------------------------------
                    // Laravel e Symfony Mailer gestiscono i destinatari in modo complesso.
                    // A volte è un array, a volte un oggetto Address, a volte una stringa.
                    // Questa logica normalizza tutto per evitare errori.
                    if (property_exists($mailable, 'to')) {
                         $to = $mailable->to;
                         // Controllo se c'è almeno un destinatario
                         if (is_array($to) && count($to) > 0) {
                             $first = $to[0];
                             // Caso A: Oggetto Symfony Address (Standard Laravel 10+)
                             if (is_object($first) && method_exists($first, 'getAddress')) {
                                 $recipient = $first->getAddress(); 
                             } 
                             // Caso B: Array associativo vecchio stile
                             elseif (is_array($first) && isset($first['address'])) {
                                 $recipient = $first['address'];
                             } 
                             // Caso C: Stringa semplice
                             elseif (is_string($first)) {
                                 $recipient = $first;
                             }
                         }
                    }

                    // ------------------------------------------------------------------
                    // 5. SCRITTURA NEL LOG (Fallimento)
                    // ------------------------------------------------------------------
                    // Creiamo il record impostando lo status su 'failed'.
                    // Questo farà apparire l'icona rossa nella Dashboard "Audit & Logs".
                    MailLog::create([
                        'recipient' => $recipient,
                        'subject'   => $mailable->subject ?? '(Nessun oggetto)',
                        'mailer'    => config('mail.default'),
                        'status'    => 'failed', // Fondamentale per la UI
                        // Tronchiamo l'errore a 1000 caratteri per non intasare il DB
                        'error_message' => substr($event->exception->getMessage(), 0, 1000),
                        'sent_at'   => now(),
                    ]);
                }
            } catch (Throwable $e) {
                // Fail Silently: Se il logging fallisce (es. errore SQL), ignoriamo.
                // Non vogliamo interrompere il flusso di gestione delle code.
            }
        });
    }
}