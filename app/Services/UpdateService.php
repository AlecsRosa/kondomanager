<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class UpdateService
{
    private const UPDATE_URL = 'https://kondomanager.com/packages/latest.json';
    private const BRIDGE_FILE = 'update_bridge.json';
    private const MASTER_PATH = 'resources/installer/index.php';

    /**
     * Verifica se gli aggiornamenti automatici sono abilitati
     */
    public function isAutoUpdateEnabled(): bool
    {
        // Verifica se l'installazione è avvenuta tramite wizard
        return config('installer.run_installer', false) === true;
    }

    /**
     * Verifica la presenza di aggiornamenti remoti
     */
    public function checkRemoteVersion(): ?array
    {
        // GATE: Solo se auto-update abilitato
        if (!$this->isAutoUpdateEnabled()) {
            Log::info('Auto-update disabled - manual installation detected');
            return null;
        }

        try {
            $response = Http::timeout(5)->get(self::UPDATE_URL);
            if (!$response->successful()) return null;

            $data = $response->json();
            
            $current = config('app.version');
            $latest = $data['latest_stable'] ?? null;
            
            if (!$latest || version_compare($latest, $current, '<=')) {
                return null;
            }

            return collect($data['releases'] ?? [])->firstWhere('version', $latest);

        } catch (\Exception $e) {
            Log::warning('Update check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Prepara il sistema per l'aggiornamento
     */
    public function prepareForUpgrade(array $release): array
    {
        // GATE: Verifica auto-update abilitato
        if (!$this->isAutoUpdateEnabled()) {
            throw new \Exception('Gli aggiornamenti automatici non sono disponibili per installazioni manuali.');
        }

        if (empty($release['hash'])) {
            throw new \Exception("Hash di sicurezza mancante per la versione " . $release['version']);
        }

        $token = bin2hex(random_bytes(32));
        
        $bridgeData = [
            'security' => [
                'token' => $token,
                'expires_at' => time() + 600,
                'initiated_by' => auth()->id() ?? 'system',
            ],
            'package' => [
                'version' => $release['version'],
                'url' => $release['url'],
                'hash' => $release['hash'],
                'exclude' => $release['exclude'] ?? [
                    '.env', 
                    'storage', 
                    'public/uploads', 
                    'public/storage',
                    'install.log', 
                    'update_bridge.json', 
                    'bootstrap/cache'
                ]
            ],
            'requirements' => $release['requirements'] ?? [
                'php' => '8.2.0',
                'extensions' => ['zip', 'curl', 'bcmath', 'xml', 'fileinfo', 'posix']
            ]
        ];

        File::put(base_path(self::BRIDGE_FILE), json_encode($bridgeData, JSON_PRETTY_PRINT));
        chmod(base_path(self::BRIDGE_FILE), 0644);

        $this->activateInstaller();

        Log::info('Update bridge created', [
            'version' => $release['version'],
            'initiated_by' => $bridgeData['security']['initiated_by']
        ]);

        return ['token' => $token];
    }

    /**
     * Copia installer master nella root
     */
    private function activateInstaller(): void
    {
        $master = base_path(self::MASTER_PATH);
        $target = base_path('index.php');

        if (!File::exists($master)) {
            throw new \Exception("Impossibile trovare l'installer master in: " . self::MASTER_PATH);
        }

        File::copy($master, $target);
        chmod($target, 0644);
        
        Log::info("Installer activated in root");
    }
    
    /**
     * Verifica se aggiornamento in corso
     */
    public function isUpgradeInProgress(): bool
    {
        if (File::exists(base_path(self::BRIDGE_FILE))) {
            return true;
        }

        return !empty(glob(sys_get_temp_dir() . '/km_lock_*.lock'));
    }
}