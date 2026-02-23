# Logica di Validazione e Copertura Piani Rate (V1.9.3)

### 1. Il Principio di Saturazione del Budget
Nella V1.9.3 superiamo il concetto rigido di "Una voce = Un piano".
La nuova regola aurea è la **Saturazione**: una singola voce di spesa del preventivo può essere distribuita su più piani rate (es. Acconto e Saldo), purché la somma degli importi parziali non superi il totale preventivato.

### 2. Ancoraggio Frazionato & Smart Overrides
Il collegamento tra Piano Rate e Voci di Spesa (Pivot `piano_rate_capitoli`) ora trasporta un metadato fondamentale: l'**Importo Impegnato**.

* **Partial Budgeting:** L'amministratore può decidere di includere solo una quota parte di una voce (es. 400€ su 1.000€). Il sistema traccia il "Residuo Disponibile" per i piani successivi.
* **Smart Folder Push-Down:** Se viene selezionato un "Capitolo Padre" (Folder senza tabella millesimale) e gli viene assegnato un importo forzato (Override), il sistema applica una logica proporzionale inversa:
    1. Calcola il rapporto tra l'Override e il totale originale dei figli.
    2. "Spinge" (Push-Down) questo rapporto sui sottoconti figli.
    3. Distribuisce l'importo ridotto usando le tabelle millesimali specifiche di ogni figlio.

### 3. Motore di Calcolo "Penny Perfect"
Per garantire la quadratura assoluta dei conti, il motore di calcolo (`CalcoloQuoteService`) abbandona gli arrotondamenti standard in favore dell'**Algoritmo di Quadratura**.

* **Logica:** Durante la distribuzione su N condomini, il sistema accumula gli importi assegnati. All'ultimo beneficiario viene assegnato esattamente il *residuo matematico* (Totale - Somma Assegnata).
* **Risultato:** Errore di arrotondamento = **0.00€**. La somma delle rate generate corrisponde sempre al centesimo all'importo impegnato.

### 4. Logica Saldi & Piani Integrativi
Il sistema adotta una **Strategia Decisionale Ibrida** per prevenire la duplicazione dei debiti pregressi:

* **Piano Principale (Master):** È il primo piano generato. Il Controller rileva che i saldi non sono ancora stati usati (`saldo_applicato = 0`), li include nel calcolo e marca il Flag DB a `1`.
* **Piano Integrativo:** Qualsiasi piano creato successivamente trova il Flag DB a `1`. Il sistema riconosce che i debiti pregressi sono già stati "spesi" e forza l'esclusione dei saldi (`$saldi = []`), generando un piano contenente *solo* le nuove spese correnti.
* **UX Contestuale:** I tooltip e gli indicatori visivi dei saldi (pallini Rossi/Blu) appaiono nell'interfaccia solo se lo snapshot delle regole di calcolo conferma l'effettivo utilizzo dei saldi in quel piano.

### 5. Dashboard Audit & Integrità Reale
Il "Semaforo Contabile" della Dashboard è stato aggiornato per leggere i dati reali:
* **Preventivo:** Somma dei valori nominali dei conti nel database.
* **Pianificato (Reale):** Somma degli importi *effettivi* (Override) definiti nella tabella pivot dei piani attivi.
* **Anomaly Detection:** Il sistema confronta Preventivo vs Pianificato. Se `Pianificato < Preventivo`, segnala l'ammanco come "Residuo da Rateizzare".

### 6. Workflow di Manutenzione (Sincronizzazione)
In presenza di voci "Orfane" o parzialmente scoperte:
* **Sincronizzazione Granulare:** È possibile aggiungere voci a un piano esistente.
* **Gestione Conflitti:** Se si tenta di aggiungere una voce che ha residuo 0 (già saturata altrove), il sistema inibisce la selezione visualizzando un indicatore di "Budget Esaurito".

### 7. La "Fortezza": I 3 Livelli di Protezione
Per garantire l'immutabilità dei dati contabili consolidati, permangono i tre livelli di blocco rigorosi:

* **Livello 1 (Blocco Incassi):** Se esistono pagamenti registrati (`importo_pagato > 0`) su qualsiasi rata del piano, **ogni modifica strutturale è inibita** (inclusa la modifica degli importi parziali).
* **Livello 2 (Blocco Emissioni):** Se le rate sono state emesse in contabilità (`scrittura_contabile_id NOT NULL`), il sistema impedisce la modifica e richiede l'annullamento dell'emissione.
* **Livello 3 (Blocco Dipendenze Preventivo):** A livello di `ContoController`, è impedita la modifica dell'importo base di una voce di spesa se questa è ancorata a un piano rate attivo, per evitare disallineamenti tra "Preventivato" e "Rateizzato".

### 8. Dinamismo del Budget: Lo "Sposta Spesa"
La V1.9.0 introduce il concetto di **Budget Dinamico**, permettendo di correggere la pianificazione finanziaria durante l'anno senza dover modificare il preventivo approvato.

* **Il Muro delle Gestioni:** Lo spostamento è consentito solo tra voci appartenenti allo stesso Piano dei Conti. Questo impedisce violazioni contabili dove i condòmini di una gestione (es. Scale) finirebbero per finanziare involontariamente le spese di un'altra (es. Riscaldamento).
* **Indicatori Visivi di Trasparenza (Badges):**
    * 🏷️ **INTEGRA:** Identifica le voci che hanno ricevuto fondi extra. Un sottotitolo esplicativo avvisa l'utente che l'importo visualizzato include integrazioni esterne non presenti nel preventivo originale.
    * 🏷️ **STANDARD:** Contrassegna le voci "pure", il cui importo corrisponde esattamente a quanto preventivato originariamente.
* **Integrità del Detach (Blocco di Dipendenza Globale):** Per garantire la quadratura, non è possibile "staccare" una voce da un Piano Rate se questa ha dei movimenti di budget registrati. L'amministratore deve prima eseguire un movimento inverso per riportare il saldo della voce alla sua condizione originale, "liberandola" per la cancellazione.
* **Audit-Proofing:** Grazie alla separazione tra "Budget Originario" e "Movimenti", il sistema è in grado di ricostruire in ogni momento il motivo di ogni variazione, rendendo il rendiconto consuntivo a prova di revisione.