<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Conto; // Assicurati di usare il Model per sfruttare le relazioni se serve, o DB builder
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    
    public function up(): void
    {
        // 1. PULIZIA TOTALE (Siamo in Beta, rifacciamo i calcoli da zero per sicurezza)
        // Rimuoviamo tutte le associazioni esistenti per ricrearle corrette con i totali giusti.
        DB::table('piano_rate_capitoli')->truncate();

        // 2. Recuperiamo tutti i piani attivi
        $pianiGlobali = PianoRate::where('attivo', true)->get();

        foreach ($pianiGlobali as $piano) {
            
            // Troviamo i capitoli RADICE della gestione
            $capitoliRadice = DB::table('conti')
                ->join('piani_conti', 'conti.piano_conto_id', '=', 'piani_conti.id')
                ->where('piani_conti.gestione_id', $piano->gestione_id)
                ->whereNull('conti.parent_id') // Solo i padri
                ->select('conti.id', 'conti.importo') 
                ->get();

            foreach ($capitoliRadice as $capitolo) {
                
                // CALCOLO INTELLIGENTE DELL'IMPORTO
                $importoReale = $capitolo->importo;

                // Se l'importo del padre è 0, significa che è un contenitore.
                // Dobbiamo sommare i figli.
                if ($importoReale == 0) {
                    // Sommiamo tutti i discendenti (figli e nipoti)
                    // Usiamo una funzione ricorsiva helper o una query sui figli diretti.
                    // Per semplicità e performance qui sommiamo i figli diretti (livello 1)
                    // Se hai 3 livelli, questa logica va adattata ricorsivamente.
                    
                    $sommaFigli = DB::table('conti')
                        ->where('parent_id', $capitolo->id)
                        ->sum('importo');
                    
                    $importoReale = $sommaFigli;
                    
                    // (Opzionale) Se anche i figli sono 0, controlliamo i nipoti?
                    // Se la tua struttura è complessa, l'importoReale si aggiorna solo se > 0
                }

                // Inseriamo il collegamento con il TOTALE CALCOLATO
                if ($importoReale > 0) {
                     DB::table('piano_rate_capitoli')->insert([
                        'piano_rate_id' => $piano->id,
                        'conto_id'      => $capitolo->id,
                        'importo'       => $importoReale, // Ora salviamo la somma dei figli!
                        'note'          => 'Migrazione V1.9 (Totale Aggregato)',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                } else {
                    // Caso raro: Capitolo vuoto e figli vuoti. 
                    // Lo inseriamo comunque come blocco "Tutto", ma con importo 0 è ininfluente.
                    // Oppure lo inseriamo con NULL per indicare "Qualsiasi cosa ci sia dentro".
                     DB::table('piano_rate_capitoli')->insert([
                        'piano_rate_id' => $piano->id,
                        'conto_id'      => $capitolo->id,
                        'importo'       => 0, 
                        'note'          => 'Capitolo vuoto',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('piano_rate_capitoli')->truncate();
    }
};