<?php

namespace App\Http\Controllers\Gestionale\Movimenti;

use App\Actions\Gestionale\Movimenti\StoreIncassoRateAction;
use App\Actions\Gestionale\Movimenti\StornoIncassoRateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestionale\Movimenti\StoreIncassoRateRequest;
use App\Models\Condominio;
use App\Models\Anagrafica;
use App\Models\Evento;
use App\Models\Immobile;
use App\Models\Gestionale\Cassa;
use App\Models\Gestionale\ScritturaContabile;
use App\Services\Gestionale\IncassoRateService;
use App\Traits\HandleFlashMessages;
use App\Traits\HasEsercizio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class IncassoRateController extends Controller
{
    use HandleFlashMessages, HasEsercizio;

    public function __construct(
        private IncassoRateService $incassoService
    ) {}

    public function index(Request $request, Condominio $condominio)
    {
        $query = $this->incassoService->getIncassiQuery(
            $condominio,
            $request->input('search')
        );

        $movimenti = $query->paginate(config('pagination.default_per_page'))
            ->withQueryString()
            ->through(fn($mov) => $this->incassoService->formatMovimentoForFrontend($mov));

        $condominiList = Anagrafica::whereHas('immobili', fn($q) => 
            $q->where('condominio_id', $condominio->id)
        )->orderBy('nome')->get();
        
        $esercizio = $this->getEsercizioCorrente($condominio);

        return Inertia::render('gestionale/movimenti/incassi/IncassoRateList', [
            'condominio' => $condominio,
            'movimenti'  => $movimenti,
            'condomini'  => $condominiList,
            'esercizio'  => $esercizio,
            'filters'    => $request->all(['search']),
        ]);
    }

    public function create(Condominio $condominio)
    {
        $risorse = Cassa::where('condominio_id', $condominio->id)
            ->whereIn('tipo', ['banca', 'contanti'])
            ->where('attiva', true)
            ->with('contoCorrente')
            ->get();

        $condomini = Anagrafica::whereHas('immobili', fn($q) => $q->where('condominio_id', $condominio->id))
            ->orderBy('nome')->get()->map(fn($a) => ['id' => $a->id, 'label' => $a->nome]);

        $immobili = Immobile::where('condominio_id', $condominio->id)
            ->orderBy('interno')->get()
            ->map(fn($i) => ['id' => $i->id, 'label' => "Int. $i->interno" . ($i->descrizione ? " - $i->descrizione" : "") . " ($i->nome)"]);

        $esercizio = $this->getEsercizioCorrente($condominio);
        
        $gestioni = $esercizio 
            ? $esercizio->gestioni()->select('gestioni.id', 'gestioni.nome', 'gestioni.tipo')->orderBy('gestioni.tipo')->get() 
            : [];

        return Inertia::render('gestionale/movimenti/incassi/IncassoRateNew', [
            'condominio' => $condominio,
            'esercizio'  => $esercizio,
            'risorse'    => $risorse,
            'condomini'  => $condomini,
            'immobili'   => $immobili,
            'gestioni'   => $gestioni,
        ]);
    }

    public function store(StoreIncassoRateRequest $request, Condominio $condominio, StoreIncassoRateAction $action) 
    {
        // 1. Esegui l'azione di business (registra soldi)
        $action->execute($request->validated(), $condominio, $this->getEsercizioCorrente($condominio));

        // 2. LOGICA CHIUSURA TASK (Action Inbox)
        $relatedTaskId = $request->input('related_task_id');

        if ($relatedTaskId) {
            // Cerchiamo il task admin
            $task = Evento::find($relatedTaskId);
            
            // Se esiste e non è già chiuso (per evitare doppi aggiornamenti)
            if ($task && !$task->is_completed) {
                
                // A. Chiudiamo il task Admin (Sparisce dalla Inbox)
                $task->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
                
                // B. Aggiorniamo l'evento originale dell'utente (Feedback Verde)
                // Recuperiamo l'ID dell'evento utente dal contesto del task admin
                $userEventId = $task->meta['context']['related_event_id'] ?? null;
                
                if ($userEventId) {
                    $userEvent = Evento::find($userEventId);
                    if ($userEvent) {
                        $userMeta = $userEvent->meta;
                        $userMeta['status'] = 'paid'; // Diventa verde per l'utente
                        // Salviamo anche l'importo effettivamente incassato se diverso
                        $userMeta['importo_pagato'] = $request->input('importo_totale'); 
                        // FIX: Azzeriamo il restante perché è pagato!
                        $userMeta['importo_restante'] = 0;
                        
                        $userEvent->update(['meta' => $userMeta]);
                    }
                }

                // C. PURGE CACHE: Aggiorniamo subito il contatore della Inbox
                // Usiamo l'ID dell'utente corrente (Admin) che ha appena completato l'azione
                Cache::forget('inbox_count_' . $request->user()->id);
            }
        }

        return to_route('admin.gestionale.movimenti-rate.index', $condominio)
            ->with($this->flashSuccess('Incasso registrato con successo.'));
    }
    
    public function storno(Request $request, Condominio $condominio, ScritturaContabile $scrittura, StornoIncassoRateAction $action) 
    {
        if ($scrittura->stato === 'annullata') {
            return back();
        }

        $action->execute($scrittura, $condominio);

        return back()->with($this->flashSuccess('Storno completato.'));
    }
}