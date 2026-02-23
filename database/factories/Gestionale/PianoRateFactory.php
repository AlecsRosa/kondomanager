<?php

namespace Database\Factories\Gestionale;

use App\Models\Gestionale\PianoRate;
use App\Models\Gestione;
use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

class PianoRateFactory extends Factory
{
    protected $model = PianoRate::class;

    public function definition(): array
    {
        return [
            'gestione_id' => Gestione::factory(),
            'condominio_id' => Condominio::factory(),
            'nome' => 'Piano Rate ' . $this->faker->word,
            'numero_rate' => 12,
            'stato' => 'bozza',
        ];
    }
}