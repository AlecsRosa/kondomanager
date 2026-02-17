<?php

namespace App\Http\Controllers\Gestionale\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Services\Gestionale\BudgetCoverageService;
use App\Traits\HasCondomini;
use App\Traits\HasEsercizio;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use HasCondomini, HasEsercizio;

    public function __invoke(Condominio $condominio, BudgetCoverageService $coverageService): Response
    {
        $esercizio = $this->getEsercizioCorrente($condominio);
        $copertura = null;

        if ($esercizio) {
            $esercizio->load('gestioni');
            $totPrev = 0; $totPian = 0; $vociScoperte = [];

            foreach ($esercizio->gestioni as $gestione) {
                $report = $coverageService->analyze($gestione);
                $totPrev += $report['totali']['budget'];
                $totPian += $report['totali']['pianificato'];

                foreach ($report['items'] as $item) {
                    if (!($item['is_leaf'] ?? false)) continue;

                    $mancanteVoce = $item['budget'] - $item['pianificato'];
                    
                    if ($mancanteVoce > 100) {
                        // LOGICA: Mostriamo nella modale se mancano soldi.
                        // Grazie alla logica a cascata nel Service, il deficit apparirà 
                        // solo sulle voci che non hanno ricevuto copertura.
                        $vociScoperte[] = [
                            'id'       => $item['id'],
                            'nome'     => $item['nome'],
                            'importo'  => $mancanteVoce, 
                            'gestione' => $gestione->nome
                        ];
                    }
                }
            }

            $delta = $totPrev - $totPian;
            $copertura = [
                'preventivo' => $totPrev, 'pianificato' => $totPian, 'delta' => $delta,
                'scoperto' => ($delta > 0 ? $delta : 0),
                'percentuale' => $totPrev > 0 ? round(($totPian / $totPrev) * 100) : 0,
                'is_completo' => abs($delta) <= 100,
                'orfani' => $vociScoperte, 'scoperto_count' => count($vociScoperte)
            ];
        }

        return Inertia::render('gestionale/dashboard/Dashboard', [
            'condominio' => $condominio, 'condomini' => $this->getCondomini(),
            'esercizio' => $esercizio, 'copertura' => $copertura
        ]);
    }
}