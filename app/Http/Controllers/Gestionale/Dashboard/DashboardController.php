<?php

namespace App\Http\Controllers\Gestionale\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Traits\HasCondomini;
use App\Traits\HasEsercizio;
use Inertia\Inertia;
use Inertia\Response;
use App\Helpers\MoneyHelper;

class DashboardController extends Controller
{
    use HasCondomini, HasEsercizio;

    /**
     * Gestisce la dashboard principale del gestionale per un condominio.
     */
    public function __invoke(Condominio $condominio): Response
    {
        $condomini = $this->getCondomini();
        $esercizio = $this->getEsercizioCorrente($condominio);

        // --- INIZIO LOGICA COPERTURA PREVENTIVO ---
        $copertura = null;
        
        if ($esercizio) {
            // Carichiamo le relazioni necessarie per evitare N+1 queries
            $esercizio->load([
                'gestioni.pianoConto.conti', 
                'gestioni.pianiRate.capitoli'
            ]);
            
            $totalePreventivo = 0;
            $totalePianificato = 0;
            $capitoliOrfani = [];

            foreach ($esercizio->gestioni as $gestione) {
                if (!$gestione->pianoConto) continue;

                // 1. Calcolo del preventivo totale della gestione (Solo Capitoli Radice)
                $preventivoGestione = $gestione->pianoConto->conti()
                    ->whereNull('parent_id')
                    ->sum('importo');
                
                $totalePreventivo += $preventivoGestione;

                // 2. Calcolo del pianificato per questa gestione
                foreach ($gestione->pianiRate as $piano) {
                    // Sommiamo gli importi dei conti ancorati a questo piano
                    $totalePianificato += $piano->capitoli->sum('importo');
                }

                // 3. Identificazione analitica dei capitoli "Orfani" (scoperti)
                // Cerchiamo i capitoli radice che non sono associati a NESSUN piano rate attivo
                $orfani = $gestione->pianoConto->conti()
                    ->whereNull('parent_id')
                    ->whereDoesntHave('pianiRate', function($q) {
                        $q->where('piani_rate.attivo', true);
                    })
                    ->get();

                foreach ($orfani as $o) {
                    $capitoliOrfani[] = [
                        'id'       => $o->id,
                        'nome'     => $o->nome,
                        'importo'  => MoneyHelper::fromCents($o->importo),
                        'gestione' => $gestione->nome
                    ];
                }
            }

            $scopertoCentesimi = $totalePreventivo - $totalePianificato;
            
            $copertura = [
                'preventivo'     => MoneyHelper::fromCents($totalePreventivo),
                'pianificato'    => MoneyHelper::fromCents($totalePianificato),
                'scoperto'       => MoneyHelper::fromCents($scopertoCentesimi > 0 ? $scopertoCentesimi : 0),
                'percentuale'    => $totalePreventivo > 0 ? min(100, round(($totalePianificato / $totalePreventivo) * 100)) : 0,
                'is_completo'    => $totalePianificato >= $totalePreventivo && $totalePreventivo > 0,
                'orfani'         => $capitoliOrfani,
                'scoperto_count' => count($capitoliOrfani)
            ];
        }
        // --- FINE LOGICA COPERTURA ---

        return Inertia::render('gestionale/dashboard/Dashboard', [
            'condominio' => $condominio,
            'condomini'  => $condomini,
            'esercizio'  => $esercizio,
            'copertura'  => $copertura
        ]);
    }
}