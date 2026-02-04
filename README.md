[![Read in English](https://img.shields.io/badge/Read_in-English-red.svg)](README.en.md)
[![Leggi in Italiano](https://img.shields.io/badge/Leggi_in-Italiano-green.svg)](README.md)
[![Leia em Português](https://img.shields.io/badge/Leia_em-Português-yellow.svg)](README.pt-br.md)
[![Generic badge](https://img.shields.io/badge/Version-1.8.0-blue.svg)](https://github.com/vince844/kondomanager-free/releases)
[![License](https://img.shields.io/badge/License-AGPL_3.0-blue.svg)](https://opensource.org/licenses/AGPL-3.0)

# KondoManager - Software gratuito e open source per la gestione condominiale

**KondoManager** è un innovativo software gratuito e open source per la gestione condominiale, realizzato in **Laravel** e database **MySQL**. Pensato per semplificare la vita degli amministratori di condominio, offrendo al contempo trasparenza e servizi digitali per i condòmini.

---

## Screenshots

<table>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-3.png" alt="Dashboard" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-2.png" alt="Segnalazioni guasto" width="100%"></td>
  </tr>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-1.png" alt="Bacheca condominio" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-6.png" alt="Archivio documenti" width="100%"></td>
  </tr>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-4.png" alt="Agenda del condominio" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-5.png" alt="Gestione utenti e permessi" width="100%"></td>
  </tr>
</table>

---

## Prova la demo

Puoi visualizzare una demo del progetto andando al seguente indirizzo:

👉 **[KondoManager demo](https://rebrand.ly/kondomanager)**

**Attenzione:** Per questioni di sicurezza alcune funzionalità quali l'invio delle email e notifiche sono state disattivate.

**Credenziali di accesso:**

| Ruolo | Email | Password |
| :--- | :--- | :--- |
| **Amministratore** | `admin@kondomanager.it` | `Pa$$w0rd!` |
| **Utente** | `user@kondomanager.it` | `Pa$$w0rd!` |

---

## Funzionalità del gestionale

### Funzioni core

- Sistema di aggiornamento automatico da pannello amministratore
- Gestione anagrafiche condomini e fornitori del condominio
- Gestione segnalazioni guasti del condominio
- Bacheca condominiale digitale per le comunicazioni
- Archivio documenti e categorie del condominio
- Agenda scadenze con gestione ricorrenze
- Gestione avanzata utenti, ruoli e permessi
- Notifiche email automatiche
- Login con protezione a due fattori 
- Sistema di inviti per la registrazione utenti
- Localizzazione: Italiano, Inglese, Portoghese

### Modulo contabilità gestionale e struttura

- Gestione palazzine, scale e immobili
- Conti correnti del condomino
- Tabelle millesimali illimitate
- Gestione esercizi contabili
- Gestioni ordinarie e straordinarie
- Creazione piano dei conti
- Generazione piano rateale anche con ricorrenze avanzate
- Registrazione incassi con ripartizione automatica o manuale
- Partita doppia 
- Emissione rate intelligente
- Estratto conto dell'anagrafica
- Smart inbox intelligente per scadenze in agenda interattive

---

## Requisiti minimi

Per installare KondoManager, il tuo ambiente server deve soddisfare i seguenti requisiti:

- **PHP** >= 8.2
- **Database:** MySQL 5.7+ o MariaDB 10.3+
- **Estensioni PHP:** `zip`, `curl`, `openssl`, `mbstring`, `fileinfo`, `dom`, `xml` consulta la guida di [Laravel](https://laravel.com/docs/12.x/deployment) per ulteriori informazioni
- **Per installazione manuale:** Node.js & NPM, Composer

---

## Installazione guidata (Consigliata per utenti meno esperti)

Per gli utenti meno esperti o per installazioni veloci su hosting condivisi (cPanel, Plesk, ecc.), abbiamo creato un wizard automatizzato.

### 1. Nuova installazione guidata

1. Scarica il [file di installazione](https://kondomanager.short.gy/km-installer) dal sito ufficiale di Kondomanager
2. Estrai e carica il file `index.php` nella **root** del tuo server (via FTP o File Manager su cPanel).
3. Apri il browser all'indirizzo: `https://tuosito.com/index.php`.
4. Segui la procedura guidata a schermo.

Per maggiori dettagli, visita la [guida ufficiale all'installazione](https://www.kondomanager.com/docs/installation.html) oppure il nostro [canale youtube](https://www.youtube.com/@Kondomanager)

### 2. Aggiornamento automatico da pannello amministratore

Il sistema di aggiornamento automatico gestisce automaticamente il ciclo di vita degli aggiornamenti, garantendo la sicurezza dei dati e tutto con pochi click direttamente dal pannello di amministrazione.

**Attenzione** Se non configuri i processi `CronJob`, l'aggiornamento automatico non funzionerà.

**Come configurare CronJob**

Accedi al tuo pannello hosting (cPanel, Plesk) nella sezione "Cron Jobs" o "Pianificazione Attività". Imposta l'esecuzione ogni minuto (* * * * *).

**Esempio per ambiente locale MAMP (Mac):**
```bash
/Applications/MAMP/bin/php/php8.2.0/bin/php tuacartella/artisan schedule:run >> /dev/null 2>&1
```
**Esempio per Server Condiviso (cPanel/Linux):**
```bash
/usr/local/bin/php /home/tuosito/public_html/artisan schedule:run >> /dev/null 2>&1
```

Assicurati di usare il percorso assoluto all'eseguibile PHP v8.2+ per esempio
/usr/local/bin/ea-php82 /home/tuosito/domain_path/path/to/cron/script 

Nell'esempio precedente, sostituisci "ea-php99" con la versione PHP assegnata al dominio che desideri utilizzare. Cerca in MultiPHP Manager la versione PHP effettivamente assegnata a un dominio.

### 3. Aggiornamento dalla versione 1.7.0 alla 1.8.0

Gli aggiornamenti automatici sono disponibili a partire dalla versione 1.8.0 pertanto se stai ancora utilizzando la versione 1.7.0 e vuoi aggiornare devi seguire i passaggi seguenti:

1. Assicurati di avere un backup del `database` e dei files della cartella `storage`
2. Scarica il [file di aggiornamento](https://kondomanager.short.gy/km-installer) dal sito ufficiale di Kondomanager
3. Carica il file `index.php` nella root del tuo server
4. Apri il browser all'indirizzo: `https://tuosito.com/index.php`.
5. Il sistema rileverà automaticamente la versione precedente installata.
6. Clicca su **"Aggiorna adesso"** e segui i passaggi guidati.

**Cosa fa il sistema automaticamente:**

- Backup automatico del file `.env`.
- Scaricamento e installazione dei nuovi file core.
- Ripristino dei dati e delle configurazioni.
- Esecuzione delle migrazioni del database.
- Pulizia e ottimizzazione cache.

**Importante:** Non chiudere la pagina del browser durante il processo di aggiornamento. Il file `index.php` si auto-eliminerà al termine dell'operazione per sicurezza.

---

## Installazione manuale (Per sviluppatori e utenti esperti)

Se desideri contribuire al codice o hai accesso SSH completo al server.

### Prima installazione

1. **Clona la repository**
```bash
git clone https://github.com/vince844/kondomanager-free.git
cd kondomanager-free
```

2. **Installa le dipendenze**
```bash
composer install
npm install
```

3. **Configura l'ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

Modifica il file `.env` inserendo i parametri del tuo database (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

4. **Setup Database**
```bash
php artisan migrate
php artisan db:seed
```

5. **Avvia**
```bash
npm run dev
php artisan serve
```

Visita http://localhost:8000.

**Credenziali Default:** `admin@km.com` / `password` (Ricorda di cambiarle subito andando sul tuo profilo `/settings/profile`).

---

### Aggiornamento Manuale (via SSH/Terminale)

Se preferisci aggiornare manualmente, segui rigorosamente questi passaggi per garantire la compatibilità con il sistema di versioning:

1. **Backup database (Raccomandato)**
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

2. **Aggiorna codice e dipendenze**
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

3. **PASSAGGIO CRITICO**

È fondamentale pulire la cache delle configurazioni prima di migrare, specialmente per il nuovo sistema di versioning settings:
```bash
php artisan config:clear
```

4. **Migrazione e ottimizzazione**
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
```

5. **Configurazione e Avvio delle Code (Queues)** 

Il sistema utilizza di default il driver database (puoi anche utilizzare Redis se preferisci) per gestire i processi in background. È necessario avviare il worker per processare le attività in coda.
```bash
php artisan queue:work
```
**Nota:** In ambiente di produzione, si consiglia di configurare Supervisor per mantenere il processo attivo.

### Verifica versione installata

Puoi verificare la versione corrente e il funzionamento delle configurazioni tramite Tinker:
```bash
php artisan tinker
>>> config('app.version')
```

---

## Documenti utili

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/introduction.html)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Spatie Laravel Settings](https://spatie.be/docs/laravel-settings)

---

## Come contribuire

Chi volesse contribuire a far crescere il progetto è sempre il benvenuto!

Per poter contribuire, si consiglia di seguire le indicazioni descritte all'interno della [documentazione ufficiale](https://github.com/vince844/kondomanager-free/blob/main/CONTRIBUTING). Se volete contribuire attivamente con semplici migliorie o correzioni potete [cercare tra le issues](https://github.com/vince844/kondomanager-free/issues) aperte.

---

## Sostieni il progetto

Sviluppare un software open source richiede molto impegno e dedizione. Ti sarò grato se deciderai di sostenere il progetto.

[Sostieni KondoManager su Patreon](https://www.patreon.com/KondoManager)

---

## Feedback & Supporto

- **Feedback:** Usa la sezione ["Issues" o "Discussions"](https://github.com/vince844/kondomanager-free/issues) di questa repository.
- **Supporto:** Per richieste di personalizzazione o supporto dedicato, usa il [modulo contatti](https://dev.karibusana.org/gestionale-condominio-contatti.html) sul sito ufficiale.

---

## Licenza

Questo progetto è rilasciato sotto licenza [AGPL-3.0](https://github.com/vince844/kondomanager-free?tab=AGPL-3.0-1-ov-file#readme).

---

## Crediti

### Lead Developer:
- [Vincenzo Vecchio](https://github.com/vince844) - Project founder and main developer

### Contributors:
- [Amnit Haldar](https://github.com/amit-eiitech) - Per il suo prezioso contributo sulla creazione dell'installazione guidata
- [k3ntinhu ](https://github.com/k3ntinhu) - Per il suo prezioso contributo sulla configurazione di Docker container e la comunità portoghese
- [Stefano B](https://github.com/borghiste) - Per aver segnalato e risolto un bug di sicurezza 
- Tutti i contributori e sviluppatori della community open source.

---
