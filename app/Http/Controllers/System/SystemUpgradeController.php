<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Settings\GeneralSettings;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class SystemUpgradeController extends Controller
{
    /**
     * DASHBOARD: Mostra stato attuale e aggiornamenti disponibili.
     */
    public function index(UpdateService $service)
    {
        return Inertia::render('system/upgrade/Index', [
            'currentVersion' => config('app.version'),
            'availableRelease' => $service->checkRemoteVersion(),
            'inProgress' => $service->isUpgradeInProgress()
        ]);
    }

    /**
     * LANCIO: Prepara il bridge, copia l'installer e reindirizza.
     */
    public function launch(UpdateService $service)
    {
        $release = $service->checkRemoteVersion();
        if (!$release) {
            return back()->withErrors(['msg' => 'Nessun aggiornamento disponibile.']);
        }

        try {
            // Prepara il token e copia index.php nella root
            $bridge = $service->prepareForUpgrade($release);
            
            return Inertia::render('system/upgrade/Launch', [
                'actionUrl' => url('/index.php'), // Punta all'installer appena copiato
                'token' => $bridge['token'],
                'version' => $release['version']
            ]);

        } catch (\Exception $e) {
            Log::error("Upgrade Launch Error: " . $e->getMessage());
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * LANDING PAGE: Atterraggio dopo che l'installer ha finito (o l'utente è stato reindirizzato).
     */
    public function confirm(GeneralSettings $settings)
    {
        // Rileggiamo la versione dal file config (che è stato appena sovrascritto dall'installer)
        // vs la versione salvata nel DB (che è ancora vecchia)
        $dbVersion = $settings->version ?? '0.0.0';
        $fileVersion = config('app.version');

        return Inertia::render('system/upgrade/Confirm', [
            'currentVersion' => $dbVersion,
            'newVersion'     => $fileVersion,
            'needsUpgrade'   => version_compare($fileVersion, $dbVersion, '>'),
        ]);
    }

    /**
     * ESECUZIONE FINALE: Migrazioni, Pulizia e Aggiornamento DB.
     */
    public function run() 
    {
        try {
            Log::info('Upgrade: Fase finale (DB & Cleanup) avviata.');

            // 1. RETRY LOGIC MIGRAZIONI (Cruciale per Shared Hosting/Windows)
            // Tenta 3 volte in caso di file lock sul database SQLite o MySQL busy
            $attempts = 0;
            while ($attempts < 3) {
                try {
                    Artisan::call('migrate', ['--force' => true]);
                    break; // Successo
                } catch (\Exception $e) {
                    $attempts++;
                    Log::warning("Migrazione fallita (tentativo $attempts): " . $e->getMessage());
                    if ($attempts >= 3) throw $e; // Se fallisce 3 volte, errore reale
                    sleep(2); // Attendi 2 secondi prima di riprovare
                }
            }
            
            // 2. AGGIORNAMENTO VERSIONE NEL DB
            $settings = app(GeneralSettings::class);
            $settings->version = config('app.version');
            $settings->save();

            // 3. PULIZIA CACHE & OTTIMIZZAZIONE
            Artisan::call('optimize:clear'); 
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            // 4. STORAGE LINK NATIVO (Fix per Shared Hosting)
            // Artisan::call('storage:link') spesso fallisce su cPanel. Usiamo PHP nativo.
            $target = storage_path('app/public');
            $link = public_path('storage');

            if (!file_exists($link)) {
                if (!@symlink($target, $link)) {
                    Log::warning('Impossibile creare symlink storage nativo. Verificare permessi.');
                }
            }

            // 5. PULIZIA JUNK INSTALLER
            // Se l'installer non è riuscito a cancellarsi ma si è svuotato (Junk Mode), lo eliminiamo ora.
            $installerPath = base_path('index.php');
            if (file_exists($installerPath)) {
                $content = @file_get_contents($installerPath);
                // Cancella solo se è il nostro file junk o un file vuoto
                if (strpos($content, '410 Gone') !== false || strlen($content) < 100) {
                    @unlink($installerPath);
                    Log::info('Cleanup: Installer residuo rimosso.');
                }
            }

            // 6. PULIZIA VECCHI BACKUP
            $this->cleanupOldBackups();

            return Redirect::route('system.upgrade.changelog')->with('success', 'Sistema aggiornato con successo!');

        } catch (\Exception $e) {
            Log::error('Upgrade Finalize Error: ' . $e->getMessage());
            return Redirect::back()->withErrors(['msg' => 'Errore durante la finalizzazione: ' . $e->getMessage()]);
        }
    }

    /**
     * MOSTRA CHANGELOG
     */
    public function showChangelog(GeneralSettings $settings)
    {
        return Inertia::render('system/upgrade/Changelog', [
            'log' => $this->getChangelog($settings)
        ]);
    }

    /**
     * HELPER: Carica il changelog JSON
     */
    private function getChangelog(GeneralSettings $settings): array
    {
        $version = config('app.version');
        $lang = $settings->language ?? 'it';
        
        $path = resource_path("data/changelogs/{$lang}/{$version}.json");

        if (!file_exists($path)) {
            // Fallback italiano
            $path = resource_path("data/changelogs/it/{$version}.json");
        }

        if (!file_exists($path)) {
            return [
                'date'     => date('d/m/Y'), 
                'version'  => $version,   
                'features' => ['Aggiornamento di sistema completato.'],
            ];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * HELPER: Rimuove i backup vecchi di 24 ore
     */
    private function cleanupOldBackups()
    {
        try {
            $backups = glob(base_path('_km_safe_zone*')); // Cerca sia la cartella base che eventuali timestamp
            foreach ($backups as $dir) {
                if (is_dir($dir) && (time() - filemtime($dir) > 86400)) { // 24 ore
                    File::deleteDirectory($dir);
                    Log::info("Cleanup: Rimosso backup vecchio: " . basename($dir));
                }
            }
        } catch (\Exception $e) {
            // Non bloccare l'aggiornamento per questo
            Log::warning("Cleanup Error: " . $e->getMessage());
        }
    }
}