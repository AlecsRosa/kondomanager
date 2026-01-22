<?php

namespace App\Traits;

use App\Models\Gestionale\RataQuote;

trait CalculatesFinancialWaterfall
{
    public function applyFinancialWaterfall($events, $anagraficaId)
    {
        $tutteLeQuote = RataQuote::where('anagrafica_id', $anagraficaId)
            ->whereHas('rata')
            ->with(['rata' => function($q) {
                $q->select('id', 'data_scadenza', 'numero_rata'); 
            }])
            ->get();

        // 1. Calcolo Globale (Ordinamento cronologico fondamentale)
        $rateAggregate = $tutteLeQuote->groupBy('rata_id')->map(function ($quotes) {
            $rata = $quotes->first()->rata;
            return [
                'rata_id' => $rata->id,
                'numero_rata' => $rata->numero_rata,
                'scadenza' => $rata->data_scadenza,
                'importo_netto' => $quotes->sum('importo'),
                'pagato' => $quotes->sum('importo_pagato')
            ];
        })->sortBy('scadenza');

        // VARIABILI DI STATO (Si parte da zero!)
        $creditoDisponibile = 0; 
        $accumuloDebito = 0;
        $rataStatus = [];

        // --- NESSUNA FASE A: Il calcolo è ora puramente sequenziale ---

        // FASE B: Waterfall Sequenziale
        foreach ($rateAggregate as $rata) {
            $netto = $rata['importo_netto'];
            $id = $rata['rata_id'];
            
            // FOTOGRAFIA: Quanto credito ho accumulato PRIMA di questa rata?
            // Per la Rata 1, questo sarà 0. Ed è CORRETTO.
            // Il frontend sommerà 0 al Saldo Iniziale del DB (-100), risultato: -100.
            $creditoInizialeSnapshot = $creditoDisponibile; 

            $residuoFinale = 0; 

            if ($netto > 0) {
                // --- CASO RATA A DEBITO (da pagare) ---
                $daPagareReale = max(0, $netto - $rata['pagato']);
                $creditoUsatoQui = 0;
                
                if ($creditoDisponibile >= $daPagareReale) {
                    // Coperta Totalmente
                    $creditoUsatoQui = $daPagareReale;
                    $creditoDisponibile -= $daPagareReale; // Scalo dal portafoglio
                    $residuoFinale = 0;
                    
                    $rataStatus[$id] = [
                        'residuo_reale' => 0,
                        'is_covered_by_credit' => true,
                        'credito_disponibile_start' => $creditoInizialeSnapshot,
                        'numero_rata' => $rata['numero_rata'],
                        'arretrati_pregressi' => $accumuloDebito
                    ];
                } else {
                    // Coperta Parzialmente
                    $creditoUsatoQui = $creditoDisponibile;
                    $residuoFinale = max(0, $daPagareReale - $creditoDisponibile);
                    $creditoDisponibile = 0; // Portafoglio vuoto
                    
                    $rataStatus[$id] = [
                        'residuo_reale' => $residuoFinale,
                        'is_covered_by_credit' => ($creditoUsatoQui > 0 && $residuoFinale < 0.01), 
                        'credito_disponibile_start' => $creditoInizialeSnapshot,
                        'numero_rata' => $rata['numero_rata'],
                        'arretrati_pregressi' => $accumuloDebito
                    ];
                }
            } else {
                // --- CASO RATA A CREDITO (Rata 1 o conguagli) ---
                // Questa rata NON consuma credito, lo GENERA.
                // Quindi $creditoInizialeSnapshot qui è quello delle rate precedenti (0 per la Rata 1).
                
                // Aggiungiamo il surplus al portafoglio per le rate SUCCESSIVE
                $creditoDisponibile += abs($netto);

                $rataStatus[$id] = [
                    'residuo_reale' => $netto, 
                    'is_covered_by_credit' => false,
                    'credito_disponibile_start' => $creditoInizialeSnapshot, // Sarà 0 per Rata 1
                    'numero_rata' => $rata['numero_rata'],
                    'arretrati_pregressi' => $accumuloDebito
                ];
            }

            // Aggiornamento Zainetto Debiti
            if ($residuoFinale > 0.01) {
                $accumuloDebito += $residuoFinale;
            }
        }

        // 2. Iniezione Dati nell'Evento (Invariato)
        return $events->map(function ($event) use ($rataStatus) {
            $meta = $event->meta;
            if (is_string($meta)) $meta = json_decode($meta, true);
            if (!is_array($meta)) $meta = [];

            $rataId = $meta['context']['rata_id'] ?? ($meta['rata_id'] ?? null);
            if (!$rataId && !empty($meta['dettaglio_quote'][0]['rata_id'])) {
                $rataId = $meta['dettaglio_quote'][0]['rata_id'];
            }

            if ($rataId && isset($rataStatus[$rataId])) {
                $status = $rataStatus[$rataId];
                
                $meta['importo_restante'] = $status['residuo_reale'];
                $meta['is_covered_by_credit'] = $status['is_covered_by_credit'];
                $meta['arretrati_pregressi'] = $status['arretrati_pregressi'];

                if (!empty($meta['dettaglio_quote'])) {
                    foreach ($meta['dettaglio_quote'] as $k => $quota) {
                        // Fix 1: Reset saldo visuale per rate successive alla 1
                        if ($status['numero_rata'] > 1 && isset($quota['audit']['saldo_usato'])) {
                            $meta['dettaglio_quote'][$k]['audit']['saldo_usato'] = 0;
                        }

                        // Fix 2: Iniezione Waterfall
                        // Se snapshot > 0, lo iniettiamo. Per Rata 1 sarà 0, quindi non inietta nulla.
                        if ($k === 0 && $status['credito_disponibile_start'] > 0.01) {
                            $meta['dettaglio_quote'][$k]['audit']['credito_pregresso_usato'] = -$status['credito_disponibile_start'];
                        }
                    }
                }
                $event->setAttribute('meta', $meta);
            }
            return $event;
        });
    }
}