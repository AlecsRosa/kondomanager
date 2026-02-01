<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
// 3. WORKER PER HOSTING CONDIVISI (Logica "Svuota e Spegni")
// ============================================================================
// Si attiva solo se configurato in config/app.php
if (config('app.scheduler_queue_worker')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55')
        ->everyMinute()
        ->withoutOverlapping();
}
