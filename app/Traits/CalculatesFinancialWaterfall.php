<?php

namespace App\Traits;

use App\Models\Gestionale\RataQuote;

trait CalculatesFinancialWaterfall
{
    /**
     * Applica il calcolo "a cascata" del debito.
     * Versione: 1.9.3 (Fix Asimmetria Crediti/Debiti)
     */
    public function applyFinancialWaterfall($events, $anagraficaId, $condominioIds = null, $pianoRateIds = null)
    {
        $condominioIds = is_numeric($condominioIds) ? [$condominioIds] : $condominioIds;
        $pianoRateIds = is_numeric($pianoRateIds) ? [$pianoRateIds] : $pianoRateIds;

        // 1. Recupero Quote
        $tutteLeQuote = RataQuote::where('anagrafica_id', $anagraficaId)
            ->whereHas('rata.pianoRate', function ($q) use ($condominioIds, $pianoRateIds) {
                if (!empty($condominioIds)) {
                    $q->whereIn('condominio_id', (array)$condominioIds);
                }
                if (!empty($pianoRateIds)) {
                    $q->whereIn('id', (array)$pianoRateIds);
                }
            })
            ->with(['rata.pianoRate' => function($q) {
                $q->select('id', 'condominio_id'); 
            }])
            ->get();

        if ($tutteLeQuote->isEmpty()) {
            return $events;
        }

        // 2. Raggruppamento
        $quotesByCondominio = $tutteLeQuote->groupBy(function ($quota) {
            return $quota->rata->pianoRate->condominio_id;
        });

        $globalRataStatus = [];

        foreach ($quotesByCondominio as $condominioId => $quotesDelCondominio) {
            
            // A. Calcolo Debito/Credito Iniziale (CENTESIMI)
            $debitoPregressoDaCoprire = 0; 
            
            $rateAggregate = $quotesDelCondominio->groupBy('rata_id')->map(function ($quotes) use (&$debitoPregressoDaCoprire) {
                $rata = $quotes->first()->rata;
                
                $saldoIncorporato = 0; 
                foreach($quotes as $q) {
                    $regole = json_decode($q->regole_calcolo ?? '{}', true);
                    if (isset($regole['importi']['saldo_usato'])) {
                        $saldoIncorporato += (int)$regole['importi']['saldo_usato'];
                    }
                }
                
                $debitoPregressoDaCoprire += $saldoIncorporato;

                return [
                    'rata_id' => $rata->id,
                    'numero_rata' => $rata->numero_rata,
                    'scadenza' => $rata->data_scadenza,
                    'importo_netto' => (int)$quotes->sum('importo'), 
                    'pagato' => (int)$quotes->sum('importo_pagato'), 
                    'saldo_incorporato' => $saldoIncorporato
                ];
            })->sortBy('scadenza');

            // B. Variabili di Stato (Wallet)
            $creditoDisponibile = 0; 
            $accumuloDebito = max(0, $debitoPregressoDaCoprire); 
            
            if ($debitoPregressoDaCoprire < 0) {
                $creditoDisponibile = abs($debitoPregressoDaCoprire);
            }

            $listaInsoluti = []; 

            // C. Ciclo Waterfall
            foreach ($rateAggregate as $rata) {
                $id = $rata['rata_id'];
                $netto = $rata['importo_netto']; 
                $saldoInRata = $rata['saldo_incorporato'];
                
                // Assorbimento Debito (solo se era un debito positivo)
                if ($saldoInRata > 0) {
                    $accumuloDebito = max(0, $accumuloDebito - $saldoInRata);
                }

                $creditoInizialeSnapshot = $creditoDisponibile; 
                $residuoFinale = 0; 
                $creditoUsatoQui = 0;

                // --- FIX CRUCIALE: DETERMINIAMO COSA DOBBIAMO PAGARE ---
                // Se il saldo incorporato è negativo (CREDITO), significa che l'importo $netto nel DB è già scontato.
                // Per vedere se il nostro Wallet copre la spesa, dobbiamo ricostruire il "Costo Puro" della rata.
                // Esempio Marta: Netto DB = -6671. SaldoInc = -10000.
                // Da Pagare (Costo Puro) = -6671 - (-10000) = 3329.
                
                if ($saldoInRata < 0) {
                    $daCoprire = $netto - $saldoInRata;
                } else {
                    // Se è un debito o zero, l'importo nel DB è quello che dobbiamo pagare davvero.
                    $daCoprire = $netto;
                }

                // --- LOGICA DI PAGAMENTO ---
                // Usiamo $daCoprire invece di $netto per erodere il credito
                
                if ($daCoprire > 0) {
                    // C'è qualcosa da pagare (Costo Puro positivo)
                    $daPagareReale = max(0, $daCoprire - $rata['pagato']); // Quanto resta da versare

                    if ($creditoDisponibile >= $daPagareReale) {
                        // Il credito copre tutto
                        $creditoUsatoQui = $daPagareReale;
                        $creditoDisponibile -= $daPagareReale;
                        $residuoFinale = 0;
                    } else {
                        // Il credito non basta o è finito
                        $creditoUsatoQui = $creditoDisponibile;
                        $residuoFinale = $daPagareReale - $creditoDisponibile; // Questo è quanto manca
                        $creditoDisponibile = 0;
                    }
                } else {
                    // La rata pura è negativa (es. rimborso spese o conguaglio a favore non derivante dal saldo iniziale)
                    $creditoDisponibile += abs($daCoprire);
                    $residuoFinale = $daCoprire; // Sarà negativo o zero
                }

                $globalRataStatus[$id] = [
                    'residuo_reale' => $residuoFinale,
                    'is_covered_by_credit' => ($creditoUsatoQui > 0 && $residuoFinale === 0), 
                    'credito_disponibile_start' => $creditoInizialeSnapshot,
                    'numero_rata' => $rata['numero_rata'],
                    'arretrati_pregressi' => $accumuloDebito, 
                    'lista_rate_precedenti' => implode(', ', $listaInsoluti),
                    'saldo_incorporato' => $saldoInRata
                ];

                if ($residuoFinale > 0) {
                    $accumuloDebito += $residuoFinale;
                    $listaInsoluti[] = '#' . $rata['numero_rata']; 
                }
            }
        }

        // 4. Iniezione (Invariato)
        $collection = $events instanceof \Illuminate\Pagination\AbstractPaginator ? $events->getCollection() : $events;

        $processed = $collection->map(function ($event) use ($globalRataStatus) {
            $meta = $event->meta;
            if (is_string($meta)) $meta = json_decode($meta, true);
            if (!is_array($meta)) $meta = [];

            $rataId = $meta['context']['rata_id'] ?? ($meta['rata_id'] ?? null);
            if (!$rataId && !empty($meta['dettaglio_quote'][0]['rata_id'])) {
                $rataId = $meta['dettaglio_quote'][0]['rata_id'];
            }

            if ($rataId && isset($globalRataStatus[$rataId])) {
                $status = $globalRataStatus[$rataId];
                
                $meta['importo_restante'] = $status['residuo_reale'];
                $meta['is_covered_by_credit'] = $status['is_covered_by_credit'];
                $meta['arretrati_pregressi'] = $status['arretrati_pregressi'];
                $meta['rif_arretrati'] = $status['lista_rate_precedenti']; 
                $meta['saldo_iniziale_assorbito'] = ($status['arretrati_pregressi'] <= 0);
                $meta['saldo_incorporato'] = $status['saldo_incorporato']; 

                if (!empty($meta['dettaglio_quote'])) {
                    foreach ($meta['dettaglio_quote'] as $k => $quota) {
                        if ($k === 0 && $status['credito_disponibile_start'] > 0) {
                            $meta['dettaglio_quote'][$k]['audit']['credito_pregresso_usato'] = -$status['credito_disponibile_start'];
                        }
                    }
                }
                $event->setAttribute('meta', $meta);
            }
            return $event;
        });

        if ($events instanceof \Illuminate\Pagination\AbstractPaginator) {
            $events->setCollection($processed);
            return $events;
        }

        return $processed;
    }
}