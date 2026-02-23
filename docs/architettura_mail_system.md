# Sistema Mail Kondomanager (V 1.9)

Il sistema di gestione email di Kondomanager è progettato per essere **ambiente-agnostico**. Supporta nativamente sia hosting condivisi (tramite scheduler) che VPS professionali (tramite Supervisor).

---

## Architettura
Il sistema si basa su un **Double-Bridge Configuration**:
1. **Runtime Bridge**: Gestito da `MailConfigServiceProvider`, sovrascrive la config per le richieste HTTP immediate.
2. **Queue Bridge**: Gestito tramite l'hook `Queue::before`, ricarica i parametri dal DB un istante prima di inviare mail asincrone.

## Priorità di Configurazione
Il sistema decide quale server usare seguendo questa gerarchia di sicurezza:

1. **Database Settings**: Se `mail_enabled` è `true`, ignora il file `.env`.
2. **Environment File**: Se il DB è disattivato, usa i parametri del file `.env`.
3. **Log Driver (Safety)**: Se entrambi mancano o l'host è `127.0.0.1`, il driver viene forzato a `log` per evitare crash (Errore 500).

## Ottimizzazione Queue
Per non appesantire il sistema, la sincronizzazione dei parametri SMTP avviene **solo** se il Job in elaborazione appartiene a uno di questi pattern:
- Notifiche standard Laravel
- Mailable in coda (`Mail::queue`)
- Namespace `App\Mail` o `App\Notifications`

---

## 🛠️ Risoluzione Problemi comuni

### "Ho cambiato la password ma le mail falliscono ancora"
Se usi **Supervisor**, i worker hanno le vecchie credenziali in memoria. 
- **Soluzione**: Il sistema ricarica automaticamente i dati grazie a `Queue::before`. Se il problema persiste, esegui `php artisan queue:restart`.

### "Le mail non partono su hosting condiviso"
Assicurati che `cron-job.org` (o il cron del server) punti al webhook dello scheduler.
- **Log**: Controlla `storage/logs/laravel.log`. Vedrai la riga `Queue: SMTP configurato da DB` se il sistema sta funzionando.

---
*Ultimo aggiornamento: 2026-02-08*