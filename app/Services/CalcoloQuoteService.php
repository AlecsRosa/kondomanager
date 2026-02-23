<?php

namespace App\Services;

use App\Models\Gestione;
use App\Models\Gestionale\PianoRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Servizio per il calcolo delle quote di spesa/entrata per ogni gestione.
 * VERSION: 1.9.3 (PENNY PERFECT - Push Down with Quadrature)
 */
class CalcoloQuoteService
{
    private ?Gestione $gestioneCorrente = null;
    private array $pivotOverrides = [];

    public function calcolaPerGestione(Gestione $gestione, ?PianoRate $pianoRate = null): array
    {
        $this->gestioneCorrente = $gestione;
        $this->pivotOverrides = [];
        $totali = [];
        $pianoConto = $gestione->pianoConto;

        if (!$pianoConto) return [];

        $capitoliIds = [];
        if ($pianoRate) {
            $pianoRate->load('capitoli');
            foreach ($pianoRate->capitoli as $capitolo) {
                $capitoliIds[] = $capitolo->id;
                if (!is_null($capitolo->pivot->importo)) {
                    $this->pivotOverrides[$capitolo->id] = (int) $capitolo->pivot->importo;
                }
            }
        }

        $query = $pianoConto->conti()
            ->with([
                'tabelleMillesimali.tabella.quote.immobile.anagrafiche',
                'tabelleMillesimali.ripartizioni',
                'sottoconti.sottoconti', 
            ]);

        if (!empty($capitoliIds)) {
            $query->whereIn('id', $capitoliIds);
        } else {
            $query->whereNull('parent_id');
        }

        $conti = $query->get();

        Log::info("=== INIZIO CALCOLO QUOTE V1.9.3 (Penny Perfect) ===", [
            'piano_rate_id' => $pianoRate?->id,
            'overrides' => count($this->pivotOverrides)
        ]);

        $this->processaConti($conti, $totali);

        return $totali;
    }

    private function processaConti(Collection $conti, array &$totali): void
    {
        foreach ($conti as $conto) {
            
            $hasOverride = isset($this->pivotOverrides[$conto->id]);
            
            if ($hasOverride) {
                $importoOverride = $this->pivotOverrides[$conto->id];

                // A. È una foglia -> Distribuisci
                if ($conto->tabelleMillesimali->isNotEmpty()) {
                    $importoConto = in_array($conto->tipo, ['spesa', 'uscita']) 
                        ? abs($importoOverride) : -abs($importoOverride);
                    
                    $this->distribuisciSuTabelle($conto, $importoConto, $totali);
                    continue; 
                }
                
                // B. È una cartella -> PUSH DOWN con QUADRATURA
                elseif ($conto->sottoconti->isNotEmpty()) {
                    
                    $totaleOriginaleFigli = (int) $conto->sottoconti->sum('importo');

                    if ($totaleOriginaleFigli != 0) {
                        $ratio = $importoOverride / $totaleOriginaleFigli;
                        
                        // Variabili per la quadratura
                        $sommaAssegnata = 0;
                        $counter = 0;
                        $totaleFigli = $conto->sottoconti->count();

                        foreach ($conto->sottoconti as $figlio) {
                            $counter++;
                            
                            // Se è l'ultimo figlio, assegniamo il residuo matematico esatto
                            if ($counter === $totaleFigli) {
                                $quotaFiglio = $importoOverride - $sommaAssegnata;
                            } else {
                                $quotaFiglio = (int) round($figlio->importo * $ratio);
                                $sommaAssegnata += $quotaFiglio;
                            }

                            // Salviamo l'override virtuale per il figlio
                            $this->pivotOverrides[$figlio->id] = $quotaFiglio;
                        }
                        
                        $this->processaConti($conto->sottoconti, $totali);
                        continue;
                    }
                }
                
                continue; 
            }

            // STANDARD
            $importoLordo = (int) $conto->importo;
            
            if ($importoLordo !== 0) {
                $tipo = $conto->tipo ?? 'spesa';
                $importoConto = in_array($tipo, ['spesa', 'uscita'])
                    ? abs($importoLordo)
                    : -abs($importoLordo);

                $this->distribuisciSuTabelle($conto, $importoConto, $totali);
            }

            if ($conto->sottoconti && $conto->sottoconti->count() > 0) {
                $this->processaConti($conto->sottoconti, $totali);
            }
        }
    }

    private function distribuisciSuTabelle($conto, $importoConto, array &$totali)
    {
        $tipo = $conto->tipo ?? 'spesa';
        $weights = [];

        foreach ($conto->tabelleMillesimali as $ctm) {
            $tabella = $ctm->tabella ?? null;
            if (!$tabella) continue;

            $coeff = (float) $ctm->coefficiente;
            if ($coeff <= 0) continue;

            $weightCoeff = $coeff / 100.0;
            $quote = $tabella->quote;
            if ($quote->isEmpty()) continue;

            $sommaValori = (float) $quote->sum('valore');
            if ($sommaValori <= 0.0) continue;

            foreach ($quote as $quota) {
                $immobile = $quota->immobile ?? null;
                if (!$immobile) continue;

                $valore = (float) $quota->valore;
                if ($valore <= 0.0) continue;

                $weightImmobile = $weightCoeff * ($valore / $sommaValori);

                $ripartizioni = $ctm->ripartizioni->isNotEmpty()
                    ? $ctm->ripartizioni
                    : collect([(object) ['soggetto' => 'proprietario', 'percentuale' => 100.0]]);

                foreach ($ripartizioni as $rip) {
                    $percent = (float) $rip->percentuale;
                    if ($percent <= 0.0) continue;

                    $weightRip = $weightImmobile * ($percent / 100.0);

                    $anagrafiche = $immobile->anagrafiche
                        ->where('pivot.attivo', true)
                        ->where('pivot.tipologia', $rip->soggetto);

                    if ($anagrafiche->isEmpty() && in_array($rip->soggetto, ['inquilino', 'usufruttuario'])) {
                        $anagrafiche = $immobile->anagrafiche
                            ->where('pivot.attivo', true)
                            ->where('pivot.tipologia', 'proprietario');
                    }

                    if ($anagrafiche->isEmpty()) continue;

                    $sommaQuote = (float) $anagrafiche->sum('pivot.quota');
                    if ($sommaQuote <= 0.0) $sommaQuote = 1.0;

                    foreach ($anagrafiche as $anag) {
                        $quotaAnag = (float) $anag->pivot->quota;
                        if ($quotaAnag <= 0.0) continue;

                        $weightAnagrafica = $weightRip * ($quotaAnag / $sommaQuote);
                        $key = $anag->id . '|' . $immobile->id;
                        $weights[$key] = ($weights[$key] ?? 0.0) + $weightAnagrafica;
                    }
                }
            }
        }

        if (empty($weights)) return;

        $pesoTotale = array_sum($weights);
        if ($pesoTotale <= 0.0) return;

        foreach ($weights as $key => $w) {
            $weights[$key] = $w / $pesoTotale;
        }

        $importiDistributi = $this->distribuisciImporto($weights, $importoConto);

        foreach ($importiDistributi as $key => $importoCentesimi) {
            [$aid, $iid] = array_map('intval', explode('|', $key));
            $totali[$aid][$iid] = ($totali[$aid][$iid] ?? 0) + $importoCentesimi;
        }
    }

    private function distribuisciImporto(array $weights, int $importoTotale): array
    {
        $result = [];
        if ($importoTotale === 0) {
            foreach ($weights as $key => $_) { $result[$key] = 0; }
            return $result;
        }

        $sign = $importoTotale < 0 ? -1 : 1;
        $totAbs = abs($importoTotale);
        $bases = [];
        $remainders = [];
        $sumBase = 0;

        foreach ($weights as $key => $w) {
            $raw = $totAbs * $w;
            $base = (int) floor($raw);
            $bases[$key] = $base;
            $remainders[$key] = $raw - $base;
            $sumBase += $base;
        }

        $diff = $totAbs - $sumBase;
        if ($diff > 0) {
            arsort($remainders);
            $keys = array_keys($remainders);
            for ($i = 0; $i < $diff && $i < count($keys); $i++) {
                $bases[$keys[$i]]++;
            }
        }

        foreach ($bases as $key => $b) {
            $result[$key] = $b * $sign;
        }

        return $result;
    }
}