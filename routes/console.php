<?php

use Illuminate\Support\Facades\Schedule;

// ============================================================================
// 1. MANUTENZIONE DATABASE (Garbage Collector)
// ============================================================================
// Esegue il pruning dei modelli (es. Eventi vecchi) ogni notte a mezzanotte.
Schedule::command('model:prune')->daily();

// ============================================================================
// 2. CONTROLLO AGGIORNAMENTI SISTEMA (Notifica Badge)
// ============================================================================
// Controlla gli aggiornamenti ogni notte alle 04:00 per non sovrapporsi al backup/prune.
Schedule::command('system:check-updates')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// ============================================================================
// 3. CONTROLLO AGGIORNAMENTI IP CRON-JOB.ORG
// ============================================================================
// Aggiornamento automatico IP di cron-job.org
Schedule::command('cronjob:update-ips')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// ============================================================================
// 4. WORKER PER HOSTING CONDIVISI (Logica "Svuota e Spegni")
// ============================================================================
// Si attiva SOLO se configurato in config/app.php.
// Fondamentale per switchare tra Supervisor (false) e Hosting Condiviso (true).
if (config('app.scheduler_queue_worker')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}