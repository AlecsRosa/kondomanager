<?php

use App\Models\User;
use App\Models\Condominio;
use App\Models\Esercizio;
use App\Models\Gestione;
use App\Models\Gestionale\PianoConto;
use App\Models\Gestionale\Conto;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestionale\Rata;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function setupFinancialScenario($preventivo, $pianificato, $connesso = true) {
    // 1. Permessi minimi per evitare crash del layout
    Permission::firstOrCreate(['name' => 'Accesso pannello amministratore', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('Accesso pannello amministratore');
    
    // 2. Creiamo il condominio
    $condominio = Condominio::factory()->create();
    
    // 3. Esercizio: Date pazzescamente ampie per non sbagliare mai
    $esercizio = Esercizio::factory()->create([
        'condominio_id' => $condominio->id,
        'stato' => 'aperto', 
        'data_inizio' => '1900-01-01', 
        'data_fine' => '2100-12-31',
    ]);

    $gestione = Gestione::factory()->create(['condominio_id' => $condominio->id]);
    $esercizio->gestioni()->attach($gestione->id, ['attiva' => true]);

    $pianoConti = PianoConto::factory()->create([
        'gestione_id' => $gestione->id, 
        'condominio_id' => $condominio->id
    ]);

    $padre = Conto::factory()->create([
        'piano_conto_id' => $pianoConti->id,
        'parent_id' => null,
        'importo' => 0 
    ]);

    Conto::factory()->create([
        'piano_conto_id' => $pianoConti->id,
        'parent_id' => $padre->id,
        'importo' => $preventivo 
    ]);

    $pianoRate = PianoRate::factory()->create(['gestione_id' => $gestione->id]);
    if ($connesso) { $pianoRate->capitoli()->attach($padre->id); }

    Rata::factory()->create(['piano_rate_id' => $pianoRate->id, 'importo_totale' => $pianificato]);

    return compact('user', 'condominio');
}

test('dashboard calcola correttamente un deficit di budget', function () {
    $data = setupFinancialScenario(100000, 80000);
    
    // Proviamo a non usare withoutMiddleware() ma a dare i permessi,
    // se fallisce rimetti withoutMiddleware()
    $this->actingAs($data['user'])
        ->get("/admin/gestionale/{$data['condominio']->id}")
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('copertura', fn ($json) => $json
                ->where('preventivo', 100000)
                ->where('pianificato', 80000)
                ->where('delta', 20000)
                ->etc()
            )
        );
});

test('dashboard risulta allineata', function () {
    $data = setupFinancialScenario(50000, 50000);

    $this->actingAs($data['user'])
        ->get("/admin/gestionale/{$data['condominio']->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('copertura.is_completo', true)
        );
});