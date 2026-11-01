<?php

namespace Database\Factories;

use App\Models\Conta;
use App\Models\Lancamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lancamento>
 */
class LancamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conta_id' => Conta::factory(),
            'categoria_id' => null,
            'data' => fake()->dateTimeBetween('-2 months')->format('Y-m-d'),
            'descricao' => fake()->randomElement(['Mercado', 'Padaria', 'Farmácia', 'Combustível', 'Restaurante']),
            'valor' => -fake()->numberBetween(500, 50000),
        ];
    }

    public function entrada(int $centavos): static
    {
        return $this->state(['valor' => $centavos]);
    }

    public function saida(int $centavos): static
    {
        return $this->state(['valor' => -$centavos]);
    }
}
