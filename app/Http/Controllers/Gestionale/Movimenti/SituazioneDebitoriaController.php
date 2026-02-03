<?php

namespace App\Http\Controllers\Gestionale\Movimenti;

use App\Http\Controllers\Controller;
use App\Helpers\MoneyHelper; 
use App\Models\Condominio;
use App\Models\Gestionale\RataQuote; 
use App\Traits\HasEsercizio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SituazioneDebitoriaController extends Controller
{
    use HasEsercizio;

    public function __invoke(Request $request, Condominio $condominio): JsonResponse
    {
        // 1. Base Query
        $query = RataQuote::query()
            ->whereHas('rata', function($q) use ($condominio) {
                $q->whereHas('pianoRate', fn($p) => $p->where('condominio_id', $condominio->id));
            });

        // 2. Filtro Logico
        $query->where(function($q) {
            $q->whereRaw('importo > importo_pagato') 
              ->orWhere('importo', '<', 0);          
        });

        // 3. Filtri Contestuali
        if ($request->has('immobile_id') && $request->immobile_id) {
            $query->where('immobile_id', $request->immobile_id);
        } elseif ($request->has('anagrafica_id') && $request->anagrafica_id) {
            $query->where('anagrafica_id', $request->anagrafica_id);
        } else {
            return response()->json(['rate' => []]);
        }

        // 4. Esecuzione
        $rawQuotes = $query->with(['rata.pianoRate.gestione', 'immobile', 'rata', 'anagrafica'])
            ->orderBy('data_scadenza', 'asc') 
            ->get();

        // 5. AGGREGAZIONE
        $groupedRate = $rawQuotes->groupBy('rata_id')->map(function ($gruppoQuotes) use ($condominio) {
            
            $first = $gruppoQuotes->first();
            
            // Calcoli Matematici sui Centesimi (Interi)
            $importoTotale = $gruppoQuotes->sum('importo');
            $importoPagato = $gruppoQuotes->sum('importo_pagato');
            $residuoNetto = ($importoTotale - $importoPagato);

            if (abs($residuoNetto) < 1) return null; 

            // Prepariamo dati per il fallback (Legacy)
            $pianoRate = $first->rata->pianoRate;
            $metodoDist = $pianoRate->metodo_distribuzione ?? 'prima_rata'; 
            $numeroRata = $first->rata->numero_rata;
            $totaleRatePiano = $pianoRate->numero_rate;

            // Cache locale per l'esercizio ID (serve solo nel fallback)
            $esercizioId = null;

            // --- MAPPATURA DETTAGLIO (TOOLTIP) ---
            $dettaglioTooltip = $gruppoQuotes->map(function($q) use ($condominio, &$esercizioId, $metodoDist, $numeroRata, $totaleRatePiano, $first) {
                
                $residuoNettoQuota = ($q->importo - $q->importo_pagato); 
                $unita = $q->immobile ? "Int. {$q->immobile->interno}" : 'Generico';
                
                $componenteSaldo = 0;
                $componenteSpesa = 0;

                // LOGICA IBRIDA: 
                // JSON (Introdotto nella versione 1.8) vs 
                // CALCOLO (Se chi usava versioni precedenti aveva già emesso le rate e registarto pagamenti)
                
                // 1. C'è lo Snapshot JSON? (Versione 1.8+)
                if (!empty($q->regole_calcolo)) {
                    $json = is_string($q->regole_calcolo) ? json_decode($q->regole_calcolo) : (object) $q->regole_calcolo;
                    
                    // Leggiamo direttamente dallo storico
                    $saldoUsato = $json->importi->saldo_usato ?? 0;
                    $quotaPura  = $json->importi->quota_pura_gestione ?? 0;

                    // NOTA: Qui stiamo mostrando come è composta la rata *Originale*.
                    // Se l'utente ha pagato parzialmente, il residuo è calcolato matematicamente sopra.
                    // Nel tooltip mostriamo i componenti originali per trasparenza.
                    $componenteSaldo = $saldoUsato;
                    $componenteSpesa = $quotaPura;

                } 
                // 2. Fallback: Calcolo Inverso (Versione 1.7 e precedenti)
                else {
                    
                    // Recuperiamo Esercizio (Lazy Load solo se serve)
                    if (!$esercizioId) {
                        if ($first->data_scadenza) {
                            $esercizioId = DB::table('esercizi')
                                ->where('condominio_id', $condominio->id)
                                ->whereDate('data_inizio', '<=', $first->data_scadenza)
                                ->whereDate('data_fine', '>=', $first->data_scadenza)
                                ->value('id');
                        }
                        if (!$esercizioId) {
                            $esercizioCorrente = $this->getEsercizioCorrente($condominio);
                            $esercizioId = $esercizioCorrente ? $esercizioCorrente->id : null;
                        }
                    }

                    // Recupero Saldo
                    $saldoInizialeTrovato = 0;
                    if ($q->anagrafica_id && $esercizioId) {
                        // Piccola ottimizzazione: potresti cachare anche questo se le righe sono tante
                        $saldoInizialeTrovato = DB::table('saldi')
                            ->where('condominio_id', $condominio->id)
                            ->where('esercizio_id', $esercizioId)
                            ->where('anagrafica_id', $q->anagrafica_id)
                            ->sum('saldo_iniziale'); 
                    }

                    // Logica di distribuzione Legacy
                    $applicareSaldoQui = ($q->id === $first->id);

                    if ($applicareSaldoQui) {
                        if ($metodoDist === 'prima_rata') {
                            if ($numeroRata == 1) {
                                $componenteSaldo = $saldoInizialeTrovato;
                            }
                        } elseif ($metodoDist === 'tutte_rate' && $totaleRatePiano > 0) {
                            $componenteSaldo = intval($saldoInizialeTrovato / $totaleRatePiano);
                        }
                    }
                    
                    // Reverse Engineering
                    // Nota: Qui usavamo il residuo netto, ma concettualmente il tooltip spiega l'origine.
                    // Per coerenza col vecchio sistema manteniamo la logica esistente:
                    $componenteSpesa = $residuoNettoQuota - $componenteSaldo;
                }

                return [
                    'unita'             => $unita,
                    'residuo'           => MoneyHelper::fromCents($residuoNettoQuota), 
                    'is_credito'        => $residuoNettoQuota < 0,
                    'componente_saldo'  => MoneyHelper::fromCents($componenteSaldo), 
                    'componente_spesa'  => MoneyHelper::fromCents($componenteSpesa)  
                ];
            })->values();

            $unitaCoinvolte = $gruppoQuotes->map(function($q) {
                return $q->immobile ? "Int. {$q->immobile->interno} ({$q->immobile->nome})" : null;
            })->filter()->unique()->join(', ');

            $isEmitted = $gruppoQuotes->contains(fn($q) => !is_null($q->scrittura_contabile_id));

            return [
                'id'              => $first->id,
                'rata_padre_id'   => $first->rata_id,
                'descrizione'     => $first->rata->descrizione ?? ("Rata n." . ($first->rata->numero_rata ?? '-')),
                'scadenza_human'  => $first->data_scadenza ? Carbon::parse($first->data_scadenza)->format('d/m/Y') : 'N/D',
                'importo_totale'  => MoneyHelper::fromCents($importoTotale),
                'residuo'         => MoneyHelper::fromCents($residuoNetto),
                'gestione'        => $first->rata->pianoRate->gestione->nome ?? 'Generica',
                'gestione_id'     => $first->rata->pianoRate->gestione_id,
                'unita'           => $unitaCoinvolte ?: 'Generico',
                'intestatario'    => $first->anagrafica ? $first->anagrafica->nome : 'N/D',
                'tipologia'       => 'Aggregato',
                'da_pagare'       => 0,     
                'selezionata'     => false, 
                'scaduta'         => $first->data_scadenza && Carbon::parse($first->data_scadenza)->isPast(),
                'is_credito'      => $residuoNetto < 0,
                'is_emitted'      => $isEmitted,
                'dettaglio_quote' => $dettaglioTooltip 
            ];
        })
        ->filter()
        ->values();

        return response()->json(['rate' => $groupedRate]);
    }
}