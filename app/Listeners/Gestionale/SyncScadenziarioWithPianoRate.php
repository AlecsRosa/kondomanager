<?php

namespace App\Listeners\Gestionale;

use App\Enums\CategoriaEventoEnum;
use App\Enums\StatoPianoRate;
use App\Enums\VisibilityStatus; 
use App\Events\Gestionale\PianoRateStatusUpdated;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncScadenziarioWithPianoRate implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PianoRateStatusUpdated $event): void
    {
        // 🔥 FIX QUI: Aggiunto ->value perché l'Enum non si converte in stringa da solo
        Log::info("Listener avviato per Piano Rate ID: {$event->pianoRate->id} - Stato: {$event->newStatus->value}");

        if ($event->newStatus === StatoPianoRate::APPROVATO) {
            $this->createEvents($event->pianoRate, $event->condominio, $event->esercizio, $event->user);
        } elseif ($event->newStatus === StatoPianoRate::BOZZA) {
            $this->deleteEvents($event->pianoRate, $event->user);
        }
    }

    private function createEvents($pianoRate, $condominio, $esercizio, $user)
    {
        Log::info("Inizio creazione eventi...");

        $pianoRate->loadMissing('gestione');
        $nomeGestione = $pianoRate->gestione->nome ?? 'Gestione';

        // Avwiamo la transazione
        DB::transaction(function () use ($pianoRate, $condominio, $esercizio, $user, $nomeGestione) {

            $catAdmin = CategoriaEvento::firstOrCreate(
                ['name' => CategoriaEventoEnum::SCADENZE_AMMINISTRATIVE->value],
                ['description' => 'Auto']
            );
            $catPublic = CategoriaEvento::firstOrCreate(
                ['name' => CategoriaEventoEnum::SCADENZE_RATE_CONDOMINIALI->value],
                ['description' => 'Auto']
            );

            // Carichiamo le rate
            $rate = $pianoRate->rate()
                ->with(['rateQuote.anagrafica', 'rateQuote.immobile']) 
                ->get(); 

            Log::info("Trovate " . $rate->count() . " rate da processare.");

            foreach ($rate as $rata) {

                // --- 1. EVENTO ADMIN: EMISSIONE ---
                $dataPromemoria = $rata->data_scadenza->copy()->subDays(7)->setTime(9, 0);
                $titoloAdmin = "Emettere rata {$rata->numero_rata} - {$condominio->nome}";

                $urlEmissione = route('admin.gestionale.esercizi.piani-rate.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoRate'  => $pianoRate->id
                ]);

                $eventoAdmin = Evento::firstOrCreate(
                    [
                        'title'       => $titoloAdmin,
                        'start_time'  => $dataPromemoria,
                    ],
                    [
                        'created_by'  => $user->id,
                        'description' => "Ricordati di emettere le ricevute per questa rata entro la scadenza. Tutte le anagrafiche coinvolte riceveranno notifica dell'avvenuta emissione.",
                        'end_time'    => $dataPromemoria->copy()->addHour(),
                        'category_id' => $catAdmin->id,
                        'visibility'  => VisibilityStatus::HIDDEN->value, 
                        'is_approved' => true,
                        'meta' => [
                            'type'              => 'emissione_rata',
                            'is_emitted'        => false,
                            'requires_action'   => true, 
                            'gestione'          => $nomeGestione,
                            'condominio_nome'   => $condominio->nome,
                            'totale_rata'       => $rata->importo_totale,
                            'numero_rata'       => $rata->numero_rata,
                            'action_url'        => $urlEmissione,
                            'context' => [
                                'piano_rate_id' => $pianoRate->id,
                                'rata_id'       => $rata->id
                            ],
                        ],
                    ]
                );
                
                $eventoAdmin->condomini()->syncWithoutDetaching([$condominio->id]);
                if ($user->anagrafica_id) $eventoAdmin->anagrafiche()->syncWithoutDetaching([$user->anagrafica_id]);


                // --- 1-BIS. EVENTO ADMIN: VERIFICA INCASSI ---
                $dataCheck = $rata->data_scadenza->copy()->addDays(4)->setTime(9, 0); 
                $titoloCheck = "Verifica incassi - Rata {$rata->numero_rata} ({$condominio->nome})";
                
                $urlIncassi = route('admin.gestionale.movimenti-rate.create', ['condominio' => $condominio->id]);

                $eventoCheck = Evento::firstOrCreate(
                    [
                        'title'      => $titoloCheck,
                        'start_time' => $dataCheck,
                    ],
                    [
                        'created_by'  => $user->id,
                        'end_time'    => $dataCheck->copy()->addHour(),
                        'description' => "La rata è scaduta il " . $rata->data_scadenza->format('d/m/Y') . ". I tempi tecnici bancari sono trascorsi: controlla l'estratto conto e registra gli incassi cumulativi.",
                        'category_id' => $catAdmin->id, 
                        'visibility'  => VisibilityStatus::HIDDEN->value,
                        'is_approved' => true,
                        'meta' => [
                            'type'            => 'controllo_incassi',
                            'requires_action' => true,
                            'condominio_nome' => $condominio->nome,
                            'numero_rata'     => $rata->numero_rata,
                            'gestione'        => $nomeGestione,
                            'action_url'      => $urlIncassi,
                            'context' => [
                                'piano_rate_id' => $pianoRate->id,
                                'rata_id'       => $rata->id
                            ],
                        ],
                    ]
                );
                
                $eventoCheck->condomini()->syncWithoutDetaching([$condominio->id]);
                if ($user->anagrafica_id) $eventoCheck->anagrafiche()->syncWithoutDetaching([$user->anagrafica_id]);


                // --- 2. EVENTI CONDÒMINI ---
                $quotePerAnagrafica = $rata->rateQuote->groupBy('anagrafica_id');

                foreach ($quotePerAnagrafica as $anagraficaId => $quote) {
                    $anagrafica = $quote->first()->anagrafica;
                    if (!$anagrafica) continue;

                    $esiste = Evento::where('start_time', $rata->data_scadenza->copy()->setTime(0, 0))
                        ->whereJsonContains('meta->context->rata_id', $rata->id)
                        ->whereJsonContains('meta->type', 'scadenza_rata_condomino')
                        ->whereHas('anagrafiche', fn($q) => $q->where('anagrafica_id', $anagraficaId))
                        ->exists();

                    if ($esiste) continue;

                    $importoVal = $quote->sum('importo');
                    $dettaglioQuote = $quote->map(function($q) {
                        $immobile = $q->immobile;
                        $desc = $immobile ? "Int. {$immobile->interno} ({$immobile->nome})" : "Unità";
                        return ['descrizione' => $desc, 'importo' => $q->importo];
                    })->values()->toArray();

                    $descUser = "Gentile {$anagrafica->nome}, ti ricordiamo la scadenza della rata condominiale. Effettua il pagamento entro la data indicata per evitare solleciti.";
                    if (!empty($rata->note)) $descUser .= "\n\nNote: {$rata->note}";

                    $eventoUser = Evento::create([
                        'title'       => "Scadenza rata {$rata->numero_rata} - {$pianoRate->nome}",
                        'start_time'  => $rata->data_scadenza->copy()->setTime(0, 0),
                        'end_time'    => $rata->data_scadenza->copy()->setTime(23, 59),
                        'created_by'  => $user->id,
                        'description' => $descUser,
                        'category_id' => $catPublic->id,
                        'visibility'  => VisibilityStatus::PRIVATE->value,
                        'is_approved' => true,
                        'timezone'    => config('app.timezone'),
                        'meta'        => [
                            'type'              => 'scadenza_rata_condomino',
                            'is_emitted'        => false, 
                            'requires_action'   => false, 
                            'status'            => 'pending',
                            'importo_originale' => $importoVal,
                            'importo_pagato'    => 0,
                            'importo_restante'  => $importoVal,
                            'dettaglio_quote'   => $dettaglioQuote, 
                            'gestione'          => $nomeGestione,
                            'condominio_nome'   => $condominio->nome,
                            'numero_rata'       => $rata->numero_rata,
                            'piano_nome'        => $pianoRate->nome,
                            'context' => [
                                'piano_rate_id' => $pianoRate->id,
                                'rata_id'       => $rata->id
                            ],
                        ],
                    ]);

                    $eventoUser->anagrafiche()->attach($anagraficaId);
                    $eventoUser->condomini()->attach($condominio->id);
                }
            }
        }); // Fine Transaction

        if ($user) {
            Cache::forget('inbox_count_' . $user->id);
        }
        
        Log::info("Listener: Eventi creati con successo.");
    }

    private function deleteEvents($pianoRate, $user)
    {
        Log::info("Cancellazione eventi...");
        Evento::whereJsonContains('meta->context->piano_rate_id', $pianoRate->id)->delete();
        if ($user) Cache::forget('inbox_count_' . $user->id);
    }
}