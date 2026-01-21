<?php

namespace App\Traits;

use App\Models\Gestionale\RataQuote;

trait CalculatesFinancialWaterfall
{
    /**
     * Calcola la Waterfall finanziaria (Credito -> Debito) aggregando le quote per Rata.
     */
    public function applyFinancialWaterfall($events, $anagraficaId)
    {
        // 1. Recupero TUTTE le quote (non filtrate)
        $tutteLeQuote = RataQuote::where('anagrafica_id', $anagraficaId)
            ->whereHas('rata')
            ->with(['rata' => function($q) {
                $q->select('id', 'data_scadenza'); 
            }])
            ->get();

        // 2. RAGGRUPPAMENTO PER RATA (Somma algebrica)
        $rateAggregate = $tutteLeQuote->groupBy('rata_id')->map(function ($quotes) {
            $rata = $quotes->first()->rata;
            // Sommiamo tutto (es: -9415 + 2744 = -6671)
            $importoTotale = $quotes->sum('importo'); 
            $pagatoTotale = $quotes->sum('importo_pagato');
            
            return [
                'rata_id' => $rata->id,
                'scadenza' => $rata->data_scadenza,
                'importo_netto' => $importoTotale,
                'pagato' => $pagatoTotale
            ];
        })->sortBy('scadenza');

        // 3. CALCOLO WATERFALL
        $creditoDisponibile = 0;
        $rataStatus = [];

        // FASE A: Accumulo Credito (Rate negative)
        foreach ($rateAggregate as $rata) {
            if ($rata['importo_netto'] < 0) {
                $creditoDisponibile += abs($rata['importo_netto']);
            }
        }

        // FASE B: Copertura Debiti (Rate positive)
        foreach ($rateAggregate as $rata) {
            $netto = $rata['importo_netto'];
            
            if ($netto > 0) {
                $daPagareReale = max(0, $netto - $rata['pagato']);
                
                if ($creditoDisponibile >= $daPagareReale) {
                    // Coperta Totalmente
                    $rataStatus[$rata['rata_id']] = [
                        'residuo_reale' => 0,
                        'is_covered_by_credit' => true,
                    ];
                    $creditoDisponibile -= $daPagareReale;
                } else {
                    // Coperta Parzialmente
                    $coperto = $creditoDisponibile;
                    $residuoFinale = max(0, $daPagareReale - $coperto);
                    
                    $rataStatus[$rata['rata_id']] = [
                        'residuo_reale' => $residuoFinale,
                        // True solo se coperta praticamente del tutto (tolleranza 1 cent)
                        'is_covered_by_credit' => ($coperto > 0 && $residuoFinale < 10), 
                    ];
                    $creditoDisponibile = 0;
                }
            } else {
                // Rata a Credito (già contata nel pool)
                $rataStatus[$rata['rata_id']] = [
                    'residuo_reale' => $netto, 
                    'is_covered_by_credit' => false
                ];
            }
        }

        // 4. INIEZIONE NEGLI EVENTI
        return $events->map(function ($event) use ($rataStatus) {
            $meta = $event->meta;
            // Parsing sicuro
            if (is_string($meta)) $meta = json_decode($meta, true);
            if (!is_array($meta)) $meta = [];

            // Cerca ID Rata
            $rataId = $meta['context']['rata_id'] ?? ($meta['rata_id'] ?? null);
            if (!$rataId && !empty($meta['dettaglio_quote'][0]['rata_id'])) {
                $rataId = $meta['dettaglio_quote'][0]['rata_id'];
            }

            if ($rataId && isset($rataStatus[$rataId])) {
                $status = $rataStatus[$rataId];
                
                // Sovrascrivi
                $meta['importo_restante'] = $status['residuo_reale'];
                $meta['is_covered_by_credit'] = $status['is_covered_by_credit'];
                
                $event->setAttribute('meta', $meta);
            }

            return $event;
        });
    }
}