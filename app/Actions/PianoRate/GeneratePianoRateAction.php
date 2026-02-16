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
     * @param PianoRate $pianoRate
     * @param bool|null $forzaApplicazioneSaldi 
     * - TRUE: Applica i saldi (es. Primo piano creato).
     * - FALSE: Non applicare (es. Piano integrativo).
     * - NULL: Auto-detect basato sul DB (es. Rigenerazione futura).
     */
    public function execute(PianoRate $pianoRate, ?bool $forzaApplicazioneSaldi = null): array
    {
        Log::info("=== GENERAZIONE PIANO RATE ===");

        // 1. Caricamento Dati
        $pianoRate->load(['ricorrenza']);

        if (!$pianoRate->relationLoaded('gestione')) {
            $pianoRate->load('gestione');
        }
        $gestione = $pianoRate->gestione;

        $esercizio = $gestione->esercizi()->wherePivot('attiva', true)->first()
            ?? $gestione->esercizi()->first();

        // 2. Calcolo Spese (Quote con Override V1.9.3)
        $totaliPerImmobile = $this->calcolatore->calcolaPerGestione($gestione, $pianoRate);

        // 3. GESTIONE SALDI (Logica Ibrida: Controller + DB)
        // Recuperiamo il flag reale dal DB per sicurezza
        $flagDb = $gestione->fresh()->saldo_applicato; 
        
        $applicare = false;

        if ($forzaApplicazioneSaldi !== null) {
            // A. Il Controller comanda (Creazione Piano A o B)
            $applicare = $forzaApplicazioneSaldi;
            Log::info("Generazione: Logica Saldi forzata dal Controller -> " . ($applicare ? 'SI (Applica)' : 'NO (Ignora)'));
        } else {
            // B. Auto-Detect (Rigenerazione / Update)
            // Se non specificato, ci fidiamo del flag DB.
            // (Nota: in futuro qui servirebbe sapere se QUESTO piano specifico possiede i saldi)
            $applicare = $flagDb;
            Log::info("Generazione: Logica Saldi Auto-Detect (Flag DB: " . ($flagDb ? 'ON' : 'OFF') . ") -> " . ($applicare ? 'SI' : 'NO'));
        }

        if ($applicare) {
            $saldi = $this->saldiAction->execute($pianoRate, $gestione, $esercizio);
            Log::info("Generazione: Saldi INCLUSI (" . count($saldi) . " anagrafiche)");
        } else {
            $saldi = [];
            Log::info("Generazione: Saldi ESCLUSI (Array vuoto)");
        }

        // 4. Generazione Date
        $dateRate = $this->dateRateAction->execute($pianoRate, $gestione);

        // 5. Creazione Rate Fisiche
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