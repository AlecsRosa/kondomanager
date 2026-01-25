<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SystemUpgradeController extends Controller
{
    /**
     * STEP 1: Pagina di Conferma Upgrade
     */
    public function confirm(GeneralSettings $settings)
    {
        return Inertia::render('system/upgrade/Confirm', [
            'currentVersion' => $settings->version ?? 'Inizializzazione',
            'newVersion'     => config('app.version'),
        ]);
    }

    /**
     * STEP 2: Esecuzione Upgrade
     */
    public function run() 
    {
        try {

            Log::info('Inizio aggiornamento sistema');

            // 1. Esegui le migrazioni (ora la tabella settings viene aggiornata/creata)
            Artisan::call('migrate', ['--force' => true]);
            
            // 2. RECUPERA UN'ISTANZA FRESCA DOPO IL MIGRATE
            // Questo forza Spatie a ri-leggere il database appena aggiornato
            $settings = app(GeneralSettings::class);
            
            $settings->version = config('app.version');
            $settings->save();

            // 3. Pulisci tutto
            Artisan::call('optimize:clear'); 

            if (!file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }

            Log::info('Aggiornamento completato con successo');

            return Redirect::route('system.upgrade.changelog')
                ->with('success', 'Aggiornamento completato con successo!');

        } catch (\Exception $e) {

            Log::error('Errore durante l\'aggiornamento: ' . $e->getMessage());

            return Redirect::back()->withErrors([
                'msg' => 'Errore critico durante l\'aggiornamento: ' . $e->getMessage()
            ]);

        }
    }

    /**
     * STEP 3: Mostra Changelog Post-Upgrade
     */
    public function showChangelog(GeneralSettings $settings)
    {
        return Inertia::render('system/upgrade/Changelog', [
            'log' => $this->getChangelog($settings)
        ]);
    }

    /**
     * Carica i dati dal file JSON basandosi sulla lingua in GeneralSettings
     */
    private function getChangelog(GeneralSettings $settings): array
    {
        $version = config('app.version');
        $lang = $settings->language ?? 'it'; // Fallback all'italiano se nullo
        
        $path = resource_path("data/changelogs/{$lang}/{$version}.json");

        // Se il file per la lingua scelta non esiste, prova il fallback in italiano
        if (!file_exists($path)) {
            $path = resource_path("data/changelogs/it/{$version}.json");
        }

        // Se non esiste neanche il file in italiano, restituiamo un array di default
        if (!file_exists($path)) {
            return [
                'date'    => date('d/m/Y'), 
                'version' => $version,   
                'title'   => 'Aggiornamento di sistema',
                'features' => ['Novità non disponibili per questa versione.'],
            ];
        }

        $content = json_decode(file_get_contents($path), true);

        return [
            'date'     => date('d/m/Y'), 
            'version'  => $version,   
            'title'    => $content['title'] ?? 'Aggiornamento di sistema',
            'features' => $content['features'] ?? [],
        ];
    }
}