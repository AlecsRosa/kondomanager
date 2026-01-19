<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Enums\CategoriaEventoEnum;
use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Rata;
use App\Models\Gestionale\ScritturaContabile;
use App\Models\Gestionale\ContoContabile;
use App\Models\Gestionale\RigaScrittura;
use App\Enums\StatoPianoRate;
use App\Enums\VisibilityStatus;
use App\Events\Gestionale\RataEmessa;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use App\Traits\HandleFlashMessages;
use App\Traits\HasEsercizio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EmissioneRateController extends Controller
{
    use HandleFlashMessages, HasEsercizio;

    public function store(Request $request, Condominio $condominio, PianoRate $pianoRate)
    {
        Log::info("--- START EMISSIONE RATE ---", [
            'condominio_id' => $condominio->id,
            'rate_ids' => $request->rate_ids
        ]);
  
        if ($pianoRate->stato !== StatoPianoRate::APPROVATO) {
            return back()->with($this->flashError('Devi approvare il piano rate prima di poter emettere le rate.'));
        }

        $request->validate([
            'rate_ids' => 'required|array|min:1',
            'rate_ids.*' => 'exists:rate,id',
            'data_emissione' => 'required|date',
            'descrizione_personalizzata' => 'nullable|string|max:255',
        ]);

        $esercizio = $this->getEsercizioCorrente($condominio);
        
        $contoCrediti = ContoContabile::where('condominio_id', $condominio->id)
            ->where('ruolo', 'crediti_condomini')
            ->first();
        $contoGestione = ContoContabile::where('condominio_id', $condominio->id)
            ->where('ruolo', 'gestione_rate')
            ->first();

        if (!$contoCrediti || !$contoGestione) {
            return back()->with($this->flashError('Mancano i conti contabili (Crediti o Gestione Rate).'));
        }

        try {
            DB::transaction(function () use ($request, $condominio, $pianoRate, $esercizio, $contoCrediti, $contoGestione) {
                
                $rateSelezionate = Rata::with('rateQuote')
                    ->where('piano_rate_id', $pianoRate->id)
                    ->whereIn('id', $request->rate_ids)
                    ->get();

                foreach ($rateSelezionate as $rata) {
                    // Salta se già emessa (ulteriore controllo di sicurezza)
                    if ($rata->rateQuote->whereNotNull('scrittura_contabile_id')->isNotEmpty()) continue;

                    $totaleRataCentesimi = 0; 

                    $scrittura = ScritturaContabile::create([
                        'condominio_id'      => $condominio->id,
                        'esercizio_id'       => $esercizio->id,
                        'gestione_id'        => $pianoRate->gestione_id,
                        'data_registrazione' => now(),
                        'data_competenza'    => $request->data_emissione,
                        'causale'            => $request->descrizione_personalizzata ?: "Emissione " . $rata->descrizione,
                        'tipo_movimento'     => 'emissione_rata',
                        'stato'              => 'registrata',
                        // Il numero di protocollo viene generato dal Trait qui
                    ]);

                    foreach ($rata->rateQuote as $quota) {
                        if ($quota->importo <= 0) continue;

                        $importoCentesimi = $quota->importo; 

                        $scrittura->righe()->create([
                            'conto_contabile_id' => $contoCrediti->id,
                            'anagrafica_id'      => $quota->anagrafica_id,
                            'immobile_id'        => $quota->immobile_id,
                            'rata_id'            => $rata->id,
                            'tipo_riga'          => 'dare',
                            'importo'            => $importoCentesimi,
                            'note'               => "Quota " . $rata->descrizione
                        ]);

                        $quota->update(['scrittura_contabile_id' => $scrittura->id]);
                        $totaleRataCentesimi += $importoCentesimi;
                    }

                    if ($totaleRataCentesimi > 0) {
                        $scrittura->righe()->create([
                            'conto_contabile_id' => $contoGestione->id,
                            'tipo_riga'          => 'avere',
                            'importo'            => $totaleRataCentesimi,
                            'note'               => "Totale emissione " . $rata->descrizione
                        ]);
                    }

                    // 1. UPDATE EVENTI UTENTE: Segniamo la rata come EMESSA
                    // Cerchiamo gli eventi dei condomini legati a questa rata
                    $userEvents = Evento::where('meta->type', 'scadenza_rata_condomino')
                        ->where('meta->context->rata_id', $rata->id)
                        ->get();

                    foreach ($userEvents as $evt) {
                        $meta = $evt->meta;
                        $meta['is_emitted'] = true;
                        $evt->update(['meta' => $meta]);
                    }

                    // --- EVENTO AGGIUNTO ---
                    // Questo cancella il task "Emettere Rata" dal calendario Admin
                    RataEmessa::dispatch($rata);

                    // 3. CANCELLAZIONE TASK SINCRONA (Fix Problema Cache)
                    // Cancelliamo subito il task Admin, così quando puliamo la cache è già sparito.
                    Evento::whereJsonContains('meta->context->rata_id', $rata->id)
                        ->whereJsonContains('meta->type', 'emissione_rata')
                        ->delete(); 
                        // Oppure ->update(['is_completed' => true]) se vuoi lo storico
                }
            });

            // 4. PURGE CACHE (MANCAVA QUESTO!)
            // Ora che i task sono cancellati dal DB, puliamo la cache.
            // Al prossimo reload (back()), il middleware ricalcolerà il conteggio a 0.
            Cache::forget('inbox_count_' . $request->user()->id);

            return back()->with($this->flashSuccess('Rate emesse correttamente.'));

        } catch (\Throwable $e) {
            
            // LOGGHIAMO L'ERRORE TECNICO PER NOI
            Log::error("Errore emissione rate: " . $e->getMessage());

            // GESTIONE ERRORE DUPLICATO PROTOCOLLO
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'numero_protocollo_unique')) {
                return back()->with($this->flashError(
                    'Errore di numerazione: Il sistema ha tentato di usare un numero di protocollo già esistente (forse cancellato in precedenza). Contatta l\'assistenza o prova a rigenerare i protocolli.'
                ));
            }

            // ERRORE GENERICO PER L'UTENTE
            return back()->with($this->flashError('Si è verificato un errore tecnico durante l\'emissione. L\'operazione è stata annullata.'));
        }
    }

    public function destroy(Request $request, Condominio $condominio, PianoRate $pianoRate, Rata $rata)
    {
        $haPagamenti = DB::table('rate_quote')
            ->where('rata_id', $rata->id)
            ->where('importo_pagato', '>', 0)
            ->exists();

        if ($haPagamenti) {
            return back()->with($this->flashError('Impossibile annullare: ci sono già incassi registrati.'));
        }

        // 1. RECUPERO ESERCIZIO (Fix "Missing parameter")
        // Usiamo il Trait come nel metodo store per ottenere l'esercizio attivo
        $esercizio = $this->getEsercizioCorrente($condominio);

        if (!$esercizio) {
            return back()->with($this->flashError('Nessun esercizio aperto trovato per generare il link del task.'));
        }

        try {
            // Passiamo $esercizio dentro la closure con 'use'
            DB::transaction(function () use ($rata, $condominio, $pianoRate, $request, $esercizio) { 
                
                // A. Cancellazione Contabile
                $scrittureIds = $rata->rateQuote()->pluck('scrittura_contabile_id')->filter()->unique();
                $rata->rateQuote()->update(['scrittura_contabile_id' => null]);

                if ($scrittureIds->isNotEmpty()) {
                    RigaScrittura::whereIn('scrittura_id', $scrittureIds)->delete();
                    ScritturaContabile::whereIn('id', $scrittureIds)->forceDelete(); 
                }

                // C. UPDATE EVENTI UTENTE: Segniamo la rata come NON EMESSA (Rollback)
                $userEvents = Evento::where('meta->type', 'scadenza_rata_condomino')
                    ->where('meta->context->rata_id', $rata->id)
                    ->get();

                foreach ($userEvents as $evt) {
                    $meta = $evt->meta;
                    $meta['is_emitted'] = false; 
                    $evt->update(['meta' => $meta]);
                }

                // B. RIPRISTINO TASK NELLA INBOX
                
                // Recupero categoria admin
                $catAdmin = CategoriaEvento::where('name', CategoriaEventoEnum::SCADENZE_AMMINISTRATIVE->value)->first();
                
                // Calcolo date
                $dataPromemoria = $rata->data_scadenza->copy()->subDays(7)->setTime(9, 0);
                
                Evento::firstOrCreate(
                    [
                        'title' => "Emettere rata {$rata->numero_rata} - {$condominio->nome}",
                        'meta->context->rata_id' => $rata->id, 
                        'meta->type' => 'emissione_rata'
                    ],
                    [
                        'start_time' => $dataPromemoria,
                        'end_time'   => $dataPromemoria->copy()->addHour(),
                        'created_by' => $request->user()->id,
                        'description' => "Ricordati di emettere le ricevute per questa rata entro la scadenza. (Riemissione dopo annullamento)",
                        'category_id' => $catAdmin?->id,
                        'visibility'  => VisibilityStatus::HIDDEN->value, 
                        'is_approved' => true,
                        'meta' => [
                            'type'            => 'emissione_rata',
                            'requires_action' => true, 
                            'context' => [
                                'piano_rate_id' => $pianoRate->id,
                                'rata_id'       => $rata->id
                            ],
                            'gestione'          => $pianoRate->gestione->nome ?? 'Gestione',
                            'condominio_nome'   => $condominio->nome,
                            'totale_rata'       => $rata->importo_totale,
                            'anagrafiche_count' => $rata->rateQuote->unique('anagrafica_id')->count(),
                            'scadenza_reale'    => $rata->data_scadenza->toDateString(),
                            'numero_rata'       => $rata->numero_rata,
                            'piano_nome'        => $pianoRate->nome,
                            
                            // 🛠️ FIX ROUTE PARAMETER USANDO IL TRAIT
                            'action_url'        => route('admin.gestionale.esercizi.piani-rate.show', [
                                'condominio' => $condominio->id,
                                'esercizio'  => $esercizio->id, 
                                'pianoRate'  => $pianoRate->id
                            ])
                        ],
                    ]
                );
                
                // Relazioni Many-to-Many
                $evento = Evento::where('meta->context->rata_id', $rata->id)
                                ->where('meta->type', 'emissione_rata')
                                ->first();
                                
                if ($evento) {
                    $evento->condomini()->syncWithoutDetaching([$condominio->id]);
                    if ($request->user()->anagrafica_id) {
                        $evento->anagrafiche()->syncWithoutDetaching([$request->user()->anagrafica_id]);
                    }
                }
            });

            // 3. AGGIORNA CACHE DOPO ANNULLAMENTO
            Cache::forget('inbox_count_' . $request->user()->id);

            return back()->with($this->flashSuccess('Emissione annullata. La rata è tornata in bozza e il promemoria è stato ripristinato.'));

        } catch (\Throwable $e) {
            Log::error("Errore annullamento: " . $e->getMessage());
            return back()->with($this->flashError('Si è verificato un errore durante l\'annullamento.'));
        }
    }
}