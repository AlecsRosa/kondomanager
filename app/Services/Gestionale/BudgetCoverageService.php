<?php

namespace App\Services\Gestionale;

use App\Models\Gestione;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BudgetCoverageService
{
    public function analyze(Gestione $gestione): array
    {
        $gestione->load(['pianoConto.conti.sottoconti', 'pianiRate.capitoli']);
        if (!$gestione->pianoConto) return ['status' => 'empty', 'items' => []];

        $contiRadice = $gestione->pianoConto->conti->whereNull('parent_id'); 
        $pianiRateAttivi = $gestione->pianiRate->where('attivo', true);
        
        // Calcolo della copertura con la nuova logica a Cascata
        $coperturaRealeMap = $this->calcolaCoperturaReale($contiRadice, $pianiRateAttivi);

        $report = [];
        foreach ($this->appiattisciConti($contiRadice) as $conto) {
            $isLeaf = $conto->sottoconti->isEmpty();
            if ($conto->importo == 0 && !$isLeaf) continue; 

            $budget = (int) $conto->importo;
            $pianificato = (int) ($coperturaRealeMap[$conto->id] ?? 0);
            $delta = $pianificato - $budget;

            $status = 'ok'; $severity = 'success'; $message = 'Copertura perfetta.';

            if ($delta < -100) { 
                $status = 'deficit'; $severity = 'danger';
                $percent = $budget > 0 ? round(($pianificato / $budget) * 100) : 0;
                $message = "Deficit: coperto al {$percent}%.";
            } elseif ($delta > 100) {
                $status = 'surplus'; $severity = 'warning';
                $extra = number_format($delta / 100, 2, ',', '.');
                $message = "Surplus di € {$extra}.";
            }

            $report[] = [
                'id' => $conto->id, 'nome' => $conto->nome, 'padre' => $conto->parent?->nome,
                'is_leaf' => $isLeaf, 'budget' => $budget, 'pianificato' => $pianificato,
                'delta' => $delta, 'status' => $status, 'severity' => $severity, 'message' => $message,
                'gestione' => $gestione->nome,
                'piani_coinvolti' => $this->trovaPianiCoinvolti($conto->id, $pianiRateAttivi)
            ];
        }

        $totBudget = 0; $totPianificato = 0;
        foreach ($report as $r) {
            if ($r['is_leaf']) {
                $totBudget += $r['budget'];
                $totPianificato += $r['pianificato'];
            }
        }

        return [
            'status' => 'analyzed',
            'items' => $report,
            'totali' => ['budget' => $totBudget, 'pianificato' => $totPianificato]
        ];
    }

    private function calcolaCoperturaReale($contiRadice, $pianiRate): array
    {
        $map = [];
        $tuttiContiFlat = $this->appiattisciConti($contiRadice);
        $contiById = $tuttiContiFlat->keyBy('id');

        // STEP 1: Prima passiamo tutti i piani e assegniamo le voci DIRETTE (Foglie)
        // Questo "blinda" le voci come il compenso amministratore se le hai messe in un piano specifico
        foreach ($pianiRate as $piano) {
            foreach ($piano->capitoli as $capitolo) {
                $contoModel = $contiById[$capitolo->id] ?? null;
                if ($contoModel && $contoModel->sottoconti->isEmpty()) {
                    $impegnato = !is_null($capitolo->pivot->importo) ? $capitolo->pivot->importo : $capitolo->importo;
                    $map[$contoModel->id] = ($map[$contoModel->id] ?? 0) + $impegnato;
                }
            }
        }

        // STEP 2: Seconda passata per i PADRI (Capitoli)
        // Distribuiscono il loro budget solo per coprire i "buchi" rimasti nelle foglie
        foreach ($pianiRate as $piano) {
            foreach ($piano->capitoli as $capitolo) {
                $contoModel = $contiById[$capitolo->id] ?? null;
                if ($contoModel && $contoModel->sottoconti->isNotEmpty()) {
                    $residuoPiano = !is_null($capitolo->pivot->importo) ? $capitolo->pivot->importo : $capitolo->importo;

                    foreach ($contoModel->sottoconti as $figlio) {
                        $budgetMancanteFiglio = $figlio->importo - ($map[$figlio->id] ?? 0);
                        
                        if ($budgetMancanteFiglio > 0 && $residuoPiano > 0) {
                            $coperturaDaAssegnare = min($budgetMancanteFiglio, $residuoPiano);
                            $map[$figlio->id] = ($map[$figlio->id] ?? 0) + $coperturaDaAssegnare;
                            $residuoPiano -= $coperturaDaAssegnare;
                        }
                    }

                    // Se dopo aver coperto i buchi avanza budget nel piano (Surplus), 
                    // lo buttiamo sull'ultimo figlio per non perdere la quadratura
                    if ($residuoPiano > 0 && $contoModel->sottoconti->isNotEmpty()) {
                        $ultimoFiglioId = $contoModel->sottoconti->last()->id;
                        $map[$ultimoFiglioId] = ($map[$ultimoFiglioId] ?? 0) + $residuoPiano;
                    }
                }
            }
        }
        return $map;
    }

    private function appiattisciConti($conti) {
        $flat = collect();
        foreach ($conti as $c) {
            $flat->push($c);
            if ($c->sottoconti->isNotEmpty()) $flat = $flat->merge($this->appiattisciConti($c->sottoconti));
        }
        return $flat;
    }

    private function trovaPianiCoinvolti($contoId, $piani)
    {
        $names = [];
        foreach ($piani as $p) {
            $coinvolto = $p->capitoli->contains(function ($c) use ($contoId) {
                return $c->id == $contoId || ($c->sottoconti && $c->sottoconti->contains('id', $contoId));
            });
            if ($coinvolto) $names[] = $p->nome;
        }
        return $names;
    }
}