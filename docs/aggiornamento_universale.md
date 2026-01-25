# Sistema di aggiornamento universale "Universal Diamond"

Questo documento descrive l'architettura e la logica del sistema di versionamento e aggiornamento automatico implementato per **Kondomanager**.

## 1. Obiettivo del sistema
Evitare il crash dell'applicazione quando il codice (Controller/Middleware) richiede colonne o tabelle del database non ancora create. Il sistema gestisce il disallineamento tra i file caricati e lo stato del database, garantendo una transizione fluida verso la nuova versione.

## 2. Architettura a tre scudi 

### Scudo 1: Il Vigilante (`CheckForPendingUpdates.php`)
Middleware globale posizionato all'inizio della pipeline `web`.
- **Logica:** Confronta `config('app.version')` con `GeneralSettings->version`.
- **Protezione Cache:** Elimina forzatamente `bootstrap/cache/config.php` se presente, per assicurarsi che il server legga la versione reale dai file e non dalla cache.
- **Azione:** Se rileva un disallineamento, reindirizza l'amministratore alla rotta di upgrade.

### Scudo 2: La Protezione Civile (`HandleInertiaRequests.php`)
Middleware per la condivisione dei dati globali con Vue/Inertia.
- **Logica:** Utilizza `Schema::hasColumn('tavola', 'colonna')` prima di eseguire query su campi nuovi (es. `meta`).
- **Azione:** Se la colonna non esiste, restituisce un valore di fallback (es. `0` per i contatori) invece di lanciare un'eccezione SQL 1054.

### Scudo 3: Il Sincronizzatore (`AppServiceProvider.php`)
Listener basato su eventi di sistema.
- **Logica:** Ascolta `MigrationsEnded`.
- **Azione:** Aggiorna automaticamente `GeneralSettings->version` dopo ogni `php artisan migrate`. Questo permette l'aggiornamento manuale da terminale bypassando la procedura web.

## 3. Configurazione dei Settings (`GeneralSettings.php`)

Per evitare l'errore `MissingSettings` di Spatie, tutte le proprietà devono avere un valore di inizializzazione nel codice. Questo funge da "Piano B" se la tabella `settings` è vuota.

```php
class GeneralSettings extends Settings
{
    public bool $user_frontend_registration = false;
    public string $language = 'it'; 
    public string $version = '0.0.0'; // Default critico
}