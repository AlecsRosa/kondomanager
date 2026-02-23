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
        
        // Copia locale dei saldi per gestirli (consumarli) man mano che li applichiamo
        $saldiResidui = $saldi;

        foreach ($dateRate as $index => $dataScadenza) {
            $numeroRata = $index + 1;

            // 1. Creazione Rata
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

                    // FIX CRITICO: Accesso corretto all'array dei saldi
                    // GenerateSaldiAction restituisce [anagrafica_id => totale]
                    // NON [anagrafica_id][immobile_id]
                    
                    $saldoDaApplicare = 0;
                    
                    // Applichiamo il saldo solo se esiste per questa anagrafica
                    if (isset($saldiResidui[$aid])) {
                        // Per evitare di applicare lo stesso saldo più volte (se ha più immobili),
                        // lo applichiamo tutto sul primo immobile che processiamo per questa anagrafica e poi lo azzeriamo.
                        // (Logica semplificata V1.9, si può raffinare per spalmarlo sugli immobili)
                        $saldoDaApplicare = $saldiResidui[$aid];
                        $saldiResidui[$aid] = 0; // Consumato
                    }

                    // Calcolo Avanzato
                    $risultatoCalcolo = $this->calcolaImportoRataAvanzato(
                        $totaleImmobile, 
                        $numeroRate,
                        $numeroRata,
                        $pianoRate->metodo_distribuzione,
                        $saldoDaApplicare // Passiamo il valore corretto
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
                        'regole_calcolo' => json_encode($snapshot),
                        'data_scadenza'  => $dataScadenza instanceof Carbon ? $dataScadenza->format('Y-m-d') : $dataScadenza,
                        'created_at'     => $now, 
                        'updated_at'     => $now, 
                    ];

                    $importoTotaleRata += $amount;
                    $quoteCreate++;
                }
            }

            // Se ci sono saldi "orfani" (cioè debiti pregressi di persone che NON hanno spese quest'anno),
            // dovremmo idealmente creare rate solo per il saldo. 
            // Per ora in V1.9 saltiamo questo caso edge (richiederebbe loop separato sui saldiResidui).

            // 3. Inserimento Massivo
            if (!empty($quotesToInsert)) {
                foreach (array_chunk($quotesToInsert, 500) as $chunk) {
                    RataQuote::insert($chunk);
                }
            }

            $rata->update(['importo_totale' => $importoTotaleRata]);
            $importoTotaleGenerato += $importoTotaleRata;
            $rateCreate++;
            
            // Reset saldi residui per le prossime rate? 
            // NO. I saldi si applicano una volta sola (Gestiti da 'metodo_distribuzione' dentro calcolaImportoRataAvanzato)
            // MA attenzione: la logica 'tutte_rate' in calcolaImportoRataAvanzato presume di ricevere il saldo TOTALE ad ogni chiamata
            // e calcola la quota parte.
            // QUINDI: Dobbiamo ripristinare $saldiResidui per la prossima rata del ciclo principale
            $saldiResidui = $saldi; 
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
                // Il saldo va tutto sulla prima rata
                if ($numeroRata === 1) {
                    $quotaSaldoApplicata = $saldo;
                }
            } elseif ($metodoDistribuzione === 'tutte_rate') {
                // Il saldo viene spalmato
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
            'importi' => [
                'quota_pura_gestione' => $quotaPuraRata,
                'saldo_usato'         => $quotaSaldoApplicata,
                'totale_calcolato'    => $importoFinale
            ],
            'parametri' => [
                'metodo_distribuzione'  => $metodoDistribuzione,
                'numero_rata'           => $numeroRata,
                'totale_rate_piano'     => $numeroRate
            ],
            'audit' => [
                'versione_calcolo'  => config('app.version', '1.9.0'), 
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