<?php

namespace Database\Factories;

use App\Models\Esercizio;
use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

class EsercizioFactory extends Factory
{
    /**
     * Il nome del modello associato alla factory.
     */
    protected $model = Esercizio::class;

    public function definition(): array
    {
        $anno = $this->faker->unique()->year;
        
        return [
            'condominio_id' => Condominio::factory(), // Crea automaticamente un condominio se non passato
            'nome' => 'Esercizio ' . $anno,
            'data_inizio' => $anno . '-01-01',
            'data_fine' => $anno . '-12-31',
            'stato' => 'aperto', // Default
        ];
    }
}