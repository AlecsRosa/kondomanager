<?php

namespace App\Services\Gestionale;

use App\Models\Condominio;
use App\Models\Esercizio;
use App\Models\Gestione;
use App\Helpers\MoneyHelper; 
use Illuminate\Support\Facades\DB;

class SaldoEsercizioService
{
    /**
     * Calcola il saldo disponibile dall'esercizio precedente.
     * Se $anagraficaId è null, calcola il saldo TOTALE del condominio.
     */
    public function calcolaSaldoApplicabile(
        Condominio $condominio, 
        Esercizio $esercizio, 
        ?int $anagraficaId = null
    ): array {
        // 1. Verifica se il saldo è già stato applicato (Blocco)
        $gestioneConSaldo = Gestione::where('condominio_id', $condominio->id)
            ->whereHas('esercizi', fn($q) => $q->where('esercizio_id', $esercizio->id))
            ->where('saldo_applicato', true)
            ->first();
        
        if ($gestioneConSaldo) {
            return [
                'saldo' => 0,
                'applicabile' => false,
                'motivo' => "Il saldo dell'esercizio precedente è già stato applicato alla gestione \"{$gestioneConSaldo->nome}\".",
                'gestione_utilizzatrice' => $gestioneConSaldo
            ];
        }
        
        // 2. Trova l'esercizio precedente CHIUSO
        $esercizioPrecedente = Esercizio::where('condominio_id', $condominio->id)
            ->where('data_fine', '<', $esercizio->data_inizio)
            ->where('stato', 'chiuso')
            ->orderBy('data_fine', 'desc')
            ->first();
        
        if (!$esercizioPrecedente) {
            return [
                'saldo' => 0,
                'applicabile' => true,
                'motivo' => "Nessun esercizio precedente chiuso trovato.",
                'is_primo_anno' => true
            ];
        }
        
        // 3. Calcola il saldo (Query Dinamica)
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

        // Calcolo somma (Debito residuo - Credito)
        $saldoInCentesimi = (int) $query->sum(DB::raw('rate_quote.importo - rate_quote.importo_pagato'));

        $soggetto = $anagraficaId ? "per te" : "totale condominio";
        
        // [MODIFICA] Uso MoneyHelper preservando il segno + per chiarezza
        $importoFormattato = ($saldoInCentesimi > 0 ? '+' : '') . MoneyHelper::format($saldoInCentesimi);

        return [
            'saldo' => $saldoInCentesimi,
            'applicabile' => true,
            'motivo' => "Saldo {$soggetto} dall'esercizio {$esercizioPrecedente->nome}: {$importoFormattato}",
            'esercizio_origine' => $esercizioPrecedente,
            'is_primo_anno' => false
        ];
    }
    
    public function marcaSaldoApplicato(Gestione $gestione, int $saldoApplicato): void
    {
        // [MODIFICA] Uso MoneyHelper preservando il segno +
        $importoFormattato = ($saldoApplicato > 0 ? '+' : '') . MoneyHelper::format($saldoApplicato);

        $gestione->update([
            'saldo_applicato' => true,
            'nota_saldo' => sprintf(
                "Saldo di %s applicato dall'esercizio precedente il %s",
                $importoFormattato,
                now()->format('d/m/Y H:i')
            )
        ]);
    }
    
}