<?php

namespace App\Actions\PianoRate;

use App\Models\Gestionale\PianoRate;
use App\Services\CalcoloQuoteService;
use Illuminate\Support\Facades\Log;

class GeneratePianoRateAction
{
    public function __construct(
        private CalcoloQuoteService $calcolatore,
        private GenerateSaldiAction $saldiAction,
        private GenerateDateRateAction $dateRateAction,
        private GenerateRateQuotesAction $rateQuotesAction,
    ) {}

    /**
     * Full pipeline to generate a PianoRate.
     *
     * @return array Statistics about generation
     */
    public function execute(PianoRate $pianoRate): array
    {
        Log::info("=== GENERAZIONE PIANO RATE INIZIATA ===");

        // FIX: CARICA LA RICORRENZA E LA GESTIONE AGGIORNATA
        $pianoRate->load(['ricorrenza', 'gestione']);

        $gestione = $pianoRate->gestione;

        $esercizio = $gestione->esercizi()->wherePivot('attiva', true)->first()
            ?? $gestione->esercizi()->first();

        // ORA: Passiamo anche il piano rate per attivare il filtro
        $totaliPerImmobile = $this->calcolatore->calcolaPerGestione($gestione, $pianoRate);

        // --- [MODIFICA] SICUREZZA SALDI ---
        // Calcoliamo i saldi SOLO SE questa gestione è stata autorizzata (flag nel DB)
        // Se stiamo rigenerando una straordinaria, questo impedirà il ricalcolo dei debiti pregressi.
        if ($gestione->saldo_applicato) {
            $saldi = $this->saldiAction->execute($pianoRate, $gestione, $esercizio);
        } else {
            $saldi = []; // Nessun saldo da applicare
            Log::info("Generazione: Saldi ignorati per gestione {$gestione->id} (saldo_applicato = false)");
        }
        // ----------------------------------

        $dateRate = $this->dateRateAction->execute($pianoRate, $gestione);

        $stats = $this->rateQuotesAction->execute(
            $pianoRate,
            $totaliPerImmobile,
            $dateRate,
            $saldi
        );

        return array_merge([
            'piano_rate_id' => $pianoRate->id,
            'rate_create'   => count($dateRate),
        ], $stats);
    }
}