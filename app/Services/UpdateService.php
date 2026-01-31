<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class UpdateService
{
    private const UPDATE_URL = 'https://kondomanager.com/packages/latest.json';
    private const BRIDGE_FILE = 'update_bridge.json';
    
    // La "Matrice" dell'installer che abbiamo appena creato
    private const MASTER_PATH = 'resources/installer/index.php'; 

    /**
     * Verifica la presenza di aggiornamenti remoti.
     * Mantiene la tua logica Beta vs Stable.
     */
    public function checkRemoteVersion(): ?array
    {
        try {
            $response = Http::timeout(5)->get(self::UPDATE_URL);
            if (!$response->successful()) return null;

            $data = $response->json();
            
            $current = config('app.version');
            $latest = $data['latest_stable'] ?? null;
            $beta = $data['latest_beta'] ?? null;

            $target = $latest;
            
            // Logica Beta: Se c'è una beta più nuova della stabile E della corrente
            if ($beta && version_compare($beta, $latest, '>') && version_compare($beta, $current, '>')) {
                $target = $beta;
            } elseif ($latest && version_compare($latest, $current, '<=')) {
                return null; // Nessun aggiornamento necessario
            }

            // Trova la release nell'array
            return collect($data['releases'] ?? [])->firstWhere('version', $target);

        } catch (\Exception $e) {
            Log::warning('Update check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Prepara il sistema per l'aggiornamento:
     * 1. Crea il file Ponte (Bridge)
     * 2. Copia l'installer dalla tana (resources) alla root
     */
    public function prepareForUpgrade(array $release): array
    {
        // 1. Validazione Hash (Critico per il nuovo installer v10+)
        if (empty($release['hash'])) {
            throw new \Exception("Hash di sicurezza mancante per la versione " . $release['version']);
        }

        $token = bin2hex(random_bytes(32));
        
        $bridgeData = [
            'security' => [
                'token' => $token,
                'expires_at' => time() + 600, // 10 minuti di validità
                'initiated_by' => auth()->id(),
            ],
            'package' => [
                'version' => $release['version'],
                'url' => $release['url'],
                'hash' => $release['hash'], // Passiamo l'hash all'installer
                'exclude' => $release['exclude'] ?? ['.env', 'storage', 'public/uploads']
            ],
            'instructions' => [
                // Dove andare quando l'installer si autodistrugge
                'post_update_redirect' => route('system.upgrade.confirm') 
            ]
        ];

        // 2. Scrittura Bridge (0644 per compatibilità Shared Hosting)
        File::put(base_path(self::BRIDGE_FILE), json_encode($bridgeData, JSON_PRETTY_PRINT));
        chmod(base_path(self::BRIDGE_FILE), 0644);

        // 3. ATTIVAZIONE INSTALLER (La parte nuova)
        $this->activateInstaller();

        return ['token' => $token];
    }
    
    /**
     * Copia il file index.php "Master" nella root per prendere il controllo
     */
    private function activateInstaller(): void
    {
        $master = base_path(self::MASTER_PATH);
        $target = base_path('index.php');

        if (File::exists($master)) {
            File::copy($master, $target);
            // 0644 è lo standard per i file PHP su cPanel/Shared Hosting (suPHP/FastCGI)
            // 0755 usalo solo se il server richiede esplicitamente bit di esecuzione, 
            // ma solitamente 0644 è più sicuro e corretto per index.php
            chmod($target, 0644); 
            Log::info("UpdateService: Installer attivato in root.");
        } else {
            throw new \Exception("Impossibile trovare l'installer master in: " . self::MASTER_PATH);
        }
    }
    
    /**
     * Verifica se un aggiornamento è in corso.
     * Controlla sia il lock file (se l'installer sta girando) che il bridge file.
     */
    public function isUpgradeInProgress(): bool
    {
        // Se c'è il bridge, siamo pronti a partire
        if (File::exists(base_path(self::BRIDGE_FILE))) return true;

        // Se c'è un lock file temporaneo, l'installer sta lavorando
        return !empty(glob(sys_get_temp_dir() . '/km_lock_*.lock'));
    }
}