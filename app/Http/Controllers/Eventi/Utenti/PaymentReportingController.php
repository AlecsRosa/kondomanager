<?php

namespace App\Http\Controllers\Eventi\Utenti;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Condominio;
use App\Enums\VisibilityStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentReportingController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Evento $evento)
    {
        $this->authorize('view', $evento);

        $currentStatus = $evento->meta['status'] ?? 'pending';

        // Blocchiamo solo se pagato o GIÀ in verifica (reported).
        // Se è 'rejected', lasciamo passare per il retry.
        if ($currentStatus === 'paid') return back()->with('error', 'Già pagata.');
        if ($currentStatus === 'reported') return back()->with('info', 'Già segnalato.');

        DB::transaction(function () use ($evento) {
            
            // 1. Aggiorna stato evento utente (RESETTA RIFIUTO)
            $meta = $evento->meta;
            $meta['status'] = 'reported'; 
            $meta['reported_at'] = now()->toIso8601String();
            
            // PULIZIA: Se era stato rifiutato, rimuoviamo la motivazione così sparisce il box rosso
            if (isset($meta['rejection_reason'])) {
                unset($meta['rejection_reason']);
                unset($meta['rejected_at']);
            }

            $evento->update(['meta' => $meta]);

            // 2. Prepara dati
            $anagrafica = $evento->anagrafiche->first();
            $nomeAnagrafica = $anagrafica ? $anagrafica->nome : 'Condòmino';
            
            // Importo in Euro (es. 33.29)
            $importoEuro = ($meta['importo_restante'] ?? 0) / 100;
            $importoFormat = number_format($importoEuro, 2, ',', '.');
            
            $condominioId = $evento->condomini->first()?->id;

            // 3. Crea Task Admin (PRIMA creiamo il task, così abbiamo l'ID)
            $adminEvent = Evento::create([
                'title'       => "Verifica Incasso: {$evento->title}",
                'description' => "Il condòmino {$nomeAnagrafica} ha segnalato di aver pagato {$importoFormat}€.\n" .
                                 "Verifica l'estratto conto bancario e registra l'incasso.",
                'start_time'  => now(),
                'end_time'    => now()->addHour(),
                'created_by'  => Auth::id(),
                'category_id' => $evento->category_id,
                'visibility'  => VisibilityStatus::HIDDEN->value,
                'is_approved' => true,
                'meta'        => [
                    'type'            => 'verifica_pagamento',
                    'requires_action' => true,
                    'context'         => [
                        'related_event_id' => $evento->id,
                        'rata_id'          => $meta['context']['rata_id'] ?? null,
                        'piano_rate_id'    => $meta['context']['piano_rate_id'] ?? null,
                        'anagrafica_id'    => $anagrafica?->id
                    ],
                    'condominio_nome'    => $meta['condominio_nome'] ?? '',
                    'importo_dichiarato' => $meta['importo_restante'] ?? 0,
                    'action_url'         => null // Lo riempiamo tra un attimo
                ]
            ]);

            if ($condominioId) {
                $adminEvent->condomini()->attach($condominioId);
            }
            // Opzionale: colleghiamo anche l'anagrafica per il fix del nome "Sconosciuto"
            if ($anagrafica) {
                $adminEvent->anagrafiche()->attach($anagrafica->id);
            }

            // 4. Genera Link "Registra Incasso" CON ID TASK
            if ($condominioId) {
                $actionUrl = route('admin.gestionale.movimenti-rate.create', [
                    'condominio' => $condominioId,
                    'prefill_rata_id'       => $meta['context']['rata_id'] ?? null,
                    'prefill_anagrafica_id' => $anagrafica?->id,
                    'prefill_importo'       => $importoEuro,
                    'prefill_descrizione'   => "Saldo rata condominiale (Segnalazione utente)",
                    // FONDAMENTALE: Passiamo l'ID del task appena creato per chiuderlo dopo
                    'related_task_id'       => $adminEvent->id 
                ]);

                // Aggiorniamo il task con l'URL corretto
                $adminMeta = $adminEvent->meta;
                $adminMeta['action_url'] = $actionUrl;
                $adminEvent->update(['meta' => $adminMeta]);
            }
        });

        return back()->with('success', 'Segnalazione inviata con successo.');
    }
}