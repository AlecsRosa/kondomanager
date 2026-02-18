<?php

namespace App\Services\Gestionale;

use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Conto;
use App\Models\Gestionale\BudgetMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetMovementService
{
    /**
     * Esegue lo spostamento di budget tra due voci all'interno dello stesso piano rate.
     */
    public function moveBudget(PianoRate $piano, int $sourceId, int $destId, int $amount, string $reason, int $userId): BudgetMovement
    {
        // 1. Validazione di Base
        if ($sourceId === $destId) {
            throw ValidationException::withMessages(['destination_id' => 'Sorgente e destinazione devono essere diverse.']);
        }
        
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'L\'importo deve essere positivo.']);
        }

        return DB::transaction(function () use ($piano, $sourceId, $destId, $amount, $reason, $userId) {
            
            // 2. Recupero Dati Pivot (con lock per evitare race conditions)
            $sourcePivot = DB::table('piano_rate_capitoli')
                ->where('piano_rate_id', $piano->id)
                ->where('conto_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if (!$sourcePivot) {
                throw ValidationException::withMessages(['source_id' => 'La voce sorgente non è presente in questo piano rate.']);
            }

            // Calcolo importo attuale sorgente
            // Se è NULL, significa "Tutto il residuo". Dobbiamo convertirlo in numero per sottrarre.
            // Recuperiamo l'importo originale dal Conto se è NULL.
            $sourceCurrentAmount = $sourcePivot->importo;
            if (is_null($sourceCurrentAmount)) {
                $contoSource = Conto::find($sourceId);
                $sourceCurrentAmount = $contoSource->importo; // Assumiamo che copra tutto il preventivo
            }

            // 3. Check Capienza (V 1.10 - Solo competenza, V 1.11 aggiungerà Cassa)
            // TODO V1.11: Aggiungere check su 'speso' (fatture registrate) per non spostare budget già consumato.
            
            if ($sourceCurrentAmount < $amount) {
                 throw ValidationException::withMessages(['amount' => "Fondi insufficienti. Disponibili: € " . number_format($sourceCurrentAmount/100, 2)]);
            }

            // 4. Gestione Destinazione
            $destPivot = DB::table('piano_rate_capitoli')
                ->where('piano_rate_id', $piano->id)
                ->where('conto_id', $destId)
                ->lockForUpdate()
                ->first();

            $destOldAmount = $destPivot ? ($destPivot->importo ?? Conto::find($destId)->importo) : 0;

            // 5. Esecuzione Spostamento (Aggiornamento Pivot)
            
            // A. Riduci Sorgente
            DB::table('piano_rate_capitoli')
                ->where('id', $sourcePivot->id)
                ->update(['importo' => $sourceCurrentAmount - $amount, 'updated_at' => now()]);

            // B. Aumenta/Crea Destinazione
            if ($destPivot) {
                // Se destinazione aveva importo NULL (tutto), dobbiamo convertirlo in numero + extra
                $newDestAmount = $destOldAmount + $amount;
                DB::table('piano_rate_capitoli')
                    ->where('id', $destPivot->id)
                    ->update(['importo' => $newDestAmount, 'updated_at' => now()]);
            } else {
                // Creiamo la riga pivot se non esiste
                DB::table('piano_rate_capitoli')->insert([
                    'piano_rate_id' => $piano->id,
                    'conto_id' => $destId,
                    'importo' => $amount,
                    'note' => 'Generato da Sposta Spesa: ' . $reason,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // 6. Audit Log (Memoria Storica)
            // Creiamo il modello BudgetMovement (che creeremo tra poco)
            // Uso DB::table per ora per velocità se non hai il model pronto, ma meglio Model.
            $logId = DB::table('budget_movements')->insertGetId([
                'piano_rate_id' => $piano->id,
                'source_conto_id' => $sourceId,
                'destination_conto_id' => $destId,
                'user_id' => $userId,
                'amount' => $amount,
                'source_old_amount' => $sourceCurrentAmount,
                'destination_old_amount' => $destOldAmount,
                'reason' => $reason,
                'type' => 'reallocation',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return BudgetMovement::find($logId);
        });
    }
}