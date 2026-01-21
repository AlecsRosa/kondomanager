<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Models\Anagrafica;
use App\Models\Gestionale\RataQuote; 
use App\Helpers\MoneyHelper;
use App\Traits\HasEsercizio;
use Inertia\Inertia;
use Illuminate\Http\Request;

class EstrattoContoAnagraficaController extends Controller
{
    use HasEsercizio;

    public function show(Request $request, Condominio $condominio, Anagrafica $anagrafica)
    {
        $esercizio = $this->getEsercizioCorrente($condominio);

        $anagrafica->load(['immobili' => function($q) use ($condominio) {
            $q->where('condominio_id', $condominio->id);
        }]);

        // 1. Saldo Iniziale
        $saldoInizialeCents = $anagrafica->saldi()
            ->where('condominio_id', $condominio->id)
            ->where('esercizio_id', $esercizio->id)
            ->sum('saldo_iniziale'); 

        // 2. Recupero tutti i movimenti ordinati cronologicamente
        $movimenti = $anagrafica->movimenti()
            ->whereHas('scrittura', function($q) use ($condominio) {
                $q->where('condominio_id', $condominio->id);
            })
            ->whereNull('cassa_id')
            ->with(['scrittura.gestione', 'rata', 'immobile']) 
            ->orderBy('created_at', 'asc') 
            ->orderBy('id', 'asc')
            ->get();

        // 3. LOGICA WATERFALL GLOBALE
        // Inizializziamo il contatore con il saldo iniziale
        $runningBalance = $saldoInizialeCents;
        
        $timeline = $movimenti->map(function ($riga) use (&$runningBalance, $anagrafica) {
            $importo = $riga->importo;
            
            // Calcolo Start/End per questa specifica riga
            $waterfallStart = $runningBalance;
            
            if ($riga->tipo_riga === 'dare') {
                // Addebito: Aumenta il debito (o riduce il credito negativo)
                // Es: Start -100, Costo 33 -> End -67
                // Es: Start 0, Costo 33 -> End 33
                $runningBalance += $importo;
                $dare = $importo; $avere = 0;
            } else {
                // Pagamento: Riduce il debito (o aumenta il credito negativo)
                $runningBalance -= $importo;
                $dare = 0; $avere = $importo;
            }
            
            $waterfallEnd = $runningBalance;

            // Icone
            $tipoMovimento = $riga->scrittura->tipo_movimento ?? 'generico';
            $icona = 'file'; 
            if ($tipoMovimento === 'emissione_rata') $icona = 'bill';
            if ($tipoMovimento === 'incasso_rata') $icona = 'payment';
            if ($tipoMovimento === 'saldo_iniziale') $icona = 'landmark';

            $dettagli = [];
            $breakdown = null;

            if ($riga->rata) {
                
                // Determiniamo lo stato basandoci SUL SALDO PROGRESSIVO FINALE ($waterfallEnd)
                // Se dopo questa rata il saldo è ancora negativo (o zero), allora è coperta.
                if ($waterfallEnd <= 0) {
                    $statoRata = 'credito'; 
                } else {
                    // Se siamo a debito, controlliamo se parziale o totale
                    // Qui semplifichiamo: se è > 0 è "da pagare" (rosso), a meno che non ci siano pagamenti parziali registrati
                    // Ma per ora fidiamoci del saldo progressivo.
                    $statoRata = 'da_pagare'; 
                }

                // Costruzione Breakdown Tooltip
                // Qui uniamo la logica progressiva con i dati descrittivi
                $breakdown = [
                    'start' => MoneyHelper::fromCents($waterfallStart),
                    'cost'  => MoneyHelper::fromCents($importo),
                    'end'   => MoneyHelper::fromCents($waterfallEnd),
                    'immobile' => $riga->immobile ? $riga->immobile->interno : 'Generico'
                ];

                $label = "Rata" . ($riga->rata->numero_rata ? " n.{$riga->rata->numero_rata}" : "");
                if ($riga->rata->data_scadenza) {
                    $label .= " (Scad. " . $riga->rata->data_scadenza->format('d/m/Y') . ")";
                }
                
                $dettagli[] = [
                    'type'   => 'rata',
                    'text'   => $label,
                    'status' => $statoRata 
                ];
            }

            if ($riga->immobile) {
                $label = $riga->immobile->nome . ($riga->immobile->interno ? " (Int. {$riga->immobile->interno})" : "");
                $dettagli[] = [
                    'type' => 'immobile',
                    'text' => $label,
                    'status' => null
                ];
            }

            $descrizione = $riga->scrittura->causale ?: 'Movimento Contabile';

            return [
                'id'          => $riga->id,
                'data'        => $riga->scrittura->data_registrazione ? $riga->scrittura->data_registrazione->format('d/m/Y') : '-',
                'protocollo'  => $riga->scrittura->numero_protocollo,
                'descrizione' => $descrizione,
                'gestione'    => $riga->scrittura->gestione ? $riga->scrittura->gestione->nome : null,
                'dettagli'    => $dettagli, 
                'note'        => $riga->note, 
                'tipo_icona'  => $icona, 
                'dare'        => $dare, 
                'avere'       => $avere,
                // Passiamo il saldo calcolato progressivamente per questa riga
                'saldo'       => $waterfallEnd, 
                'breakdown'   => $breakdown 
            ];
        });

        $stats = [
            'totale_addebiti'   => MoneyHelper::format($timeline->sum('dare')),
            'totale_versamenti' => MoneyHelper::format($timeline->sum('avere')),
            'saldo_finale'      => MoneyHelper::format($runningBalance),
            'saldo_raw'         => $runningBalance,
            'saldo_iniziale'    => MoneyHelper::format($saldoInizialeCents),
            'saldo_iniziale_raw'=> $saldoInizialeCents
        ];

        return Inertia::render('gestionale/pianiRate/EstrattoContoAnagrafica', [
            'condominio' => $condominio,
            'esercizio'  => $esercizio,
            'anagrafica' => $anagrafica,
            'timeline'   => $timeline,
            'stats'      => $stats
        ]);
    }
}