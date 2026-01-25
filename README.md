
[![en](https://img.shields.io/badge/lang-en-red.svg)](https://github.com/vince844/kondomanager-free/blob/main/README.en.md)
[![it](https://img.shields.io/badge/lang-it-green.svg)](https://github.com/vince844/kondomanager-free/blob/main/README.md)
[![pt-br](https://img.shields.io/badge/lang-pt--br-yellow.svg)](https://github.com/vince844/kondomanager-free/blob/main/README.pt-br.md)
[![Generic badge](https://img.shields.io/badge/Version-1.8.0_Beta.2-blue.svg)](https://github.com/vince844/kondomanager-free/releases)
[![License](https://img.shields.io/badge/License-AGPL_3.0-blue.svg)](https://opensource.org/licenses/AGPL-3.0)

# KondoManager - Software gratuito e open source per la gestione condominiale

**KondoManager** è un innovativo software gratuito e open source per la gestione condominiale, realizzato in **Laravel** e database **MySQL**. È pensato per semplificare la vita degli amministratori di condominio, offrendo al contempo trasparenza e servizi digitali ai condòmini.

---

## 📸 Screenshots

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

## 🌐 Prova la demo

Puoi visualizzare una demo del progetto andando al seguente indirizzo:

👉 **[KondoManager Demo](https://rebrand.ly/kondomanager)**

> ⚠️ **Nota:** Per questioni di sicurezza alcune funzionalità (scrittura su filesystem, invio email) sono state disattivate.

**Credenziali di accesso:**

| Ruolo | Email | Password |
| :--- | :--- | :--- |
| **Amministratore** | `admin@kondomanager.it` | `Pa$$w0rd!` |
| **Utente** | `user@kondomanager.it` | `Pa$$w0rd!` |

---

## ✨ Funzionalità del gestionale

### Funzioni core

- **Sistema di aggiornamento automatico (Universal Diamond 💎)**
- Gestione anagrafiche condomini
- Gestione segnalazioni guasti (ticket system)
- Bacheca condominiale digitale
- Archivio documenti e categorie
- Agenda scadenze condivisa
- Gestione avanzata utenti, ruoli e permessi
- Notifiche email automatiche

### Modulo contabilità e struttura

- Gestione palazzine, scale e immobili
- Tabelle millesimali illimitate
- Gestione esercizi contabili
- Gestioni ordinarie e straordinarie
- Creazione piano dei conti (preventivo spesa)
- Generazione piano rateale

---

## ⚙️ Requisiti minimi

Per installare KondoManager, il tuo ambiente server deve soddisfare i seguenti requisiti:

- **PHP** >= 8.2
- **Database:** MySQL 5.7+ o MariaDB 10.3+
- **Estensioni PHP:** `zip`, `curl`, `openssl`, `mbstring`, `fileinfo`, `dom`, `xml`
- **Per installazione manuale:** Node.js & NPM, Composer

---

## 🚀 Installazione guidata (Consigliata per Hosting)

Per gli utenti meno esperti o per installazioni veloci su hosting condivisi (cPanel, Plesk, ecc.), abbiamo creato un wizard automatizzato.

### 1. Nuova installazione

1. Scarica il [file di installazione](https://kondomanager.short.gy/installer).
2. Carica il file `index.php` nella **root** del tuo server (via FTP o File Manager).
3. Apri il browser all'indirizzo: `https://tuosito.com/index.php`.
4. Segui la procedura guidata a schermo.

Per maggiori dettagli, visita la [guida ufficiale all'installazione](https://www.kondomanager.com/docs/installation.html).

### 2. Aggiornamento automatico (Universal diamond)

Il sistema **Universal Diamond** gestisce automaticamente il ciclo di vita degli aggiornamenti, garantendo la sicurezza dei dati.

1. Scarica il [file di aggiornamento](https://kondomanager.short.gy/installer).
2. Carica il file `index.php` nella root del tuo server (**sovrascrivendo** se necessario).
3. Apri il browser all'indirizzo: `https://tuosito.com/index.php`.
4. Il sistema rileverà automaticamente la versione precedente installata.
5. Clicca su **"Aggiorna alla v1.8beta2"** (o versione successiva disponibile).

**Cosa fa il sistema automaticamente:**

- ✅ Backup automatico di `.env`, cartella `storage` e `uploads`.
- ✅ Scaricamento e installazione dei nuovi file core.
- ✅ Ripristino dei dati e delle configurazioni.
- ✅ Esecuzione delle migrazioni del database.
- ✅ Pulizia e ottimizzazione cache.

> ⚠️ **IMPORTANTE:** Non chiudere la pagina del browser durante il processo di aggiornamento. Il file `index.php` si auto-eliminerà al termine dell'operazione per sicurezza.

---

## 🔧 Installazione manuale (Per sviluppatori)

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

**Credenziali Default:** `admin@km.com` / `password` (Ricorda di cambiarle subito su `/settings/profile`).

### Aggiornamento Manuale (via SSH/Terminale)

Se preferisci aggiornare manualmente, segui rigorosamente questi passaggi per garantire la compatibilità con il sistema di versioning:

1. **Backup Database (Raccomandato)**
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

2. **Aggiorna Codice e Dipendenze**
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

3. **🔥 PASSAGGIO CRITICO 🔥**

È fondamentale pulire la cache delle configurazioni prima di migrare, specialmente per il nuovo sistema di versioning settings:
```bash
php artisan config:clear
```

4. **Migrazione e Ottimizzazione**
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
```

### Verifica Versione Installata

Puoi verificare la versione corrente e il funzionamento delle configurazioni tramite Tinker:
```bash
php artisan tinker
>>> config('app.version')
```

---

## 📚 Documenti utili

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/introduction.html)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Spatie Laravel Settings](https://spatie.be/docs/laravel-settings)

---

## 🤝 Come contribuire

Chi volesse contribuire a far crescere il progetto è sempre il benvenuto!

Per poter contribuire, si consiglia di seguire le indicazioni descritte all'interno della [documentazione ufficiale](https://github.com/vince844/kondomanager-free/blob/main/CONTRIBUTING). Se volete contribuire attivamente con semplici migliorie o correzioni potete [cercare tra le issues](https://github.com/vince844/kondomanager-free/issues) aperte.

---

## ❤️ Sostieni il progetto

Sviluppare un software open source richiede molto impegno e dedizione. Ti sarò grato se deciderai di sostenere il progetto.

👉 [Sostieni KondoManager su Patreon](https://www.patreon.com/KondoManager)

---

## 💬 Feedback & Supporto

- **Feedback:** Usa la sezione "Issues" o "Discussions" di questa repository.
- **Supporto:** Per richieste di personalizzazione o supporto dedicato, usa il [modulo contatti](https://dev.karibusana.org/gestionale-condominio-contatti.html) sul sito ufficiale.

---

## 📜 Licenza

Questo progetto è rilasciato sotto licenza [AGPL-3.0](https://github.com/vince844/kondomanager-free?tab=AGPL-3.0-1-ov-file#readme).

---

## 🏆 Credits

### Lead Developer:
- **Vincenzo Vecchio** - Project founder and main developer

### Contributors:
- [Amnit Haldar](https://github.com/amit-eiitech) - Amazing laravel developer
- Tutti i contributori della community open source.

---

**Versione corrente:** 1.8.0-beta.2 | **Sistema di aggiornamento:** Universal Diamond 

