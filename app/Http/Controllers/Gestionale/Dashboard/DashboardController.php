<?php

namespace App\Http\Controllers\Gestionale\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Traits\HasCondomini;
use App\Traits\HasEsercizio;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use HasCondomini, HasEsercizio;

    public function __invoke(Condominio $condominio): Response
    {
        $condomini = $this->getCondomini();
        $esercizio = $this->getEsercizioCorrente($condominio);

        $copertura = null;
        
        if ($esercizio) {
            // [MODIFICA 1] Carichiamo le 'rate' invece dei 'capitoli' per il pianificato
            $esercizio->load([
                'gestioni.pianoConto.conti.sottoconti', 
                'gestioni.pianiRate.rate' // <-- Cambiato qui
            ]);
            
            $totalePreventivo = 0;
            $totalePianificato = 0;
            $capitoliOrfani = [];

            foreach ($esercizio->gestioni as $gestione) {
                if (!$gestione->pianoConto) continue;

                // 1. Preventivo (Resta invariato: legge i Conti vivi)
                $contiRadice = $gestione->pianoConto->conti->whereNull('parent_id');
                foreach ($contiRadice as $conto) {
                    $valoreReale = $conto->sottoconti->isNotEmpty()
                        ? $conto->sottoconti->sum('importo')
                        : $conto->importo;
                    
                    $totalePreventivo += (int) $valoreReale;
                }

                // 2. Pianificato (MODIFICATO: legge le Rate generate)
                foreach ($gestione->pianiRate as $piano) {
                    // Sommiamo l'importo totale delle rate effettivamente create nel DB
                    // Questo valore è "fermo" al momento dell'ultimo calcolo del piano
                    $totalePianificato += (int) $piano->rate->sum('importo_totale');
                }

                // 3. Orfani (Resta invariato)
                $orfani = $gestione->pianoConto->conti()
                    ->whereNull('parent_id')
                    ->whereDoesntHave('pianiRate') 
                    ->get();

                foreach ($orfani as $o) {
                    $importoOrfano = $o->sottoconti->isNotEmpty() 
                        ? $o->sottoconti->sum('importo') 
                        : $o->importo;

                    $capitoliOrfani[] = [
                        'id'       => $o->id,
                        'nome'     => $o->nome,
                        'importo'  => (int) $importoOrfano, 
                        'gestione' => $gestione->nome
                    ];
                }
            }

            $delta = $totalePreventivo - $totalePianificato;
            
            $copertura = [
                'preventivo'     => (int) $totalePreventivo,
                'pianificato'    => (int) $totalePianificato,
                'delta'          => (int) $delta,
                'scoperto'       => (int) ($delta > 0 ? $delta : 0),
                'percentuale'    => $totalePreventivo > 0 ? round(($totalePianificato / $totalePreventivo) * 100) : 0,
                'is_completo'    => abs($delta) <= 5, 
                'orfani'         => $capitoliOrfani,
                'scoperto_count' => count($capitoliOrfani)
            ];
        }

        return Inertia::render('gestionale/dashboard/Dashboard', [
            'condominio' => $condominio,
            'condomini'  => $condomini,
            'esercizio'  => $esercizio,
            'copertura'  => $copertura
        ]);
    }
}