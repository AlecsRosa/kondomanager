<?php

namespace App\Actions\PianoRate;

use App\Models\Esercizio;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestione;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSaldiAction
{
    /**
     * Recupera i saldi (debiti/crediti) da applicare al piano rate.
     * Restituisce un array semplice: [anagrafica_id => importo_centesimi]
     */
    public function execute(PianoRate $pianoRate, Gestione $gestione, Esercizio $esercizio): array
    {
        $condominioId = $pianoRate->condominio_id;
        $saldi = [];

        // 1. TENTATIVO A: Esercizio Precedente CHIUSO (Automatico)
        // Questo serve per il futuro: quando chiuderai il 2026, il 2027 userà questo blocco.
        $esercizioPrecedente = Esercizio::where('condominio_id', $condominioId)
            ->where('data_fine', '<', $esercizio->data_inizio)
            ->where('stato', 'chiuso')
            ->orderBy('data_fine', 'desc')
            ->first();

        if ($esercizioPrecedente) {
            Log::info("Generazione Saldi: Trovato esercizio precedente chiuso ID: {$esercizioPrecedente->id}");

            $results = DB::table('rate_quote')
                ->join('rate', 'rate_quote.rata_id', '=', 'rate.id')
                ->join('piani_rate', 'rate.piano_rate_id', '=', 'piani_rate.id')
                ->join('gestioni', 'piani_rate.gestione_id', '=', 'gestioni.id')
                ->join('esercizio_gestione', 'gestioni.id', '=', 'esercizio_gestione.gestione_id')
                ->where('esercizio_gestione.esercizio_id', $esercizioPrecedente->id)
                ->where('gestioni.condominio_id', $condominioId)
                ->select(
                    'rate_quote.anagrafica_id',
                    DB::raw('SUM(rate_quote.importo - rate_quote.importo_pagato) as saldo')
                )
                ->groupBy('rate_quote.anagrafica_id')
                ->havingRaw('SUM(rate_quote.importo - rate_quote.importo_pagato) != 0')
                ->get();

            foreach ($results as $row) {
                $saldi[$row->anagrafica_id] = (int) $row->saldo;
            }

        } else {
            // 2. TENTATIVO B: Saldi Manuali (Importazione Iniziale)
            // Questo è quello che serve ADESSO per il tuo caso.
            Log::info("Generazione Saldi: Uso saldi manuali per esercizio corrente ID: {$esercizio->id}");

            $results = DB::table('saldi')
                ->where('condominio_id', $condominioId)
                ->where('esercizio_id', $esercizio->id)
                ->select('anagrafica_id', DB::raw('SUM(saldo_iniziale) as totale'))
                ->groupBy('anagrafica_id')
                ->havingRaw('SUM(saldo_iniziale) != 0')
                ->get();

            foreach ($results as $row) {
                $saldi[$row->anagrafica_id] = (int) $row->totale;
            }
        }

        Log::info("Generazione Saldi: Totale anagrafiche con saldo da applicare: " . count($saldi));

        return $saldi;
    }
}