# Changelog

Tutte le modifiche notevoli a questo progetto saranno documentate in questo file.

---

## [1.9.3] - Penny Perfect & Smart Push-Down

Questa release rifinisce il motore "Accounting Intelligence Core" (v1.9) introducendo la precisione assoluta nei calcoli e una gestione intelligente dei capitoli raggruppati.

### 🧠 Accounting Core Intelligence: Evolution

* **Frazionamento Voci di Spesa (Partial Budgeting):**
    * Introdotta la possibilità di includere nei Piani Rate solo una quota parte dell'importo totale di una voce di spesa (es. richiedere un acconto di 400€ su una spesa da 1.000€).
    * Il sistema traccia automaticamente il "Residuo Disponibile" per i piani successivi e impedisce il superamento del budget preventivato.
* **Algoritmo "Penny Perfect" (Quadratura Centesimale):**
    * Il motore di calcolo delle quote (`CalcoloQuoteService`) è stato riscritto per eliminare gli errori di arrotondamento.
    * Implementata logica di quadratura: l'ultimo beneficiario di una ripartizione assorbe matematicamente l'eventuale resto, garantendo che la somma delle quote corrisponda al 100% dell'importo speso, al centesimo.
* **Logica "Smart Folder Push-Down":**
    * Supporto avanzato per i "Capitoli Contenitore" (Folder) nei piani rate parziali.
    * Se si assegna un importo forzato a un Capitolo Padre (es. "Spese Generali: 200€") che non ha tabella millesimale propria, il sistema calcola automaticamente il rapporto proporzionale rispetto al preventivo originale e "spinge" l'override sui sottoconti figli, distribuendo l'importo corretto in base alle loro tabelle specifiche.
* **Gestione Piani Integrativi (No-Duplicate Balance):**
    * Introdotta logica decisionale ibrida (Controller + DB) nell'Action di generazione.
    * Il sistema ora distingue tra "Primo Piano" (che applica i saldi pregressi) e "Piani Integrativi" (che contengono solo le nuove spese), prevenendo la duplicazione dei debiti/crediti iniziali.

### 🐛 Bug Fixes & Refactoring

* **Correzione Ricorsione Override:** Risolto un bug critico nel calcolo dei piani parziali dove l'importo del padre si sommava erroneamente a quello dei figli. Ora l'override sul nodo padre interrompe correttamente la ricorsione.
* **Tooltip Saldi Intelligenti:** Il frontend ora mostra i dettagli dei saldi (pallini rossi/blu) solo se il piano rate specifico li include effettivamente (basandosi sullo snapshot delle regole di calcolo), evitando confusione nei piani integrativi.
* **Fix Totali Widget Copertura:** La risorsa API ora calcola il totale del piano rate leggendo correttamente gli importi parziali dalla tabella pivot, allineando il widget di copertura con il reale valore delle rate generate.
* **Mass Assignment Protection:** Aggiunti `saldo_applicato` e `nota_saldo` al modello `Gestione` per permettere il corretto salvataggio dello stato dei saldi.

---

## [1.9.0] - Accounting Intelligence Core

Questa release rappresenta il più grande aggiornamento strutturale al motore contabile di Kondomanager.
Con la v1.9.0 introduciamo l'**Audit Intelligence**: un sistema di controllo attivo che garantisce l'integrità matematica tra Preventivo e Piani Rate.
Abbiamo eliminato l'astrazione dei piani rate: ora ogni voce di spesa è "ancorata" atomicamente, prevenendo duplicazioni, ammanchi e errori di ripartizione.

Inoltre, questa versione abbatte le barriere tecniche, introducendo la piena compatibilità con hosting condivisi (come Altervista), gestione avanzata dei Cron Job e configurazioni email semplificate.

### 🧠 New Feature: Accounting Core Intelligence

Il nuovo motore contabile introduce livelli di sicurezza avanzati per "blindare" il bilancio condominiale:

* **Ancoraggio Atomico & Gerarchico:** I piani rate vengono collegati a specifici capitoli di spesa tramite una tabella pivot.
    * *Auto-Popolamento:* I piani globali vengono ancorati automaticamente a tutte le spese correnti.
    * *Gerarchia:* Il selettore capitoli supporta la logica Padre/Figlio con indicatori visivi di stato.
* **Collision Detection (Anti-Double Billing):** Il sistema impedisce matematicamente di inserire la stessa voce di spesa in due piani rate attivi contemporaneamente.
* **Double-Lock Strategy (Lucchetto sui Saldi):**
    * *Protezione Saldo Applicato:* Al momento della creazione di un Piano Rate, il sistema impegna in modo irreversibile il saldo dell’esercizio precedente.
    * *Hard-Lock:* Blocco a livello di Controller per impedire tentativi di duplicazione addebito su altre gestioni.
* **Dashboard Audit & Copertura:** Nuova widget "Semaforo Contabile" nella dashboard. Confronta in tempo reale il Preventivo vs Pianificato e segnala le voci "Orfane".
* **Sincronizzazione Intelligente (Smart Sync):** Workflow guidato per integrare le voci orfane nei piani rate esistenti con selezione granulare.
* **Blocco Cancellazione Preventivo:** Protezione a livello di `ContoController` per impedire l'eliminazione di voci ancorate a piani attivi.

### 🛠️ System & Hosting Compatibility

Abbiamo reso Kondomanager installabile ovunque, dai server dedicati agli hosting gratuiti.

* **Database Flexibility:** Configurazione `.env` per supportare charset diversi da `utf8mb4` (compatibilità legacy MySQL/Altervista).
* **Hosting Condiviso & HTTPS:** Logica avanzata per forzare HTTPS e gestire i reverse proxies (`TRUSTED_PROXIES`), risolvendo loop di redirect.
* **Gestione Cron Job Remoti:** Attivazione dei processi pianificati (Queue Work) tramite chiamata HTTP esterna sicura con token cifrato.
* **Configurazione SMTP via UI:** Configurazione server di posta direttamente da pannello, senza editare file `.env`.

### 🚀 Improvements

* **UX Potenziata (No-Jump & Design System):**
    * *Filtro Capitoli:* Caricamento asincrono e disabilitazione input per evitare "salti" visivi della pagina.
    * *Coerenza Design:* Adozione completa dei pattern Shadcn/UI (checkbox opachi, toggle moderni) su tutta l'interfaccia.
* **Logica Condizionale Saldi:** Il selettore "Distribuzione Saldo Iniziale" appare solo se il sistema rileva effettivi arretrati recuperabili.
* **Admin Inbox Notifiche:** Badge visivo sul pulsante Admin Inbox per le notifiche di "Pagamento Effettuato".
* **Log Email:** Sistema di logging per tracciare lo stato di invio delle email.
* **Logica "Financial Waterfall":** Aggiornamento del Trait per rilevare con precisione quando un saldo pregresso è incorporato nella rata corrente.

### 🐛 Bug Fixes

* **CRITICO - Cross-Condominium Pollution:** Risolto un bug grave nel calcolo degli arretrati che aggregava erroneamente i debiti dello stesso proprietario su condomini diversi.
* **Duplicazione Saldi:** Risolto problema che impegnava irreversibilmente il saldo dell'esercizio precedente alla creazione del piano rate (ora gestito dinamicamente).
* **Pulizia Rate Orfane:** Implementata logica automatica per ignorare rate collegate a piani cancellati o gestioni obsolete.
* **Validazione Obbligatoria Tabelle:** Introdotta logica rigorosa per le voci di spesa (singole o sottoconti). Il campo "Tabella Millesimale" è ora obbligatorio per garantire che ogni spesa abbia sempre un criterio di ripartizione certo.

---

## [1.8.0] - The "Smart Assistant" Update

Questa release segna un cambio di paradigma per Kondomanager. Abbiamo lavorato intensamente per trasformare il gestionale da un semplice archivio dati a un **Assistente Proattivo**.

Con la nuova **Smart Activity Inbox**, il sistema lavora per te: il calendario non è più statico, ma suggerisce in modo intelligente le scadenze imminenti e le azioni richieste.
Inoltre, introduciamo gli **Aggiornamenti Frontend**, permettendo anche agli utenti su hosting condivisi (o con poca esperienza di terminale) di mantenere il software aggiornato con un semplice click.

### ✨ New Features

#### Core & Automazione
* **Smart Activity Inbox:** Il nuovo motore eventi trasforma il calendario in un assistente virtuale. Il sistema ora genera e suggerisce eventi collegati alla generazione e ai pagamenti delle rate, permettendo una gestione proattiva delle scadenze.
* **Aggiornamenti Automatici da Frontend:** Nuova funzione dedicata agli utenti che hanno usato l'installazione guidata. È ora possibile aggiornare Kondomanager direttamente dal pannello di amministrazione senza accedere alla console del server.
* **Condominio di Default al Login:** Nelle impostazioni generali, gli amministratori possono ora impostare un condominio specifico da aprire automaticamente al login. Ogni utente (admin o collaboratore) può personalizzare questa scelta, ottimizzando il flusso di lavoro.

#### Contabilità & Gestione
* **Gestione Fornitori:** Aggiunto modulo completo per la creazione e gestione delle anagrafiche fornitori.
* **Casse del Condominio:** Nuova funzionalità per creare e gestire le risorse finanziarie e le casse condominiali.
* **Emissione Rate Evoluta (Capitoli di Spesa):** Introdotta la possibilità di emettere rate parziali o mirate selezionando specifici capitoli di spesa (es. generare rate solo per "Scala A").
* **Piani Rate Multipli:** Evoluzione della logica contabile. Ogni gestione mantiene un singolo piano dei conti, ma ora può supportare **più piani rate**, offrendo massima flessibilità.
* **Registrazione Pagamento Rate:** Nuova interfaccia dedicata per la registrazione rapida dei pagamenti.
* **Ottimizzazione Incassi Multi-gestione:** Supporto avanzato per pagamenti che coprono più gestioni, con riconciliazione virtuale visibile nei report.
* **Estratto Conto:** Aggiunta la visualizzazione dell'estratto conto direttamente nell'anagrafica del condòmino.
* **Statistiche Dashboard:** Nuovi moduli statistici sulla home page amministratore per un controllo immediato dell'andamento gestionale.

#### Internazionalizzazione
Kondomanager diventa globale. Abbiamo aggiunto il supporto completo per le lingue **Inglese** e **Portoghese** in tutto l'ecosistema:
* Traduzione completa delle **Impostazioni Generali** e dell'interfaccia **Frontend**.
* Traduzione modulo **Comunicazioni in Bacheca**.
* Traduzione modulo **Autenticazione e Registrazione**.
* Traduzione delle **Notifiche Email** transazionali.
* Traduzione modulo **Documenti/Archivio** del condominio.
* Traduzione modulo **Segnalazioni Guasti**.

#### DevOps
* **Supporto Docker:** Aggiunta guida ufficiale e file di configurazione per il deploy di Kondomanager tramite Docker (Special thanks to @k3ntinhu).

### 🚀 Improvements

* **Nuovo Menu "Rubrica":** Riorganizzazione della Topbar. La voce "Anagrafiche" diventa "Rubrica" e integra un menu a tendina per l'accesso rapido sia ai Condòmini che ai Fornitori.
* **Visualizzazione Permessi Rapida:** Le tabelle *Utenti* e *Ruoli* ora mostrano direttamente i permessi associati nelle colonne, evitando di dover entrare in modifica per verificarli.
* **Gestione Intelligente Permessi:** Migliorata la logica di assegnazione e revoca permessi durante la modifica di un Utente o di un Ruolo.
* **Smart Associazione Immobili:** Nel menu a tendina per associare un'anagrafica a un immobile, il sistema ora mostra *solo* le anagrafiche già presenti nel condominio ma *non ancora associate* a quell'immobile specifico, prevenendo duplicazioni errate.
* **Filtro Preventivi nel Piano dei Conti:** Durante la creazione di un nuovo piano dei conti, il controller ora filtra e mostra solo le gestioni che non hanno ancora un preventivo associato.
* **Integrazione Widget Eventi:** Il widget eventi nella dashboard utente è stato collegato alla nuova *Smart Activity Inbox* per mostrare le notifiche intelligenti.
* **UX Piani Rate:** Migliorata la visualizzazione e le funzioni operative all'interno della gestione piani rate.

### 🐛 Bug Fixes

* **Valori negativi:** Risolto un bug che impediva l'inserimento di valori negativi nelle maschere di input delle anagrafiche associate all'immobile (utile per conguagli o crediti pregressi).
* **Registrazione utenti invitati:** Risolto un problema che impediva agli utenti invitati via email di completare la registrazione se l'opzione "Registrazione pubblica" era disabilitata nelle impostazioni generali.
* **Sicurezza password:** Implementato controllo per impedire il riutilizzo della password corrente durante la procedura di cambio password (Special thanks to @borghiste - Issue #30).