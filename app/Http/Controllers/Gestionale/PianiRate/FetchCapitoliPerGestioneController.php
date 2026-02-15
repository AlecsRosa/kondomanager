<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Models\Gestione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchCapitoliPerGestioneController extends Controller
{
    public function __invoke(Condominio $condominio, Request $request): JsonResponse
    {
        try {
            $request->validate(['gestione_id' => 'required|integer|exists:gestioni,id']);
            $gestioneId = $request->input('gestione_id');
            $currentPlanId = $request->input('piano_rate_id');

            $gestione = Gestione::with('pianoConto')->findOrFail($gestioneId);
            if ($gestione->condominio_id !== $condominio->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if (!$gestione->pianoConto) return response()->json([]);

            // 1. Recuperiamo tutti i conti con le relazioni gerarchiche
            $conti = $gestione->pianoConto->conti()
                ->with(['parent', 'sottoconti', 'pianiRate' => function($q) {
                    $q->where('piani_rate.attivo', true);
                }])->get();

            // 2. Prepariamo una lista di tutti gli ID già impegnati in piani attivi 
            // (escludendo il piano corrente se siamo in modifica)
            $idImpegnati = DB::table('piano_rate_capitoli')
                ->join('piani_rate', 'piano_rate_capitoli.piano_rate_id', '=', 'piani_rate.id')
                ->where('piani_rate.gestione_id', $gestioneId)
                ->where('piani_rate.attivo', true)
                ->when($currentPlanId, fn($q) => $q->where('piani_rate.id', '!=', $currentPlanId))
                ->pluck('conto_id')
                ->toArray();

            // 3. Mappatura Gerarchica
            $capitoli = $conti->map(function($c) use ($idImpegnati) {
                
                // Un conto è disabilitato se:
                // - È impegnato lui stesso
                // - È impegnato uno dei suoi antenati (Padre, Nonno...)
                // - È impegnato uno dei suoi discendenti (Figlio, Nipote...)
                
                $ramoIds = array_merge(
                    [$c->id], 
                    $c->getAllAncestorsIds(), 
                    $c->getAllChildrenIds()
                );

                // Verifichiamo se c'è un'intersezione tra il ramo e gli ID impegnati
                $conflittoIds = array_intersect($ramoIds, $idImpegnati);
                $isDisabled = !empty($conflittoIds);

                return [
                    'id' => $c->id,
                    'nome' => $c->parent_id ? "{$c->parent->nome} > {$c->nome}" : "[CAPITOLO] {$c->nome}",
                    'disabled' => $isDisabled,
                    'note' => $isDisabled ? "Voce o ramo già impegnato in un altro piano attivo" : ""
                ];
            });

            return response()->json($capitoli);

        } catch (\Exception $e) {
            Log::error('Errore fetch capitoli gerarchico', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Errore interno'], 500);
        }
    }
}