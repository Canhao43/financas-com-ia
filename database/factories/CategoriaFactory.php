<?php

namespace Database\Factories;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Categoria '.fake()->unique()->numberBetween(1, 99999),
            'tipo' => TipoCategoria::Despesa,
        ];
    }

    public function receita(): static
    {
        return $this->state(['tipo' => TipoCategoria::Receita]);
    }
}
