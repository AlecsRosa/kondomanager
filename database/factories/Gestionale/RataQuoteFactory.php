<?php

namespace Database\Factories\Gestionale;

use App\Models\Gestionale\RataQuote;
use App\Models\Gestionale\Rata;
use Illuminate\Database\Eloquent\Factories\Factory;

class RataQuoteFactory extends Factory
{
    protected $model = RataQuote::class;

    public function definition(): array
    {
        return [
            'rata_id' => Rata::factory(),
            'anagrafica_id' => 1, 
            'importo' => 5000,
            'importo_pagato' => 0,
            'stato' => 'da_pagare', // Aggiungi lo stato se obbligatorio
        ];
    }
}