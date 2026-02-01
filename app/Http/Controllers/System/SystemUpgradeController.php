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
use Illuminate\Support\Facades\Cache;

class SystemUpgradeController extends Controller
{
    /**
     * Dashboard aggiornamenti
     */
    public function index(UpdateService $service)
    {
        // GATE: Verifica se auto-update è abilitato
        if (!$service->isAutoUpdateEnabled()) {
            return Inertia::render('system/upgrade/Disabled', [
                'reason' => 'manual_installation',
                'message' => 'Gli aggiornamenti automatici non sono disponibili per installazioni manuali. Per aggiornare, segui la procedura manuale.'
            ]);
        }

        return Inertia::render('system/upgrade/Index', [
            'currentVersion' => config('app.version'),
            'availableRelease' => $service->checkRemoteVersion(),
            'inProgress' => $service->isUpgradeInProgress()
        ]);
    }

    /**
     * Lancio aggiornamento
     */
    public function launch(UpdateService $service)
    {
        // GATE: Verifica auto-update abilitato
        if (!$service->isAutoUpdateEnabled()) {
            return back()->withErrors([
                'msg' => 'Gli aggiornamenti automatici non sono disponibili. Usa la procedura manuale.'
            ]);
        }

        $release = $service->checkRemoteVersion();
        
        if (!$release) {
            return back()->withErrors(['msg' => 'Nessun aggiornamento disponibile.']);
        }

        try {
            $bridge = $service->prepareForUpgrade($release);
            
            return Inertia::render('system/upgrade/Launch', [
                'actionUrl' => url('/index.php'),
                'token' => $bridge['token'],
                'version' => $release['version']
            ]);

        } catch (\Exception $e) {
            Log::error('Upgrade launch failed', [
                'error' => $e->getMessage(),
                'version' => $release['version'] ?? 'unknown'
            ]);
            
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Conferma post-aggiornamento
     */
    public function confirm(GeneralSettings $settings)
    {
        $dbVersion = $settings->version ?? '0.0.0';
        $fileVersion = config('app.version');

        return Inertia::render('system/upgrade/Confirm', [
            'currentVersion' => $dbVersion,
            'newVersion' => $fileVersion,
            'needsUpgrade' => version_compare($fileVersion, $dbVersion, '>'),
        ]);
    }

    /**
     * Finalizzazione aggiornamento
     */
    public function run() 
    {
        try {
            Log::info('Upgrade finalization started');

            // 1. Migrazioni con retry logic
            $this->runMigrationsWithRetry();
            
            // 2. Aggiornamento versione DB
            $settings = app(GeneralSettings::class);
            $settings->version = config('app.version');
            $settings->save();

            // 3. Cache clearing
            Artisan::call('optimize:clear'); 
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            // 4. INVALIDA CACHE MIDDLEWARE
            Cache::forget('system.needs_upgrade');
            
            // AGGIUNTO: Pulisci anche cache aggiornamenti
            $updateService = app(UpdateService::class);
            $updateService->clearUpdateCache();

            Log::info('Upgrade middleware cache invalidated');

            // 4. Storage link
            $this->ensureStorageLink();

            // 5. Cleanup
            $this->cleanupInstallerJunk();
            $this->cleanupOldBackups();

            Log::info('Upgrade completed successfully', [
                'version' => config('app.version')
            ]);

            return Redirect::route('system.upgrade.changelog')
                ->with('success', 'Sistema aggiornato con successo!');

        } catch (\Exception $e) {
            Log::error('Upgrade finalization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Redirect::back()
                ->withErrors(['msg' => 'Errore durante la finalizzazione: ' . $e->getMessage()]);
        }
    }

    /**
     * Changelog
     */
    public function showChangelog(GeneralSettings $settings)
    {
        return Inertia::render('system/upgrade/Changelog', [
            'log' => $this->getChangelog($settings)
        ]);
    }

    /**
     * HELPERS
     */
    
    private function runMigrationsWithRetry(int $maxAttempts = 3): void
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                Log::info("Migrations completed on attempt " . ($attempts + 1));
                return;
                
            } catch (\Exception $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    throw new \Exception("Migration failed after {$maxAttempts} attempts: " . $e->getMessage());
                }
                
                Log::warning("Migration attempt {$attempts} failed, retrying...", [
                    'error' => $e->getMessage()
                ]);
                
                sleep(2);
            }
        }
    }

    private function ensureStorageLink(): void
    {
        $target = storage_path('app/public');
        $link = public_path('storage');

        if (!file_exists($link)) {
            if (@symlink($target, $link)) {
                Log::info('Storage symlink created');
            } else {
                Log::warning('Failed to create storage symlink - check permissions');
            }
        }
    }

    private function cleanupInstallerJunk(): void
    {
        $paths = [
            base_path('index.php'),
            public_path('index.php')
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $content = @file_get_contents($path);
                // Cerca la firma del bridge o il comando di autodistruzione
                if (strpos($content, '410 Gone') !== false || strpos($content, 'Bridge-Only') !== false) {
                    @unlink($path);
                    Log::info('Installer junk removed: ' . $path);
                }
            }
        }
    }

    private function getChangelog(GeneralSettings $settings): array
    {
        $version = config('app.version');
        $lang = $settings->language ?? 'it';
        
        $path = resource_path("data/changelogs/{$lang}/{$version}.json");

        if (!file_exists($path)) {
            $path = resource_path("data/changelogs/it/{$version}.json");
        }

        if (!file_exists($path)) {
            return [
                'date' => date('d/m/Y'), 
                'version' => $version,   
                'features' => ['Aggiornamento di sistema completato.'],
            ];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function cleanupOldBackups(): void
    {
        try {
            $backups = glob(base_path('_km_safe_zone*'));
            
            foreach ($backups as $dir) {
                if (is_dir($dir) && (time() - filemtime($dir) > 86400)) {
                    File::deleteDirectory($dir);
                    Log::info('Old backup removed', ['path' => basename($dir)]);
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Backup cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}