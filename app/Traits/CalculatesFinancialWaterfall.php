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

        // 1. Calcolo Globale
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

        // VARIABILI DI STATO
        $creditoDisponibile = 0.0; 
        $accumuloDebito = 0.0;
        $listaInsoluti = []; 
        $rataStatus = [];

        foreach ($rateAggregate as $rata) {
            $netto = $rata['importo_netto'];
            $id = $rata['rata_id'];
            
            $creditoInizialeSnapshot = $creditoDisponibile; 
            $residuoFinale = 0.0; 
            
            // FIX: Inizializziamo la variabile qui, per coprire sia if che else
            $creditoUsatoQui = 0.0;

            // --- Logica Calcolo ---
            if ($netto > 0) {
                $daPagareReale = round(max(0, $netto - $rata['pagato']), 2);

                if (round($creditoDisponibile, 2) >= $daPagareReale) {
                    $creditoUsatoQui = $daPagareReale;
                    $creditoDisponibile -= $daPagareReale;
                    $residuoFinale = 0.0;
                } else {
                    $creditoUsatoQui = $creditoDisponibile;
                    $residuoFinale = $daPagareReale - $creditoDisponibile;
                    $creditoDisponibile = 0.0;
                }
            } else {
                $creditoDisponibile += abs($netto);
                $residuoFinale = $netto; 
                // Qui $creditoUsatoQui rimane 0.0 come inizializzato sopra
            }

            // Pulizia decimali
            $residuoFinale = ($residuoFinale > 0) ? round($residuoFinale, 2) : $residuoFinale;
            $creditoDisponibile = round($creditoDisponibile, 2);

            // Costruzione Status per questa rata
            $rataStatus[$id] = [
                'residuo_reale' => $residuoFinale,
                'is_covered_by_credit' => ($creditoUsatoQui > 0.01 && $residuoFinale < 0.01), 
                'credito_disponibile_start' => $creditoInizialeSnapshot,
                'numero_rata' => $rata['numero_rata'],
                'arretrati_pregressi' => $accumuloDebito,
                'lista_rate_precedenti' => implode(', ', $listaInsoluti) 
            ];

            // Aggiornamento Accumulatori per il prossimo giro
            if ($residuoFinale > 0.01) {
                $accumuloDebito += $residuoFinale;
                $accumuloDebito = round($accumuloDebito, 2);
                $listaInsoluti[] = '#' . $rata['numero_rata']; 
            }
        }

        // 2. Iniezione
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
                $meta['rif_arretrati'] = $status['lista_rate_precedenti']; 

                if (!empty($meta['dettaglio_quote'])) {
                    foreach ($meta['dettaglio_quote'] as $k => $quota) {
                        if ($status['numero_rata'] > 1 && isset($quota['audit']['saldo_usato'])) {
                            $meta['dettaglio_quote'][$k]['audit']['saldo_usato'] = 0;
                        }
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