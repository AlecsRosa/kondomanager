<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Gestionale\PianoRate;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Recuperiamo tutti i piani rate attivi che non hanno ancora capitoli associati
        $pianiGlobali = PianoRate::where('attivo', true)
            ->whereDoesntHave('capitoli')
            ->get();

        foreach ($pianiGlobali as $piano) {
            // Per ogni piano, troviamo i capitoli radice della sua gestione
            $capitoliIds = DB::table('conti')
                ->where('piano_conto_id', function($q) use ($piano) {
                    $q->select('id')->from('piani_conti')
                      ->where('gestione_id', $piano->gestione_id);
                })
                ->whereNull('parent_id')
                ->pluck('id');

            if ($capitoliIds->isNotEmpty()) {
                // Li colleghiamo nella pivot
                foreach ($capitoliIds as $id) {
                    DB::table('piano_rate_capitoli')->insert([
                        'piano_rate_id' => $piano->id,
                        'conto_id' => $id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Logica di rollback se necessaria
    }
};
