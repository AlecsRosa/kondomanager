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

        // 1. Calcolo Globale Waterfall
        $rateAggregate = $tutteLeQuote->groupBy('rata_id')->map(function ($quotes) {
            $rata = $quotes->first()->rata;
            $importoTotale = $quotes->sum('importo'); 
            $pagatoTotale = $quotes->sum('importo_pagato');
            
            return [
                'rata_id' => $rata->id,
                'numero_rata' => $rata->numero_rata, // Importante per il check Rata 1
                'scadenza' => $rata->data_scadenza,
                'importo_netto' => $importoTotale,
                'pagato' => $pagatoTotale
            ];
        })->sortBy('scadenza');

        $creditoDisponibile = 0;
        $rataStatus = [];

        // FASE A: Accumulo Credito
        foreach ($rateAggregate as $rata) {
            if ($rata['importo_netto'] < 0) {
                $creditoDisponibile += abs($rata['importo_netto']);
            }
        }

        // FASE B: Copertura Debiti e Calcolo Utilizzo Credito
        foreach ($rateAggregate as $rata) {
            $netto = $rata['importo_netto'];
            $id = $rata['rata_id'];
            
            if ($netto > 0) {
                $daPagareReale = max(0, $netto - $rata['pagato']);
                $creditoUsatoQui = 0; // Quanto credito "mangia" questa rata?
                
                if ($creditoDisponibile >= $daPagareReale) {
                    // Coperta Totalmente
                    $creditoUsatoQui = $daPagareReale;
                    $creditoDisponibile -= $daPagareReale;
                    
                    $rataStatus[$id] = [
                        'residuo_reale' => 0,
                        'is_covered_by_credit' => true,
                        'credito_usato' => $creditoUsatoQui,
                        'numero_rata' => $rata['numero_rata']
                    ];
                } else {
                    // Coperta Parzialmente
                    $creditoUsatoQui = $creditoDisponibile;
                    $residuoFinale = max(0, $daPagareReale - $creditoDisponibile);
                    $creditoDisponibile = 0;
                    
                    $rataStatus[$id] = [
                        'residuo_reale' => $residuoFinale,
                        'is_covered_by_credit' => ($creditoUsatoQui > 0 && $residuoFinale < 0.01), 
                        'credito_usato' => $creditoUsatoQui,
                        'numero_rata' => $rata['numero_rata']
                    ];
                }
            } else {
                // Rata a credito
                $rataStatus[$id] = [
                    'residuo_reale' => $netto, 
                    'is_covered_by_credit' => false,
                    'credito_usato' => 0,
                    'numero_rata' => $rata['numero_rata']
                ];
            }
        }

        // 2. Iniezione Dati nell'Evento (Fix Inconsistenza)
        return $events->map(function ($event) use ($rataStatus) {
            $meta = $event->meta;
            if (is_string($meta)) $meta = json_decode($meta, true);
            if (!is_array($meta)) $meta = [];

            $rataId = $meta['context']['rata_id'] ?? ($meta['rata_id'] ?? null);
            // Fallback ID
            if (!$rataId && !empty($meta['dettaglio_quote'][0]['rata_id'])) {
                $rataId = $meta['dettaglio_quote'][0]['rata_id'];
            }

            if ($rataId && isset($rataStatus[$rataId])) {
                $status = $rataStatus[$rataId];
                
                // A. Aggiorna Totali
                $meta['importo_restante'] = $status['residuo_reale'];
                $meta['is_covered_by_credit'] = $status['is_covered_by_credit'];

                // B. Aggiorna Dettaglio Quote (FIX AUDIT)
                if (!empty($meta['dettaglio_quote'])) {
                    foreach ($meta['dettaglio_quote'] as $k => $quota) {
                        
                        // Fix 1: Se NON è Rata 1, azzera visualizzazione saldo iniziale
                        if ($status['numero_rata'] > 1) {
                            if (isset($quota['audit']['saldo_usato'])) {
                                $meta['dettaglio_quote'][$k]['audit']['saldo_usato'] = 0;
                            }
                        }

                        // Fix 2: Se abbiamo usato credito, lo scriviamo nell'audit della prima riga
                        if ($k === 0 && $status['credito_usato'] > 0.01) {
                            // Iniettiamo il campo specifico per il frontend
                            $meta['dettaglio_quote'][$k]['audit']['credito_pregresso_usato'] = -$status['credito_usato'];
                        }
                    }
                }

                $event->setAttribute('meta', $meta);
            }

            return $event;
        });
    }
}