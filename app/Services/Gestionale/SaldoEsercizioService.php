<?php

namespace App\Services\Gestionale;

use App\Models\Condominio;
use App\Models\Esercizio;
use App\Models\Gestione;
use App\Helpers\MoneyHelper; 
use Illuminate\Support\Facades\DB;

class SaldoEsercizioService
{
    public function calcolaSaldoApplicabile(
        Condominio $condominio, 
        Esercizio $esercizio, 
        ?int $anagraficaId = null
    ): array {
        
        // 1. Verifica Blocco
        $gestioneConSaldo = Gestione::where('condominio_id', $condominio->id)
            ->whereHas('esercizi', fn($q) => $q->where('esercizio_id', $esercizio->id))
            ->where('saldo_applicato', true)
            ->first();
        
        if ($gestioneConSaldo) {
            return [
                'saldo' => 0,
                'has_movimenti' => false,
                'applicabile' => false,
                'motivo' => "Il saldo è già stato applicato alla gestione \"{$gestioneConSaldo->nome}\".",
            ];
        }
        
        $saldoInCentesimi = 0;
        $haMovimenti = false; 
        $motivo = "";
        $isPrimoAnno = false;

        // 2. TENTATIVO A: Esercizio precedente CHIUSO
        $esercizioPrecedente = Esercizio::where('condominio_id', $condominio->id)
            ->where('data_fine', '<', $esercizio->data_inizio)
            ->where('stato', 'chiuso')
            ->orderBy('data_fine', 'desc')
            ->first();
        
        if ($esercizioPrecedente) {
            $query = DB::table('rate_quote')
                ->join('rate', 'rate_quote.rata_id', '=', 'rate.id')
                ->join('piani_rate', 'rate.piano_rate_id', '=', 'piani_rate.id')
                ->join('gestioni', 'piani_rate.gestione_id', '=', 'gestioni.id')
                ->join('esercizio_gestione', 'gestioni.id', '=', 'esercizio_gestione.gestione_id')
                ->where('esercizio_gestione.esercizio_id', $esercizioPrecedente->id)
                ->where('gestioni.condominio_id', $condominio->id);

            if ($anagraficaId) {
                $query->where('rate_quote.anagrafica_id', $anagraficaId);
            }

            $saldoInCentesimi = (int) $query->sum(DB::raw('rate_quote.importo - rate_quote.importo_pagato'));
            
            // Se la somma è diversa da 0, ci sono movimenti.
            // Se è 0, controlliamo se ci sono record che si annullano.
            if ($saldoInCentesimi != 0) {
                $haMovimenti = true;
            } else {
                $haMovimenti = $query->havingRaw('SUM(rate_quote.importo - rate_quote.importo_pagato) != 0')->exists();
            }
            
            $motivo = "Saldo da esercizio chiuso {$esercizioPrecedente->nome}";
        } 
        else {
            // 3. TENTATIVO B: Saldi Manuali
            $queryManuale = DB::table('saldi')
                ->where('condominio_id', $condominio->id)
                ->where('esercizio_id', $esercizio->id);

            if ($anagraficaId) {
                $queryManuale->where('anagrafica_id', $anagraficaId);
            }

            $saldoInCentesimi = (int) $queryManuale->sum('saldo_iniziale');
            
            // Verifica fondamentale: esistono righe diverse da 0?
            $haMovimenti = (clone $queryManuale)->where('saldo_iniziale', '!=', 0)->exists();
            
            if ($haMovimenti) {
                $motivo = "Saldi iniziali manuali rilevati";
            } else {
                $motivo = "Nessun saldo precedente trovato.";
                $isPrimoAnno = true;
            }
        }

        $importoFormattato = ($saldoInCentesimi > 0 ? '+' : '') . MoneyHelper::format($saldoInCentesimi);
        
        if ($haMovimenti) {
            $soggetto = $anagraficaId ? "per te" : "totale";
            $motivo .= " ({$soggetto}: {$importoFormattato})";
        }

        return [
            'saldo' => $saldoInCentesimi,
            'has_movimenti' => $haMovimenti,
            'applicabile' => true, 
            'motivo' => $motivo,
            'is_primo_anno' => $isPrimoAnno
        ];
    }
    
    public function marcaSaldoApplicato(Gestione $gestione, int $saldoApplicato): void
    {
        $importoFormattato = ($saldoApplicato > 0 ? '+' : '') . MoneyHelper::format($saldoApplicato);

        $gestione->update([
            'saldo_applicato' => true,
            'nota_saldo' => sprintf(
                "Saldo Netto %s applicato il %s",
                $importoFormattato,
                now()->format('d/m/Y H:i')
            )
        ]);
    }
}