<?php

namespace App\Services;

use App\Models\Gestionale\PianoRate;
use App\Models\Saldo;
use Illuminate\Support\Collection;

class PianoRateQuoteService
{
    /**
     * Helper privato: Scansiona il piano per capire se include saldi.
     * Non si ferma al primo record, ma cerca attivamente un utilizzo.
     */
    private function determinaSePianoUsaSaldi(PianoRate $pianoRate): bool
    {
        // Ottimizzazione: prendiamo un campione di quote (es. 50) per non scansionare tutto il DB
        // Se in 50 anagrafiche nessuno ha un saldo usato, è molto probabile che il piano non li preveda.
        $quoteCampione = $pianoRate->rate()
            ->join('rate_quote', 'rate.id', '=', 'rate_quote.rata_id')
            ->whereNotNull('rate_quote.regole_calcolo')
            ->take(50) 
            ->pluck('rate_quote.regole_calcolo');

        foreach ($quoteCampione as $json) {
            $snapshot = json_decode($json, true);
            // Se troviamo anche solo 1 centesimo di saldo usato, ACCENDIAMO TUTTO
            if (isset($snapshot['importi']['saldo_usato']) && $snapshot['importi']['saldo_usato'] != 0) {
                return true;
            }
        }
        
        return false;
    }

    public function quotePerAnagrafica(PianoRate $pianoRate): Collection
    {
        $esercizio = $pianoRate->gestione->esercizi()->wherePivot('attiva', true)->first() 
                     ?? $pianoRate->gestione->esercizi()->first();

        // 1. Verifica se dobbiamo mostrare i saldi
        $pianoUsaSaldi = $this->determinaSePianoUsaSaldi($pianoRate);

        return $pianoRate->rate
            ->flatMap->rateQuote
            ->groupBy('anagrafica_id')
            ->map(function ($quotes) use ($pianoRate, $esercizio, $pianoUsaSaldi) {

                $anagrafica = $quotes->first()->anagrafica;
                
                // --- RECUPERO SALDO ---
                $saldoIniziale = 0;
                
                if ($pianoUsaSaldi && $esercizio) {
                    $saldoRecord = Saldo::where('esercizio_id', $esercizio->id)
                        ->where('condominio_id', $pianoRate->condominio_id)
                        ->where('anagrafica_id', $anagrafica->id)
                        ->sum('saldo_iniziale');
                    $saldoIniziale = (int) $saldoRecord;
                }
                // ----------------------

                $rate = $quotes
                    ->groupBy(fn($q) => $q->rata->numero_rata)
                    ->map(function ($q) {
                        $rata = $q->first()->rata;
                        $importo = $q->sum('importo');
                        $pagato  = $q->sum('importo_pagato');
                        
                        $stato = 'da_pagare';
                        if ($q->first()->stato === 'annullata') $stato = 'annullata';
                        elseif ($importo < 0) $stato = 'credito';
                        elseif ($pagato >= $importo && $importo > 0) $stato = 'pagata';
                        elseif ($pagato > 0 && $pagato < $importo) $stato = 'parzialmente_pagata';

                        $dataPagamento = $q->whereNotNull('data_pagamento')
                                           ->sortByDesc('data_pagamento')
                                           ->first()
                                           ?->data_pagamento;

                        return [
                            'numero'   => $rata->numero_rata,
                            'scadenza' => optional($rata->data_scadenza)->format('Y-m-d'),
                            'importo'  => $importo,
                            'importo_pagato' => $pagato,
                            'stato'          => $stato,
                            'data_pagamento' => $dataPagamento ? $dataPagamento->format('Y-m-d') : null,
                        ];
                    })
                    ->sortBy('numero')
                    ->values();

                return [
                    'anagrafica' => [
                        'id'        => $anagrafica->id,
                        'nome'      => $anagrafica->nome,
                        'indirizzo' => $anagrafica->indirizzo,
                    ],
                    // Se $pianoUsaSaldi è true, qui arriva il valore corretto (+/-)
                    // Il frontend Vue userà questo per colorare il pallino (Rosso > 0, Blu < 0)
                    'saldo_iniziale' => $saldoIniziale,
                    'rate' => $rate,
                ];
            })
            ->values();
    }

    public function quotePerImmobile(PianoRate $pianoRate): Collection
    {
        $esercizio = $pianoRate->gestione->esercizi()->wherePivot('attiva', true)->first() 
                     ?? $pianoRate->gestione->esercizi()->first();

        // 1. Verifica anche qui
        $pianoUsaSaldi = $this->determinaSePianoUsaSaldi($pianoRate);

        return $pianoRate->rate
            ->flatMap->rateQuote
            ->whereNotNull('immobile_id')
            ->groupBy('immobile_id')
            ->map(function ($quotes) use ($pianoRate, $esercizio, $pianoUsaSaldi) {

                $immobile = $quotes->first()->immobile;

                // 2. Recupero Dettagliato (Debiti vs Crediti separati)
                $totaleDebiti = 0;
                $totaleCrediti = 0;

                if ($pianoUsaSaldi && $esercizio) {
                    $saldiRecords = Saldo::where('esercizio_id', $esercizio->id)
                        ->where('condominio_id', $pianoRate->condominio_id)
                        ->where('immobile_id', $immobile->id)
                        ->get();

                    foreach ($saldiRecords as $s) {
                        if ($s->saldo_iniziale > 0) {
                            $totaleDebiti += $s->saldo_iniziale;
                        } else {
                            $totaleCrediti += $s->saldo_iniziale;
                        }
                    }
                }

                $rate = $quotes
                    ->groupBy('rata_id')
                    ->map(function ($q) {
                        $rata = $q->first()->rata;
                        $importo = $q->sum('importo');
                        $pagato = $q->sum('importo_pagato');
                        
                        $stato = 'da_pagare';
                        if ($q->first()->stato === 'annullata') $stato = 'annullata';
                        elseif ($importo < 0) $stato = 'credito';
                        elseif ($pagato >= $importo && $importo > 0) $stato = 'pagata';
                        elseif ($pagato > 0 && $pagato < $importo) $stato = 'parzialmente_pagata';

                        return [
                            'numero'   => $rata->numero_rata,
                            'scadenza' => optional($rata->data_scadenza)->format('Y-m-d'),
                            'importo'  => $importo,
                            'importo_pagato' => $pagato,
                            'stato'          => $stato,
                            'data_pagamento' => $q->sortByDesc('data_pagamento')->first()?->data_pagamento?->format('Y-m-d'),
                        ];
                    })
                    ->sortBy('numero')
                    ->values();

                return [
                    'immobile' => [
                        'id'         => $immobile->id,
                        'nome'       => $immobile->nome ?? 'Sconosciuto',
                        'interno'    => $immobile->interno,
                        'piano'      => $immobile->piano,
                        'superficie' => $immobile->superficie,
                    ],
                    // Passiamo i valori separati: il Frontend Vue li userà per mostrare
                    // i doppi pallini (Rosso e Blu) se esistono entrambi
                    'totale_debiti'  => (int) $totaleDebiti,
                    'totale_crediti' => (int) $totaleCrediti, 
                    'rate' => $rate,
                ];
            })
            ->values();
    }
}