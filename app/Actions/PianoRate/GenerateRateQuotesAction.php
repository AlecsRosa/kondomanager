<?php

namespace App\Actions\PianoRate;

use App\Enums\OrigineQuota;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Rata;
use App\Models\Gestionale\RataQuote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth; 

class GenerateRateQuotesAction
{
    public function execute(
        PianoRate $pianoRate,
        array $totaliPerImmobile,
        array $dateRate,
        array $saldi = []
    ): array {
        $numeroRate = count($dateRate);
        $rateCreate = 0;
        $quoteCreate = 0;
        $importoTotaleGenerato = 0;
        
        $now = now(); 

        foreach ($dateRate as $index => $dataScadenza) {
            $numeroRata = $index + 1;

            // 1. Creazione Rata (Testata)
            $rata = Rata::create([
                'piano_rate_id'  => $pianoRate->id,
                'numero_rata'    => $numeroRata,
                'data_scadenza'  => $dataScadenza,
                'data_emissione' => $now,
                'descrizione'    => "Rata n.{$numeroRata} - {$pianoRate->nome}",
                'importo_totale' => 0, 
                'stato'          => 'bozza',
            ]);

            $importoTotaleRata = 0;
            $quotesToInsert = []; 

            // 2. Calcolo Quote
            foreach ($totaliPerImmobile as $aid => $immobili) {
                foreach ($immobili as $iid => $totaleImmobile) {
                    if ($totaleImmobile == 0) continue;

                    // Calcolo Avanzato + Snapshot
                    $risultatoCalcolo = $this->calcolaImportoRataAvanzato(
                        $totaleImmobile, 
                        $numeroRate,
                        $numeroRata,
                        $pianoRate->metodo_distribuzione,
                        $saldi[$aid][$iid] ?? 0
                    );

                    $amount = $risultatoCalcolo['importo_finale'];
                    $snapshot = $risultatoCalcolo['snapshot'];

                    $statoQuota = $amount < 0 ? 'credito' : 'da_pagare';

                    $quotesToInsert[] = [
                        'rata_id'        => $rata->id,
                        'anagrafica_id'  => $aid,
                        'immobile_id'    => $iid,
                        'importo'        => $amount,
                        'importo_pagato' => 0,
                        'stato'          => $statoQuota,
                        // Salvataggio JSON Snapshot
                        'regole_calcolo' => json_encode($snapshot),
                        'data_scadenza'  => $dataScadenza instanceof Carbon ? $dataScadenza->format('Y-m-d') : $dataScadenza,
                        'created_at'     => $now, 
                        'updated_at'     => $now, 
                    ];

                    $importoTotaleRata += $amount;
                    $quoteCreate++;
                }
            }

            // 3. Inserimento Massivo
            if (!empty($quotesToInsert)) {
                foreach (array_chunk($quotesToInsert, 500) as $chunk) {
                    RataQuote::insert($chunk);
                }
            }

            $rata->update(['importo_totale' => $importoTotaleRata]);
            $importoTotaleGenerato += $importoTotaleRata;
            $rateCreate++;
        }

        return [
            'rate_create' => $rateCreate,
            'quote_create' => $quoteCreate,
            'importo_totale_rate' => $importoTotaleGenerato,
        ];
    }

    protected function calcolaImportoRataAvanzato(
        int $totaleImmobile,
        int $numeroRate,
        int $numeroRata,
        string $metodoDistribuzione,
        int $saldo
    ): array {
        // --- 1. Calcolo Quota Pura ---
        $segno = $totaleImmobile < 0 ? -1 : 1;
        $absTot = abs($totaleImmobile);
        $base = intdiv($absTot, $numeroRate);
        $resto = $absTot % $numeroRate;
        
        $quotaPuraRata = $base + ($numeroRata <= $resto ? 1 : 0);
        $quotaPuraRata *= $segno;

        // --- 2. Calcolo Componente Saldo ---
        $quotaSaldoApplicata = 0;
        if ($saldo !== 0) {
            if ($metodoDistribuzione === 'prima_rata') {
                if ($numeroRata === 1) {
                    $quotaSaldoApplicata = $saldo;
                }
            } elseif ($metodoDistribuzione === 'tutte_rate') {
                $segnoSaldo = $saldo < 0 ? -1 : 1;
                $absSaldo   = abs($saldo);
                $baseSaldo = intdiv($absSaldo, $numeroRate);
                $restoSaldo = $absSaldo % $numeroRate;

                $quotaSaldoApplicata = $baseSaldo + ($numeroRata <= $restoSaldo ? 1 : 0);
                $quotaSaldoApplicata *= $segnoSaldo;
            }
        }

        $importoFinale = $quotaPuraRata + $quotaSaldoApplicata;

        // --- 3. Costruzione Snapshot ---
        $snapshot = [
            'origine' => OrigineQuota::CALCOLO_AUTOMATICO->value,
            
            // Dati Finanziari (per Tooltip)
            'importi' => [
                'quota_pura_gestione' => $quotaPuraRata,
                'saldo_usato'         => $quotaSaldoApplicata,
                'totale_calcolato'    => $importoFinale
            ],
            
            // Contesto
            'parametri' => [
                'metodo_distribuzione'  => $metodoDistribuzione,
                'numero_rata'           => $numeroRata,
                'totale_rate_piano'     => $numeroRate
            ],
            
            // Audit (FIX 2: Uso config e Facade Auth)
            'audit' => [
                'versione_calcolo'  => config('app.version'), 
                'generato_il'       => now()->toIso8601String(),
                'generato_da'       => Auth::check() ? 'user_'.Auth::id() : 'sistema',
            ]
        ];

        return [
            'importo_finale' => $importoFinale,
            'snapshot'       => $snapshot
        ];
    }
}