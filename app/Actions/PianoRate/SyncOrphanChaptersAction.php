<?php

namespace App\Actions\PianoRate;

use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Conto;

class SyncOrphanChaptersAction
{
    /**
     * Sincronizza capitoli orfani specifici al piano rate.
     * Garantisce che i capitoli non siano già impegnati altrove.
     * * @param PianoRate $pianoRate
     * @param array $specificIds Array di IDs di capitoli da sincronizzare (opzionale)
     * @return int Numero di capitoli sincronizzati
     */
    public function execute(PianoRate $pianoRate, array $specificIds = []): int
    {
        // Costruiamo la query usando Eloquent per sfruttare le relazioni
        $query = Conto::query()
            // 1. Deve appartenere al Piano dei Conti della gestione corrente
            ->where('piano_conto_id', function($q) use ($pianoRate) {
                $q->select('id')
                  ->from('piani_conti')
                  ->where('gestione_id', $pianoRate->gestione_id);
            })
            // 2. Deve essere un capitolo radice (non sottoconto)
            ->whereNull('parent_id')
            // 3. NON deve essere associato a NESSUN piano rate attivo (Global Orphan Check)
            // Questo previene il Double Billing
            ->whereDoesntHave('pianiRate', function($q) {
                $q->where('piani_rate.attivo', true);
            });

        // 4. Se l'utente ha selezionato specifici ID, filtriamo anche per quelli
        if (!empty($specificIds)) {
            $query->whereIn('id', $specificIds);
        }

        $idsDaInserire = $query->pluck('id');

        if ($idsDaInserire->isEmpty()) {
            return 0;
        }

        // 5. Inserimento sicuro nella pivot
        $pianoRate->capitoli()->syncWithoutDetaching($idsDaInserire);

        return $idsDaInserire->count();
    }
}