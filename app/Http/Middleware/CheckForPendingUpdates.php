<?php

namespace App\Http\Middleware;

use Closure;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckForPendingUpdates
{
    public function handle($request, Closure $next)
    {
        // 1. Escludi rotte di upgrade
        if ($request->is('system/upgrade*')) {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->hasRole('amministratore')) {
            
            // 2. PULISCI CONFIG CACHE se esiste (caso produzione post-estrazione ZIP)
            // Questo assicura che config('app.version') legga il NUOVO valore
            if (file_exists(base_path('bootstrap/cache/config.php'))) {
                try {
                    @unlink(base_path('bootstrap/cache/config.php'));
                } catch (\Exception $e) {
                    // Ignora errori di permessi
                }
            }
            
            // 3. CONTROLLO: Tabella settings di Spatie esiste?
            if (!Schema::hasTable('settings')) {
                return redirect()->route('system.upgrade.confirm');
            }

            // 4. CONTROLLO: Esiste il record general.version?
            $versionExists = DB::table('settings')
                ->where('group', 'general')
                ->where('name', 'version')
                ->exists();

            if (!$versionExists) {
                // Caso: upgrade da 1.8beta (senza version) a 1.8beta2
                return redirect()->route('system.upgrade.confirm');
            }

            // 5. Carica settings (ora siamo sicuri che version esiste)
            try {
                $settings = app(GeneralSettings::class);
            } catch (\Exception $e) {
                // Se Spatie Settings ha problemi a caricare
                return redirect()->route('system.upgrade.confirm');
            }
            
            // 6. CONTROLLO VERSIONE: Confronto semantico
            if (empty($settings->version) || 
                version_compare(config('app.version'), $settings->version, '>')) {
                return redirect()->route('system.upgrade.confirm');
            }
        }

        return $next($request);
    }
}