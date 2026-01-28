<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // 1. Sempre per primo: Sincronizza Ruoli e Permessi (è già sicuro grazie a updateOrCreate)
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. UserSeeder: Lo eseguiamo SOLO se non esiste nessun amministratore
        // Questo permette all'admin di cambiare email/password senza essere sovrascritto
        if (User::role(Role::AMMINISTRATORE->value)->count() === 0) {
            $this->call(UserSeeder::class);
        }

        // 3. Tabelle Master: Le facciamo girare sempre per assicurarci che 
        // le categorie siano aggiornate (Richiede modifiche ai singoli seeder sotto)
        $this->call([          
            CategoriaDocumentoSeeder::class,
            CategoriaEventoSeeder::class,
            TipologieImmobiliSeeder::class,
            CategoriaFornitoreSeeder::class,
        ]);

    }
}
