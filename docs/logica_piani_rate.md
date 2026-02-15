# Logica di Validazione e Copertura Piani Rate (V1.9)

### 1. Il Principio di Integrità
Ogni voce di spesa (**Conto**) del preventivo deve essere associata a un solo piano rate attivo. Questo garantisce l'assenza di raddoppi di incasso (*Double Billing*) o ammanchi nel bilancio consuntivo.

### 2. L'Ancoraggio Atomico e Gerarchico
Il piano rate non è più un'entità astratta. Ogni piano si "ancora" a specifici ID della tabella conti tramite la tabella pivot `piano_rate_capitoli`.

**Meccanismi di Controllo Avanzati:**
* **Auto-Popolamento:** Se l'amministratore crea un piano senza selezionare voci (Piano Globale), il sistema ancora automaticamente tutti i Capitoli Radice orfani a quel piano.
* **Collision Detection Gerarchica:** Il sistema non controlla solo il singolo ID, ma l'intero "ramo familiare" del conto.
    * *Blocco Discendente:* Se selezioni un Padre, tutti i suoi Figli vengono marcati come "In Uso".
    * *Blocco Ascendente:* Se selezioni un Figlio, il Padre viene marcato come "In Uso" (poiché il Padre non è più selezionabile "in blocco").
* **Orphan Check:** Il sistema rileva voci di spesa "orfane" (aggiunte al preventivo dopo la creazione dei piani) e ne segnala l'assenza nella Dashboard.

### 3. Dashboard Audit & Integrità
La Dashboard funge da "Semaforo Contabile" confrontando i dati in tempo reale:
* **Preventivo:** Somma dei conti `parent_id IS NULL` della gestione.
* **Pianificato:** Somma degli importi dei conti ancorati nella pivot `piano_rate_capitoli`.
* **Copertura:** Percentuale di bilancio coperta da piani rate attivi. Se < 100%, viene mostrato un avviso proattivo con l'elenco analitico delle voci scoperte.

### 4. Workflow di Manutenzione (Sincronizzazione)
In presenza di voci "Orfane", l'amministratore dispone di due strade per l'inclusione (**Add Flow**):
* **Piano Integrativo:** Creazione di un nuovo piano dedicato alle voci scoperte.
* **Sincronizzazione Intelligente:** Tramite `SincronizzaCapitoliOrfaniAction`, è possibile integrare le voci orfane in un piano esistente selezionando granularmente (checkbox) quali voci includere.

### 5. Workflow di Correzione (Detach & Undo)
Il sistema permette la rimozione puntuale di voci erroneamente associate (**Remove Flow**), mantenendo la coerenza gerarchica.
* **Lista Voci Incluse:** Visualizzazione collassabile (Accordion) all'interno del dettaglio piano rate, con calcolo dinamico dei totali per i Capitoli Padre (somma dei figli).
* **Smart Alert (Detach):**
    * *Rimozione Figlio:* Dissociazione semplice della singola voce.
    * *Rimozione Padre:* Warning specifico che informa l'utente che l'intera struttura gerarchica (Gruppo + Sottoconti) verrà dissociata.
* **Ricalcolo Immediato:** Al termine del detach, le rate vengono rigenerate e gli importi aggiornati al ribasso.

### 6. La "Fortezza": I 3 Livelli di Protezione
Per garantire l'immutabilità dei dati contabili consolidati, il sistema applica tre livelli di blocco rigorosi su tutte le operazioni di modifica (Sincronizzazione, Detach, Cancellazione):

* **Livello 1 (Blocco Incassi):** Se esistono pagamenti registrati (`importo_pagato > 0`) su qualsiasi rata del piano, **ogni modifica strutturale è inibita**. L'integrità di cassa è prioritaria.
* **Livello 2 (Blocco Emissioni):** Se le rate sono state emesse in contabilità (`scrittura_contabile_id NOT NULL`), il sistema impedisce la modifica e richiede l'annullamento dell'emissione (ritorno in stato Bozza) prima di procedere.
* **Livello 3 (Blocco Dipendenze Preventivo):** A livello di `ContoController`, è impedita la cancellazione fisica di una voce dal preventivo se questa risulta ancorata a un piano rate attivo. È necessario prima rimuoverla dal piano (Detach) per poterla eliminare dal database.