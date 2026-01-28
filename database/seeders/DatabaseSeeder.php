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

        // 2. UserSeeder: Controllo intelligente per lo UserSeeder
        // Se il wizard è disattivato (false) e non c'è un admin, lo creiamo.
        if (!config('installer.run_installer') && User::role(Role::AMMINISTRATORE->value)->count() === 0) {
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
