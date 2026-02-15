<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Actions\PianoRate\GeneratePianoRateAction;
use App\Enums\StatoPianoRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestionale\PianoRate\CreatePianoRateRequest;
use App\Http\Requests\Gestionale\PianoRate\PianoRateIndexRequest;
use App\Http\Resources\Condominio\CondominioResource;
use App\Http\Resources\Gestionale\PianiRate\PianoRateResource;
use App\Models\Condominio;
use App\Models\Esercizio;
use App\Models\Gestionale\PianoRate;
use App\Models\Gestione;
use App\Services\PianoRateCreatorService;
use App\Services\PianoRateQuoteService;
use App\Traits\HandleFlashMessages;
use App\Traits\HasCondomini;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\Gestionale\PianoRateStatusUpdated;
use App\Helpers\MoneyHelper;
use App\Services\Gestionale\SaldoEsercizioService;
use Illuminate\Support\Facades\Auth; 

class PianoRateController extends Controller
{
    use HandleFlashMessages, HasCondomini;

    public function __construct(
        private PianoRateQuoteService $pianoRateQuoteService,
        private PianoRateCreatorService $pianoRateCreatorService,
        private SaldoEsercizioService $saldoService, 
    ) {}

    public function index(PianoRateIndexRequest $request, Condominio $condominio, Esercizio $esercizio): Response
    {
        $validated = $request->validated();

        $pianiRate = PianoRate::with(['gestione'])
            ->where('condominio_id', $condominio->id)
            ->whereHas('gestione.esercizi', fn($q) => $q->where('esercizio_id', $esercizio->id))
            ->paginate($validated['per_page'] ?? config('pagination.default_per_page'));

        $esercizi = $condominio->esercizi()->orderBy('data_inizio', 'desc')->get(['id', 'nome', 'stato']);

        return Inertia::render('gestionale/pianiRate/PianiRateList', [
            'condominio' => $condominio,
            'esercizio'  => $esercizio,
            'esercizi'   => $esercizi,
            'condomini'  => CondominioResource::collection($this->getCondomini()),
            'pianiRate'  => PianoRateResource::collection($pianiRate)->resolve(),
            'meta' => [
                'current_page' => $pianiRate->currentPage(),
                'last_page'    => $pianiRate->lastPage(),
                'per_page'     => $pianiRate->perPage(),
                'total'        => $pianiRate->total(),
            ],
            'filters' => $request->only(['nome']),
        ]);
    }

    public function create(Condominio $condominio, Esercizio $esercizio): Response
    {
        $condomini = $this->getCondomini();

        $esercizi = $condominio->esercizi()->orderBy('data_inizio', 'desc')->get(['id', 'nome', 'stato']);

        $gestioni = Gestione::whereHas('esercizi', fn($q) => $q->where('esercizio_id', $esercizio->id))
            ->with(['esercizi' => fn($q) => $q->where('esercizio_id', $esercizio->id)])
            ->get();

        // --- [MODIFICA] Calcolo Info Saldo per Alert ---
        // Passiamo null per ottenere il saldo GLOBALE del condominio
        $saldoInfo = $this->saldoService->calcolaSaldoApplicabile($condominio, $esercizio, null);
        // ----------------------------------------------

        return Inertia::render('gestionale/pianiRate/PianiRateNew', [
            'condominio' => $condominio,
            'esercizio'  => $esercizio,
            'esercizi'   => $esercizi,
            'condomini'  => $condomini,
            'gestioni'   => $gestioni,
            'saldoInfo'  => $saldoInfo, 
        ]);
    }

    public function store(CreatePianoRateRequest $request, Condominio $condominio, Esercizio $esercizio)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            // Recuperiamo la gestione per i controlli successivi e per il lock
            $gestione = Gestione::findOrFail($validated['gestione_id']);

            $this->pianoRateCreatorService->verificaGestione($validated['gestione_id']);

            // --- [NUOVA MODIFICA V1.9] PROTEZIONE OVERLAP CAPITOLI ---
            if (!empty($validated['capitoli_ids'])) {
                $this->pianoRateCreatorService->convalidaCapitoliUnici(
                    (int) $validated['gestione_id'], 
                    $validated['capitoli_ids']
                );
            }

            // --- [MODIFICA] INIZIO PROTEZIONE SALDO ---
            // Calcoliamo se il saldo è applicabile a livello globale
            $saldoInfo = $this->saldoService->calcolaSaldoApplicabile($condominio, $esercizio, null);

            // BLOCCO PREVENTIVO: Se il saldo NON è applicabile (già usato) MA esiste un importo da spalmare (!= 0),
            // impediamo la creazione per evitare che il service lo applichi di nuovo erroneamente.
            if (!$saldoInfo['applicabile'] && $saldoInfo['saldo'] != 0) {
                DB::rollBack();
                return back()
                    ->withInput()
                    ->with($this->flashError('ATTENZIONE: ' . $saldoInfo['motivo'] . ' Impossibile applicare nuovamente i saldi.'));
            }
            // --- FINE PROTEZIONE SALDO ---

            $pianoRate = $this->pianoRateCreatorService->creaPianoRate($validated, $condominio);

            // 3. INTELLIGENZA V1.9: Gestione Capitoli
            $capitoliIds = $validated['capitoli_ids'] ?? [];

            if (empty($capitoliIds)) {
                // Cerchiamo tutti i capitoli RADICE che non sono ancora assegnati a nessun piano attivo
                $capitoliIds = $gestione->pianoConto->conti()
                    ->whereNull('parent_id')
                    ->whereDoesntHave('pianiRate', function($q) {
                        $q->where('attivo', true);
                    })
                    ->pluck('id')
                    ->toArray();
            }

            $pianoRate->capitoli()->sync($capitoliIds);

            if (!empty($validated['recurrence_enabled'])) {
                $this->pianoRateCreatorService->creaRicorrenza($pianoRate, $validated);
            }

            // --- [MODIFICA] MARCATURA GESTIONE (LOCK) ---
            // Se abbiamo applicato un saldo diverso da zero, marchiamo questa gestione come "proprietaria" dei saldi
            if ($saldoInfo['applicabile'] && $saldoInfo['saldo'] != 0) {
                $this->saldoService->marcaSaldoApplicato($gestione, $saldoInfo['saldo']);
            }
            // -------------------------------------

            $statistiche = [];

            if (!empty($validated['genera_subito'])) {
                $statistiche = app(GeneratePianoRateAction::class)->execute($pianoRate);
            }

            DB::commit();

            return $this->redirectSuccess($condominio, $esercizio, $pianoRate, $validated, $statistiche);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error("Errore creazione piano rate", ['errore' => $e->getMessage()]);

            // [MODIFICA] Uso del trait flashError per consistenza
            return back()->withInput()->with($this->flashError($e->getMessage()));
        }
    }

    /**
     * MODIFICATO SOLO PER AGGIUNGERE $ratePure
     */
    public function show(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate): Response
    {
        $pianoRate->load([
            'rate.rateQuote.anagrafica',
            'rate.rateQuote.immobile',
            'gestione.pianoConto',
            'capitoli.sottoconti'
        ]);

        // --- CALCOLO VOCI ORFANE ---
        $orfani = [];
        if ($pianoRate->gestione && $pianoRate->gestione->pianoConto) {
            
            // Usiamo Eloquent per coerenza
            $orfaniRaw = $pianoRate->gestione->pianoConto->conti()
                ->whereNull('parent_id') // Solo capitoli radice
                // Escludiamo quelli in QUESTO piano O in ALTRI piani attivi
                // whereDoesntHave('pianiRate') esclude QUALSIASI piano attivo collegato, incluso questo.
                ->whereDoesntHave('pianiRate', fn($q) => $q->where('piani_rate.attivo', true))
                ->get();

            $orfani = $orfaniRaw->map(fn($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'importo' => MoneyHelper::fromCents($c->importo)
            ])->values()->toArray();
        }

        $coperturaData = [
            'scoperto_count' => count($orfani),
            'orfani' => $orfani
        ];

        // --- INIZIO LOGICA MIGRAZIONE V1.8 verrà rimosso nella versione 1.9 ---
        $hasLegacyRates = $pianoRate->rate()
            ->whereHas('rateQuote', fn($q) => $q->whereNull('regole_calcolo'))
            ->exists();

        $hasEmittedRates = $pianoRate->rate()
            ->whereHas('rateQuote', fn($q) => $q->whereNotNull('scrittura_contabile_id'))
            ->exists();

        $needsMigration = $hasLegacyRates && !$hasEmittedRates;
        // --- FINE LOGICA MIGRAZIONE ---

        $ratePure = $pianoRate->rate()
            ->orderBy('numero_rata')
            ->get()
            ->map(function($rata) {
                return [
                    'id'            => $rata->id,
                    'numero_rata'   => $rata->numero_rata,
                    'is_emessa'     => $rata->rateQuote()->whereNotNull('scrittura_contabile_id')->exists(),
                    'totale_rata'   => MoneyHelper::fromCents($rata->importo_totale), 
                ];
            });

        return Inertia::render('gestionale/pianiRate/PianiRateShow', [
            'condominio'         => $condominio,
            'esercizio'          => $esercizio,
            'pianoRate'          => new PianoRateResource($pianoRate),
            'ratePure'           => $ratePure, 
            'quotePerAnagrafica' => $this->pianoRateQuoteService->quotePerAnagrafica($pianoRate),
            'quotePerImmobile'   => $this->pianoRateQuoteService->quotePerImmobile($pianoRate),
            'needsMigration'     => $needsMigration,
            'copertura'          => $coperturaData, 
        ]);
    }

    /**
     * [NUOVO METODO]
     */
    public function updateStato(Request $request, Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate)
    {
        $validated = $request->validate([ 'approvato' => 'required|boolean' ]);
        
        $vecchioStato = $pianoRate->stato;
        $nuovoStato = $validated['approvato'] ? StatoPianoRate::APPROVATO : StatoPianoRate::BOZZA;

        // ... controlli di sicurezza (bozza/rate emesse) ...

        $pianoRate->update(['stato' => $nuovoStato]);

        // Ottieni l'utente autenticato con controllo di sicurezza
        $user = Auth::user() ?? throw new \LogicException('User must be authenticated here');

        // LANCIA L'EVENTO
        // Laravel gestirà i Listener (e quindi i Job in coda) automaticamente
        PianoRateStatusUpdated::dispatch(
            $condominio, 
            $esercizio,
            $pianoRate, 
            $user,
            $vecchioStato, 
            $nuovoStato
        );

        return back()->with($this->flashSuccess('Stato aggiornato con successo.'));
    }

    public function destroy(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate): RedirectResponse
    {
        try {
            $pianoRate->delete();
            return to_route('admin.gestionale.esercizi.piani-rate.index', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoRate' => $pianoRate->id
                ])->with($this->flashSuccess(__('gestionale.success_delete_piano_rate')));
        } catch (\Throwable $e) {
            Log::error('Error deleting piano rate', ['message' => $e->getMessage()]);
            return to_route('admin.gestionale.esercizi.piani-rate.index', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoRate' => $pianoRate->id
                ])->with($this->flashError(__('gestionale.error_delete_piano_rate')));
        }
    }

    protected function redirectSuccess(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate, array $validated, array $statistiche = []) {
        $message = $validated['genera_subito'] ? "Piano rate creato e generato!" : "Piano rate creato!";
        return redirect()->route('admin.gestionale.esercizi.piani-rate.show', [
            'condominio' => $condominio->id,
            'esercizio'  => $esercizio->id,
            'pianoRate'  => $pianoRate->id
        ])->with('success', $message);
    }

    /**
     * Rimuove un capitolo di spesa dal piano rate e ricalcola.
     */
    public function detachCapitolo(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate, $capitoloId)
    {
        // 1. BLOCCO TOTALE: Pagamenti
        $haPagamenti = $pianoRate->rate()
            ->whereHas('rateQuote', fn($q) => $q->where('importo_pagato', '>', 0))
            ->exists();

        if ($haPagamenti) {
            return back()->with($this->flashError(
                "Impossibile modificare le voci di spesa: ci sono rate con incassi registrati."
            ));
        }

        // 2. BLOCCO SOFT: Emissioni
        $haEmissioni = $pianoRate->rate()
            ->whereHas('rateQuote', fn($q) => $q->whereNotNull('scrittura_contabile_id'))
            ->exists();

        if ($haEmissioni) {
            return back()->with($this->flashError(
                "Annulla le emissioni contabili delle rate prima di rimuovere voci di spesa."
            ));
        }

        try {
            DB::beginTransaction();

            // Scolleghiamo la voce (detach rimuove la riga dalla pivot)
            $pianoRate->capitoli()->detach($capitoloId);

            // RIGENERAZIONE OBBLIGATORIA
            // Cancelliamo le rate vecchie (sicuro perché siamo in bozza)
            $pianoRate->rate()->delete();
            
            // Rigeneriamo il piano con le voci rimanenti
            app(GeneratePianoRateAction::class)->execute($pianoRate);

            DB::commit();

            return back()->with($this->flashSuccess("Voce di spesa rimossa e piano ricalcolato correttamente."));

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore detach capitolo", ['msg' => $e->getMessage()]);
            return back()->with($this->flashError("Errore durante l'operazione: " . $e->getMessage()));
        }
    }
}