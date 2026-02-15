<?php

namespace App\Http\Controllers\Gestionale\PianiConti\Conti;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestionale\PianoConto\Conto\CreateContoRequest;
use App\Http\Requests\Gestionale\PianoConto\Conto\UpdateContoRequest;
use App\Models\Condominio;
use App\Models\Esercizio;
use App\Models\Gestionale\Conto;
use App\Models\Gestionale\PianoConto;
use App\Models\Tabella;
use App\Traits\HandleFlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContoController extends Controller
{
     use HandleFlashMessages;

    /**
     * Create a new expense item (Conto) within a specific chart of accounts.
     *
     * This method handles the creation of both "Capitoli" (parent groups) and "Voci di Spesa" (child items).
     * It manages amount conversion (cents), hierarchy linking, and automatic millesimal table association.
     *
     * @param CreateContoRequest $request Validated form request data.
     * @param Condominio $condominio Current condominium context.
     * @param Esercizio $esercizio Current fiscal year context.
     * @param PianoConto $pianoConto Parent chart of accounts.
     * * @return RedirectResponse Redirects back to the chart view with success/error flash.
     */
    public function store(CreateContoRequest $request, Condominio $condominio, Esercizio $esercizio, PianoConto $pianoConto): RedirectResponse
    {
        try {

            DB::beginTransaction();

            $data = $request->validated();
            $isCapitolo = $data['isCapitolo'];
            $isSottoConto = $data['isSottoConto'];
                
            // Prepara i dati per la creazione
            $contoData = [
                'piano_conto_id' => $pianoConto->id,
                'parent_id'      => $isSottoConto ? ($data['parent_id'] ?? null) : null,
                'nome'           => $data['nome'],
                'descrizione'    => $data['descrizione'] ?? null,
                'tipo'           => $data['tipo'],
                'importo'        => $isCapitolo ? 0 : MoneyHelper::toCents($data['importo']), 
                'note'           => $data['note'] ?? null,
                'attivo'         => true,
            ];

            // Crea il conto
            $nuovoConto = Conto::create($contoData);

            // Se non è un capitolo, gestisci le ripartizioni millesimali
            if (!$isCapitolo) {
                
                // Se è stata selezionata una tabella specifica, usiamo quella
                if (!empty($data['tabella_millesimale_id'])) {
                    $tabella = Tabella::where('id', $data['tabella_millesimale_id'])
                        ->where('condominio_id', $condominio->id)
                        ->first();
                    
                    if (!$tabella) {
                        throw new \Exception('Tabella millesimale selezionata non trovata');
                    }
                } 

                $contoTabellaId = DB::table('conto_tabella_millesimale')->insertGetId([
                    'conto_id'     => $nuovoConto->id, 
                    'tabella_id'   => $tabella->id,
                    'coefficiente' => 100.00, 
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $ripartizioni = [
                    [
                        'soggetto' => 'proprietario',
                        'percentuale' => $data['percentuale_proprietario']
                    ],
                    [
                        'soggetto' => 'inquilino', 
                        'percentuale' => $data['percentuale_inquilino']
                    ],
                    [
                        'soggetto' => 'usufruttuario',
                        'percentuale' => $data['percentuale_usufruttuario']
                    ]
                ];

                // Verifica che la somma delle percentuali sia 100
                $sommaPercentuali = array_sum(array_column($ripartizioni, 'percentuale'));
                if ($sommaPercentuali != 100) {
                    throw new \Exception("La somma delle percentuali deve essere 100%. Attuale: {$sommaPercentuali}%");
                }

                // Crea le ripartizioni per ogni soggetto
                foreach ($ripartizioni as $ripartizione) {
                    if ($ripartizione['percentuale'] > 0) {
                        DB::table('conto_tabella_ripartizioni')->insert([
                            'conto_tabella_millesimale_id' => $contoTabellaId,
                            'soggetto'                     => $ripartizione['soggetto'],
                            'percentuale'                  => $ripartizione['percentuale'],
                            'created_at'                   => now(),
                            'updated_at'                   => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id
                ])
                ->with($this->flashSuccess(__('gestionale.success_create_conto')));

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Errore durante la creazione della voce di spesa:', [
                'condominio_id' => $condominio->id,
                'esercizio_id'  => $esercizio->id,
                'conto_id'      => $pianoConto->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString()
            ]);

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id
                ])
                ->with($this->flashError(__('gestionale.error_create_conto')));

        }
    }

    /**
     * Update an existing expense item.
     *
     * Updates details, hierarchy position, and amounts.
     *
     * @param UpdateContoRequest $request Validated update data.
     * @param Condominio $condominio Context.
     * @param Esercizio $esercizio Context.
     * @param PianoConto $pianoConto Context.
     * @param Conto $conto The item to update.
     */
    public function update(UpdateContoRequest $request, Condominio $condominio, Esercizio $esercizio, PianoConto $pianoConto, Conto $conto): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $isCapitolo = $data['isCapitolo'];
            $isSottoConto = $data['isSottoConto'];
                
            // Prepara i dati per l'aggiornamento
            $contoData = [
                'parent_id'      => $isSottoConto ? ($data['parent_id'] ?? null) : null,
                'nome'           => $data['nome'],
                'descrizione'    => $data['descrizione'] ?? null,
                'tipo'           => $data['tipo'],
                'importo'        => $isCapitolo ? 0 : MoneyHelper::toCents($data['importo']), 
                'note'           => $data['note'] ?? null,
            ];

            $conto->update($contoData);

            DB::commit();

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id
                ])
                ->with($this->flashSuccess(__('gestionale.success_update_conto')));

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Errore durante l\'aggiornamento della voce di spesa:', [
                'condominio_id' => $condominio->id,
                'esercizio_id'  => $esercizio->id,
                'piano_conto_id' => $pianoConto->id,
                'conto_id'      => $conto->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString()
            ]);

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id
                ])
                ->with($this->flashError(__('gestionale.error_update_conto')));
        }
    }

    /**
     * Delete an expense item (Conto).
     * [V1.9 SECURITY UPDATE]
     * Includes strict integrity checks to prevent "Phantom References" in accounting:
     * 1. Hierarchy Check: Prevents deletion of Parent items that still have Children.
     * 2. Active Plan Check: Prevents deletion of items currently anchored to an active Installment Plan.
     *
     * @param Condominio $condominio
     * @param Esercizio $esercizio
     * @param PianoConto $pianoConto
     * @param Conto $conto The item to delete.
     * @return RedirectResponse
     * @version 1.9.0
     */

    public function destroy(Condominio $condominio, Esercizio $esercizio, PianoConto $pianoConto, Conto $conto): RedirectResponse
    {

    // 1. [HIERARCHY CHECK] Block if children exist
        if ($conto->sottoconti()->exists()) {

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio' => $esercizio->id,
                    'pianoConto' => $pianoConto->id,
                ])
                ->with($this->flashError(__('gestionale.error_conto_has_sottoconti')));
                
        }

        // 2. [INTEGRITY CHECK V1.9] Controllo Piani Rate (Double Lock)
        // This prevents the "Database Corruption" scenario where a Plan Rate points to a deleted ID.
        
        // A. Controllo DIRETTO: Il conto specifico è usato in un piano attivo?
        // (Es. Ho aggiunto "Pulizie Scale" direttamente al piano)
        $pianiDiretti = $conto->pianiRate()
            ->where('piani_rate.attivo', true)
            ->pluck('nome')
            ->toArray();

        // B. Controllo EREDITATO (Il Fix che mancava): Il PADRE è usato in un piano attivo?
        // (Es. Ho aggiunto "Spese Generali" al piano -> "Pulizie Scale" è bloccato di conseguenza)
        $pianiEreditati = [];
        
        if ($conto->parent_id) {
            // Recuperiamo il padre
            $parent = $conto->parent; // Usa la relazione definita nel model
            
            if ($parent) {
                $pianiEreditati = $parent->pianiRate()
                    ->where('piani_rate.attivo', true)
                    ->pluck('nome')
                    ->toArray();
            }
        }

        // Uniamo i risultati. Se c'è anche solo un piano in una delle due liste, BLOCCA.
        $tuttiPianiCoinvolti = array_unique(array_merge($pianiDiretti, $pianiEreditati));

        if (!empty($tuttiPianiCoinvolti)) {
            
            $listaPiani = implode(', ', $tuttiPianiCoinvolti);

            // Logica dei messaggi basata sulla provenienza del blocco
            if (!empty($pianiEreditati)) {
                // MESSAGGIO EREDITATO
                $msg = __('gestionale.error_conto_inherited_lock', [
                    'parent' => $parent->nome,
                    'plans'  => $listaPiani
                ]);
            } else {
                // MESSAGGIO DIRETTO
                $msg = __('gestionale.error_conto_used_in_active_plans', [
                    'plans' => $listaPiani
                ]);
            }

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio' => $esercizio->id,
                    'pianoConto' => $pianoConto->id,
                ])
                ->with($this->flashError($msg));
        }

        try {
            DB::beginTransaction();

            // Detach Millesimal Tables (Clean Pivot)
            // Assuming relationships are defined in Conto model: public function tabelle() { ... }
            $conto->tabelle()->detach(); 

            // Delete the item
            $conto->delete();

            DB::commit();

            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id,
                ])
                ->with($this->flashSuccess(__('gestionale.success_delete_conto')));

        } catch (\Exception $e) {
            
            DB::rollBack();
            
            Log::error("Errore durante l'eliminazione della voce di spesa:", [
                'condominio_id' => $condominio->id,
                'esercizio_id'  => $esercizio->id,
                'pianoConto'    => $pianoConto->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString()
            ]);
            
            return to_route('admin.gestionale.esercizi.piani-conti.show', [
                    'condominio' => $condominio->id,
                    'esercizio'  => $esercizio->id,
                    'pianoConto' => $pianoConto->id,
                ])
                ->with($this->flashError(__('gestionale.error_delete_conto')));
        }

    }

}
