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
use App\Models\Gestionale\Conto;
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
            'condominio' => $condominio, 'esercizio' => $esercizio, 'esercizi' => $esercizi,
            'condomini' => CondominioResource::collection($this->getCondomini()),
            'pianiRate' => PianoRateResource::collection($pianiRate)->resolve(),
            'meta' => ['current_page' => $pianiRate->currentPage(), 'last_page' => $pianiRate->lastPage(), 'per_page' => $pianiRate->perPage(), 'total' => $pianiRate->total()],
            'filters' => $request->only(['nome']),
        ]);
    }

    public function create(Condominio $condominio, Esercizio $esercizio): Response
    {
        $condomini = $this->getCondomini();
        $esercizi = $condominio->esercizi()->orderBy('data_inizio', 'desc')->get(['id', 'nome', 'stato']);
        $gestioni = Gestione::whereHas('esercizi', fn($q) => $q->where('esercizio_id', $esercizio->id))
            ->with(['esercizi' => fn($q) => $q->where('esercizio_id', $esercizio->id)])->get();
        $saldoInfo = $this->saldoService->calcolaSaldoApplicabile($condominio, $esercizio, null);
        return Inertia::render('gestionale/pianiRate/PianiRateNew', [
            'condominio' => $condominio, 'esercizio' => $esercizio, 'esercizi' => $esercizi,
            'condomini' => $condomini, 'gestioni' => $gestioni, 'saldoInfo' => $saldoInfo, 
        ]);
    }

    public function store(CreatePianoRateRequest $request, Condominio $condominio, Esercizio $esercizio)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // 1. Recupero Gestione
            $gestione = Gestione::findOrFail($validated['gestione_id']);
            $this->pianoRateCreatorService->verificaGestione($validated['gestione_id']);

            // 2. Protezione Overlap (Disabilitata per V1.9)
            // if (!empty($validated['capitoli_ids'])) { ... }

            // 3. RECUPERO E DIAGNOSTICA SALDI
            $saldoInfo = $this->saldoService->calcolaSaldoApplicabile($condominio, $esercizio, null);
            
            $haMovimenti = $saldoInfo['has_movimenti'] ?? false;
            if (!$haMovimenti && $saldoInfo['saldo'] == 0) {
                // Fallback: se il service non ha rilevato movimenti ma saldo è 0, controlliamo manualmente
                $esisteManuale = DB::table('saldi')
                    ->where('condominio_id', $condominio->id)
                    ->where('esercizio_id', $esercizio->id)
                    ->where('saldo_iniziale', '!=', 0)
                    ->exists();
                if ($esisteManuale) $haMovimenti = true;
            }

            Log::info("PianoRate Store Debug:", [
                'gestione_id' => $gestione->id,
                'applicabile' => $saldoInfo['applicabile'],
                'has_movimenti' => $haMovimenti
            ]);

            // VARIABILE DECISIONALE
            $applicareSaldi = ($saldoInfo['applicabile'] && $haMovimenti);

            // 4. Creazione Piano
            $pianoRate = $this->pianoRateCreatorService->creaPianoRate($validated, $condominio);

            // 5. Gestione Capitoli
            $capitoliConfig = $validated['capitoli_config'] ?? [];
            $syncData = [];

            if (!empty($capitoliConfig)) {
                foreach ($capitoliConfig as $conf) {
                    $importoCents = (isset($conf['importo']) && $conf['importo'] !== '') ? MoneyHelper::toCents($conf['importo']) : null;
                    $syncData[$conf['id']] = ['importo' => $importoCents, 'note' => $conf['note'] ?? null];
                }
            } elseif (!empty($validated['capitoli_ids'])) {
                $conti = Conto::findMany($validated['capitoli_ids']);
                foreach ($conti as $c) {
                    $syncData[$c->id] = ['importo' => $c->importo, 'note' => 'Selezione rapida (Intero)'];
                }
            } else {
                $capitoliIds = $gestione->pianoConto->conti()
                    ->whereNull('parent_id')
                    ->whereDoesntHave('pianiRate', function($q) { $q->where('attivo', true); })->get();
                foreach ($capitoliIds as $c) {
                    $syncData[$c->id] = ['importo' => $c->importo, 'note' => 'Inclusione automatica'];
                }
            }
            $pianoRate->capitoli()->sync($syncData);

            // 6. Ricorrenza
            if (!empty($validated['recurrence_enabled'])) {
                $this->pianoRateCreatorService->creaRicorrenza($pianoRate, $validated);
            }

            // 7. APPLICAZIONE SALDI (Aggiornamento DB)
            if ($applicareSaldi) {
                Log::info("PianoRateController: Applicazione saldi in corso per gestione {$gestione->id}");
                $this->saldoService->marcaSaldoApplicato($gestione, $saldoInfo['saldo']);
                $gestione->refresh();
                $pianoRate->setRelation('gestione', $gestione);
            }

            // 8. GENERAZIONE RATE (IL FIX FINALE)
            $statistiche = [];
            if (!empty($validated['genera_subito'])) {
                // PASSAGGIO ESPLICITO DEL BOOLEANO: TRUE o FALSE.
                // Se $applicareSaldi è false, l'Action ignorerà i saldi anche se il DB dice "True".
                $statistiche = app(GeneratePianoRateAction::class)->execute($pianoRate, $applicareSaldi);
            }

            DB::commit();
            return $this->redirectSuccess($condominio, $esercizio, $pianoRate, $validated, $statistiche);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore store piano rate", ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withInput()->with($this->flashError($e->getMessage()));
        }
    }

    // ... (metodi successivi show, updateStato, destroy, etc. uguali) ...
    public function show(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate): Response
    {
        $pianoRate->load(['rate.rateQuote.anagrafica', 'rate.rateQuote.immobile', 'gestione.pianoConto', 'capitoli.sottoconti']);
        $orfani = [];
        if ($pianoRate->gestione && $pianoRate->gestione->pianoConto) {
            $orfaniRaw = $pianoRate->gestione->pianoConto->conti()
                ->whereNull('parent_id')
                ->whereDoesntHave('pianiRate', fn($q) => $q->where('piani_rate.attivo', true))
                ->where('id', '!=', $pianoRate->capitoli->pluck('id')->toArray()) // Fix V1.9
                ->get();
            $orfani = $orfaniRaw->map(fn($c) => ['id' => $c->id, 'nome' => $c->nome, 'importo' => $c->importo])->values()->toArray();
        }
        $coperturaData = ['scoperto_count' => count($orfani), 'orfani' => $orfani];
        $ratePure = $pianoRate->rate()->orderBy('numero_rata')->get()->map(function($rata) {
            return ['id' => $rata->id, 'numero_rata' => $rata->numero_rata, 'is_emessa' => $rata->rateQuote()->whereNotNull('scrittura_contabile_id')->exists(), 'totale_rata' => MoneyHelper::fromCents($rata->importo_totale)];
        });
        return Inertia::render('gestionale/pianiRate/PianiRateShow', [
            'condominio' => $condominio, 'esercizio' => $esercizio, 'pianoRate' => new PianoRateResource($pianoRate),
            'ratePure' => $ratePure, 'quotePerAnagrafica' => $this->pianoRateQuoteService->quotePerAnagrafica($pianoRate),
            'quotePerImmobile' => $this->pianoRateQuoteService->quotePerImmobile($pianoRate),
            'needsMigration' => false, 'copertura' => $coperturaData,
        ]);
    }

    public function updateStato(Request $request, Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate)
    {
        $validated = $request->validate([ 'approvato' => 'required|boolean' ]);
        $vecchioStato = $pianoRate->stato;
        $nuovoStato = $validated['approvato'] ? StatoPianoRate::APPROVATO : StatoPianoRate::BOZZA;
        $pianoRate->update(['stato' => $nuovoStato]);
        PianoRateStatusUpdated::dispatch($condominio, $esercizio, $pianoRate, Auth::user(), $vecchioStato, $nuovoStato);
        return back()->with($this->flashSuccess('Stato aggiornato con successo.'));
    }

    public function destroy(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate): RedirectResponse
    {
        try {
            $pianoRate->delete();
            return to_route('admin.gestionale.esercizi.piani-rate.index', ['condominio' => $condominio->id, 'esercizio' => $esercizio->id, 'pianoRate' => $pianoRate->id])->with($this->flashSuccess(__('gestionale.success_delete_piano_rate')));
        } catch (\Throwable $e) {
            return to_route('admin.gestionale.esercizi.piani-rate.index', ['condominio' => $condominio->id, 'esercizio' => $esercizio->id, 'pianoRate' => $pianoRate->id])->with($this->flashError(__('gestionale.error_delete_piano_rate')));
        }
    }

    protected function redirectSuccess(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate, array $validated, array $statistiche = []) {
        $message = $validated['genera_subito'] ? "Piano rate creato e generato!" : "Piano rate creato!";
        return redirect()->route('admin.gestionale.esercizi.piani-rate.show', ['condominio' => $condominio->id, 'esercizio' => $esercizio->id, 'pianoRate' => $pianoRate->id])->with('success', $message);
    }

    public function detachCapitolo(Condominio $condominio, Esercizio $esercizio, PianoRate $pianoRate, $capitoloId)
    {
        if ($pianoRate->rate()->whereHas('rateQuote', fn($q) => $q->where('importo_pagato', '>', 0))->exists()) return back()->with($this->flashError("Impossibile modificare: ci sono incassi."));
        if ($pianoRate->rate()->whereHas('rateQuote', fn($q) => $q->whereNotNull('scrittura_contabile_id'))->exists()) return back()->with($this->flashError("Annulla le emissioni prima."));
        try {
            DB::beginTransaction();
            $pianoRate->capitoli()->detach($capitoloId);
            $pianoRate->rate()->delete();
            // Passiamo NULL per default (l'Action userà il flag DB, che è corretto per update)
            app(GeneratePianoRateAction::class)->execute($pianoRate, null); 
            DB::commit();
            return back()->with($this->flashSuccess("Voce rimossa e ricalcolata."));
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with($this->flashError("Errore: " . $e->getMessage()));
        }
    }
}