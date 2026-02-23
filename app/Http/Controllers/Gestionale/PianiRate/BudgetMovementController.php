<?php

namespace App\Http\Controllers\Gestionale\PianiRate;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Models\Gestionale\Conto;
use App\Models\Gestionale\PianoRate;
use App\Services\Gestionale\BudgetMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BudgetMovementController extends Controller
{
    public function __construct(
        protected BudgetMovementService $budgetService
    ) {}

    /**
     * Esegue lo spostamento di budget.
     * Route: admin.gestionale.piani-rate.move-budget
     */
    public function store(Request $request, Condominio $condominio, PianoRate $pianoRate)
    {
        // 1. Validazione
        $validated = $request->validate([
            'source_id' => 'required|exists:conti,id',
            'destination_id' => 'required|exists:conti,id|different:source_id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        try {
            // 2. Conversione sicura in centesimi (Money Pattern)
            $amountCents = MoneyHelper::toCents($validated['amount']);
            
            $userId = Auth::id();

            // 3. Verifica Coerenza Piano dei Conti (Security)
            $source = Conto::findOrFail($validated['source_id']);
            $dest = Conto::findOrFail($validated['destination_id']);

            if ($source->piano_conto_id !== $dest->piano_conto_id) {
                 return back()->withErrors(['destination_id' => 'Sorgente e Destinazione devono appartenere allo stesso Piano dei Conti.']);
            }

            // 4. Esecuzione tramite Service
            $this->budgetService->moveBudget(
                $pianoRate,
                $validated['source_id'],
                $validated['destination_id'],
                $amountCents,
                $validated['reason'],
                $userId
            );

            return back()->with('success', 'Budget riallocato con successo.');

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error("Budget Movement Error: " . $e->getMessage());
            return back()->withErrors(['amount' => 'Errore di sistema: ' . $e->getMessage()]);
        }
    }
}