<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Helpers\MoneyHelper;
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

            // 1. Recuperiamo tutti i conti con i figli
            $conti = $gestione->pianoConto->conti()
                ->with(['parent', 'sottoconti'])
                ->get();

            // 2. Mappiamo TUTTI gli impegni esistenti (Tabella Pivot)
            // Chiave: conto_id => Array di importi impegnati
            $rawImpegni = DB::table('piano_rate_capitoli')
                ->join('piani_rate', 'piano_rate_capitoli.piano_rate_id', '=', 'piani_rate.id')
                ->where('piani_rate.gestione_id', $gestioneId)
                ->where('piani_rate.attivo', true)
                ->when($currentPlanId, fn($q) => $q->where('piani_rate.id', '!=', $currentPlanId))
                ->select('conto_id', 'importo')
                ->get();

            $impegniMap = [];
            foreach ($rawImpegni as $row) {
                $impegniMap[$row->conto_id][] = $row->importo; // Può essere null (Tutto) o int (Parziale)
            }

            // 3. Elaborazione Intelligente
            $capitoli = $conti->map(function($c) use ($impegniMap) {
                
                // A. CALCOLO TOTALE REALE (Gestione Padri vuoti)
                $importoTotale = $c->importo;
                if ($importoTotale == 0 && $c->sottoconti->isNotEmpty()) {
                    $importoTotale = $c->sottoconti->sum('importo');
                }

                $impegnato = 0;
                $isLockedTotally = false;

                // B. CALCOLO IMPEGNATO (Logica Bidirezionale)

                // 1. Controllo Diretto (Io sono nel piano?)
                if (isset($impegniMap[$c->id])) {
                    foreach ($impegniMap[$c->id] as $val) {
                        if ($val === null) {
                            $isLockedTotally = true;
                            $impegnato = $importoTotale;
                        } else {
                            $impegnato += $val;
                        }
                    }
                }

                // 2. Controllo Bottom-Up (I miei FIGLI sono nel piano?)
                // Se sono un padre, devo sommare quanto è stato speso dei miei figli
                if (!$isLockedTotally && $c->sottoconti->isNotEmpty()) {
                    foreach ($c->sottoconti as $figlio) {
                        if (isset($impegniMap[$figlio->id])) {
                            foreach ($impegniMap[$figlio->id] as $val) {
                                // Se il figlio è impegnato con NULL (Tutto), aggiungo il suo intero importo
                                if ($val === null) {
                                    $impegnato += $figlio->importo;
                                } else {
                                    $impegnato += $val;
                                }
                            }
                        }
                    }
                }

                // 3. Controllo Top-Down (Mio PADRE è nel piano?)
                // Se il padre è impegnato, io sono bloccato (eredito il blocco)
                // Nota: In V1.9 semplifichiamo: se papà è bloccato, io sono bloccato totalmente.
                // Per una gestione fine (residuo papà distribuito) servirebbe logica molto complessa.
                if ($c->parent_id && isset($impegniMap[$c->parent_id])) {
                     $isLockedTotally = true;
                     $impegnato = $importoTotale;
                }

                // C. Calcoli Finali
                // Sicurezza: non possiamo impegnare più di quanto abbiamo
                $impegnato = min($impegnato, $importoTotale); 
                $residuo = max(0, $importoTotale - $impegnato);
                
                // Disabilitato se non c'è budget o se è finito
                $isDisabled = ($importoTotale == 0) || ($residuo <= 0);

                $nome = $c->parent_id ? "{$c->parent->nome} > {$c->nome}" : "[CAPITOLO] {$c->nome}";

                return [
                    'id' => $c->id,
                    'nome' => $nome,
                    'importo_totale' => MoneyHelper::fromCents($importoTotale),
                    'impegnato' => MoneyHelper::fromCents($impegnato),
                    'residuo' => MoneyHelper::fromCents($residuo),
                    'disabled' => $isDisabled,
                    'note' => $isDisabled ? "Budget esaurito" : "Disp: € " . MoneyHelper::format($residuo)
                ];
            });

            return response()->json($capitoli->values());

        } catch (\Exception $e) {
            Log::error('Errore fetch capitoli', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Errore interno'], 500);
        }
    }
}