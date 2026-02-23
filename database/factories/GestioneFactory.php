<?php

namespace Database\Factories;

use App\Models\Gestione;
use App\Models\Condominio;
use App\Models\Esercizio;
use Illuminate\Database\Eloquent\Factories\Factory;

class GestioneFactory extends Factory
{
    protected $model = Gestione::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'nome' => $this->faker->word . ' ' . $this->faker->year,
            'tipo' => 'ordinaria',
            'saldo_applicato' => false, 
            'nota_saldo' => null,
            'attiva' => true, 
        ];
    }
}