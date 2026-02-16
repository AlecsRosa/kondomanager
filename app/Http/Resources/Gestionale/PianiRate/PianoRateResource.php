<?php

namespace App\Http\Resources\Gestionale\PianiRate;

use App\Http\Resources\Gestionale\Gestioni\GestioneResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PianoRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 1. Calcolo Totale Piano (Somma delle rate collegate) in CENTESIMI
        $totalePiano = 0;
        if ($this->relationLoaded('rate')) {
            // Restituiamo INT (Centesimi) per coerenza con il frontend
            $totalePiano = (int) $this->rate->sum('importo_totale');
        }

        // 2. Calcolo Totale Capitoli (Preventivo Reale) in CENTESIMI
        $totaleCapitoli = 0;
        if ($this->relationLoaded('capitoli')) {
            $totaleCapitoli = (int) $this->capitoli->sum(function($c) {
                // Se è un padre, sommiamo i figli, altrimenti prendiamo il suo importo
                return $c->sottoconti->isNotEmpty() 
                    ? $c->sottoconti->sum('importo') 
                    : $c->importo;
            });
        }

        // 3. Gestione Stato
        $statoValue = $this->stato;
        if ($this->stato instanceof \UnitEnum) {
            $statoValue = $this->stato->value;
        }

        return [
            'id'              => $this->id,
            'nome'            => $this->nome,
            'descrizione'     => $this->descrizione,
            'numero_rate'     => $this->numero_rate,
            'stato'           => $statoValue,
            'data_inizio'     => $this->data_inizio?->format('Y-m-d') ?? $this->created_at?->format('Y-m-d'),
            
            // DATI PURI IN CENTESIMI (Es. 120000 per 1.200,00 €)
            // Questo risolve il conflitto con gli "aggregates" del frontend
            'totale_piano'    => $totalePiano,
            'totale_capitoli' => $totaleCapitoli,
            
            // Relazioni
            'gestione'        => new GestioneResource($this->whenLoaded('gestione')),
            'capitoli'        => $this->whenLoaded('capitoli', function() {
                return $this->capitoli->map(fn($c) => [
                    'id'          => $c->id,
                    'nome'        => $c->nome,
                    // Anche qui manteniamo i centesimi
                    'importo'     => $c->sottoconti->isNotEmpty() 
                                        ? (int) $c->sottoconti->sum('importo') 
                                        : (int) $c->importo,
                    'is_parent'   => $c->sottoconti->isNotEmpty(),
                    'figli_names' => $c->sottoconti->pluck('nome')->join(', '),
                ]);
            }),
        ];
    }
}