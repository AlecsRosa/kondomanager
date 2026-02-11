# Master Documentation: Architettura Gestionale, Eventi & Flussi Finanziari

**Versione:** 1.2 (Unified Release)  
**Data:** 19 Gennaio 2026  
**Tecnologia:** Laravel + Vue.js + Inertia + Lucide Icons  
**Modulo:** Action Inbox, Event System & Flussi Finanziari

---

## 1. Filosofia del Sistema

Il sistema si basa su una **distinzione architetturale netta** tra **Pianificazione** e **Fiscalità**. Questa separazione è fondamentale per gestire correttamente casistiche complesse come i **Subentri** (vendita immobile) e per garantire la **solidità contabile**.

| Concetto | Definizione | Effetto Sistema & Caso d'Uso Critico |
|----------|-------------|---------------------------------------|
| **Piano Rate (Preventivo)** | È una **promessa di pagamento**. | Genera eventi nel calendario ("Promemoria"), ma **non crea ancora debito contabile**. |
| **Emissione (Fiscalità)** | È l'atto formale che rende il debito **esigibile**. | Genera la scrittura contabile e **"fissa" il debitore** (il proprietario in quel momento). Caso Subentro: la responsabilità segue il proprietario alla data di emissione. |
| **Incasso (Cassa)** | È la **riconciliazione bancaria** che chiude il cerchio. | Registra il movimento e aggiorna lo stato (partial/paid). |

---

## 2. Il Ciclo di Vita Automatico (Workflow) 

Tutto parte dall'approvazione di un **Piano Rate**. Il **Listener `SyncScadenziarioWithPianoRate`** orchestra la creazione di tutti i task futuri in una singola transazione.

### Timeline degli Eventi (Esempio: Scadenza Rata 30 Gennaio)

| T-Minus / Data | Attore | Tipo Evento | Azione Sistema |
|----------------|--------|-------------|----------------|
| **23 Gennaio (-7gg)** | Admin | `emissione_rata` | **Task Blu** in Inbox: "Emetti Rata". |
| **30 Gennaio (Day 0)** | Utente | `scadenza_rata_condomino` | **Avviso Blu** nella Dashboard Utente. |
| **03 Febbraio (+4gg)** | Admin | `controllo_incassi` | **Task Viola** in Inbox: "Verifica Incassi". |

### ⚡ Buffer Bancario Intelligente (+4 Giorni)

Il task di `controllo_incassi` viene generato volutamente **4 giorni dopo la scadenza**.

- **Obiettivo:** Permettere ai bonifici effettuati all'ultimo giorno utile di essere **accreditati**.
- **Risultato:** L'amministratore controlla l'estratto conto **una volta sola** e registra tutto in **blocco (Bulk)**, massimizzando l'efficienza operativa.

---

## 3. Action Inbox (Admin) 📥

La **Action Inbox** non è una lista passiva, ma un **Centro di Comando**. Implementa una **Logica di Design Ibrida** per massimizzare la velocità di decisione.

### A. Gerarchia Visiva (Hybrid Logic)

Il sistema separa visivamente **l'Urgenza** dal **Contenuto**.

- **L'Urgenza vince sul Colore:**
  - Se il task è **Scaduto** (`status: 'expired'`), il **bordo e lo sfondo diventano ROSSI** 🔴 per attirare l'attenzione immediata.
  - Se il task è **In Tempo**, il bordo segue il colore della tipologia (Viola, Blu, ecc.).

- **Il Tipo definisce l'Identità:**
  - Anche se il task è rosso, **l'icona e il testo mantengono SEMPRE** il colore semantico per identificare cosa fare.
  - **Risultato:** L'admin capisce subito _"È urgente"_ (Rosso) e _"Riguarda i soldi"_ (Viola).

### B. Token Semantici & Icone (Design System)

| Tipo Task (meta.type) | Token Colore | Icona (Lucide) | Significato |
|----------------------|-------------|----------------|-------------|
| **Emissione Rata** | 🔵 Blu | `ArrowUpFromLine` ⬆️ | **Output:** Richiesta pagamento inviata ai condomini. |
| **Controllo Incassi** | 🟣 Viola | `ArrowDownToLine` ⬇️ | **Input:** Verifica accrediti bancari (Bulk). |
| **Verifica Pagamento** | 🟡 Ambra | `Banknote` 💶 | **Segnalazione:** Utente dichiara "Ho pagato". Richiede validazione manuale. |
| **Scaduto/Urgente** | 🔴 Rosso | `AlertTriangle` ⚠️ | **Stato:** Task non completato entro la scadenza. |
| **Manutenzione** | 🔵 Indaco | `Wrench` 🔧 | **Ticket:** Gestione interventi tecnici. |

### C. Sistema di Filtri (4 Macro-Lenti)

La Inbox dispone di **filtri intelligenti** per organizzare il lavoro:

1. **Urgenti (Rosso):** Mostra solo ciò che è scaduto (`status: expired`).
2. **Verifiche Incassi (Viola):** Filtra task di tipo `controllo_incassi` e `verifica_pagamento`.
3. **Ticket & Manut. (Blu):** Filtra task di tipo `manutenzione` o ticket aperti.
4. **Vedi Tutto:** Reset dei filtri.

### D. Gestione Rifiuti (Reject Modal)

Per i task di tipo `verifica_pagamento` (segnalazione utente), l'admin può:

- **Accettare:** Registra l'incasso e chiude il task.
- **Rifiutare:** Apre una **Modale dedicata**:
  - Richiede **obbligatoriamente** una motivazione (es. "Bonifico non trovato").
  - Invia una notifica all'utente e cambia lo stato dell'evento utente in `rejected`.
  - **L'azione è irreversibile.**

### E. Gestione Cache (Performance)

Per performance elevate, il **badge rosso** della sidebar (`inbox_count_{user_id}`) è **cachato**.  
Deve essere invalidato (`Cache::forget`) su:

1. **Creazione/Approvazione Piano Rate.**
2. **Chiusura Task** (Accettazione o Rifiuto).
3. **Scadenza temporale** (gestita dal cron/scheduler giornaliero che aggiorna gli stati).
4. **Registrazione Incasso** collegato a un task.

---

## 4. Esperienza Utente (Condòmino) 

Il componente **`EventDetailsDialog.vue`** protegge l'utente da errori contabili con **sicurezza ferrea**.

### Stati della Rata (Frontend Logic)

| Stato | Visuale | Significato | Azione Utente |
|-------|---------|-------------|---------------|
| **Non Emessa** | 🟡 `!isEmitted` | **Bloccante.** Il debito non è ancora fiscalizzato. | **Pulsante "Paga" disabilitato** (Sicurezza). Messaggio: "Attendi l'emissione ufficiale". |
| **Emessa** | 🔵 `isEmitted && Pending` | Debito esigibile e valido. | Pulsante **"Ho effettuato il pagamento"** (o Stripe in futuro) attivo. Messaggio: "Emissione Confermata". |
| **Pagata / Parziale** | 🟢 / 🟠 | Stati gestiti automaticamente in base al residuo contabile. | Visualizzazione **ricevuta/stato completato**. |

**Trigger:** Il flag `is_emitted` passa a `true` quando l'admin esegue `EmissioneRateController`.

**Logica "Financial Waterfall" (Smart Debt)**

Il sistema applica un algoritmo a cascata (CalculatesFinancialWaterfall) per presentare il debito in modo psicologicamente corretto al condòmino:

    Isolamento Contestuale: Il calcolo degli arretrati è segregato rigorosamente per Condominio.

    Assorbimento Debito (Anti-Panico): Se una rata corrente include un saldo pregresso (es. conguaglio), il sistema nasconde l'avviso rosso "Arretrati" per evitare di sommare visivamente il vecchio debito al nuovo.

    Visualizzazione a 3 Stati:

        🔴 Arretrati: Rate precedenti non pagate e non incorporate.

        🟠 Debito Incorporato: Rata corrente maggiorata da debito pregresso (Avviso Ambra).

        🔵 Credito Usato: Rata corrente scontata da credito pregresso (Avviso Blu).

---

## 5. Modulo Incassi & Emissioni (Backend) 

### Emissione (`EmissioneRateController`)

Quando l'admin clicca **"Emetti"**:

1. **Genera** la Scrittura Contabile (Dare vs Avere).
2. **Aggiorna** gli eventi Utente collegati: `meta['is_emitted'] = true`.
3. **Chiude** il task Admin `emissione_rata`.
4. **Pulisce** la cache Inbox.

### Incasso (`IncassoRateController`)

Quando l'admin registra un incasso (manualmente o da task):

1. **Registra** il movimento di denaro.
2. **Ricalcola** lo stato degli eventi Utente (Partial o Paid).
3. **Smart Feature:** Se l'incasso deriva da una segnalazione utente (`verifica_pagamento`), chiude automaticamente il task correlato.
4. **Admin Warning:** Nella pagina `IncassoRateNew`, se si seleziona una rata **non emessa**, appare un badge Ambra **"NO EMISSIONE"** per avvisare l'admin che sta creando un anticipo/acconto.

---

## 6. Struttura Dati (Evento) 

Il modello **Evento** è polimorfico grazie al campo JSON `meta`.

```json
// Esempio Task: Controllo Incassi
{
    "title": "Verifica Incassi - Rata 2",
    "start_time": "2026-02-03 09:00:00", // Scadenza + 4gg
    "meta": {
        "type": "controllo_incassi",
        "requires_action": true,
        "is_emitted": false, // Solo per eventi utente
        "context": {
            "piano_rate_id": 15,
            "rata_id": 42
        },
        "action_url": "/admin/gestionale/incassi/create?..."
    }
}

```

---

## 7. Roadmap Futura (V2 Concepts) 

Idee approvate per la prossima iterazione:

| Feature | Icona | Descrizione |
| --- | --- | --- |
| **Snooze Task** | 💤 | Tasto *"Ricordamelo domani"* (sposta `start_time` di +24h/48h). Utile se l'estratto conto non è ancora pronto. |
| **Bulk Actions** | ⚡ | Esecuzione massiva di task simili (es. *"Emetti tutte le 10 rate in scadenza questa settimana"* con un click). |
| **Filtro Condominio** | 🏢 | Dropdown nella Inbox per visualizzare solo i task relativi a **uno specifico stabile**. |
| **Context Filter** | 🎯 | Avanzamento del filtro condominio per navigazione più granulare. |

---
## [1.9.0] - 2026-02-11

### 🐛 Bug Fixes (Critical)
- **Financial Waterfall Isolation:** Risolto un bug critico nel calcolo degli arretrati che aggregava erroneamente i debiti di condomini diversi per lo stesso proprietario (Cross-Condominium Pollution).
- **Ghost Data Handling:** Implementata logica di pulizia per ignorare rate orfane di piani cancellati o gestioni obsolete.

### ✨ Features & Improvements
- **Smart Debt Absorption:** Introdotta logica nel Trait `CalculatesFinancialWaterfall` per rilevare quando un saldo pregresso è incorporato nella rata corrente.
    - *Effetto:* L'avviso "Arretrati" viene soppresso se il debito è già spalmato nella rata, evitando la "Doppia Imputazione" visiva.
- **Frontend Intelligence (Vue):** Aggiunta gestione a 3 stati per il box avvisi finanziari nel dettaglio evento:
    - 🟠 *Warning:* Saldo debitore incluso nella rata.
    - 🔵 *Info:* Credito pregresso utilizzato.
    - 🔴 *Danger:* Arretrati standard non coperti.
- **Backend Refactor:** Il Trait di calcolo ora lavora rigorosamente in **centesimi (integer)** per evitare errori di virgola mobile sui totali aggregati.

**Documentazione aggiornata al 11/02/2026.** *Sistema progettato per scalabilità, sicurezza contabile e massima efficienza operativa.*
