<?php

namespace Database\Factories;

use App\Enums\TipoConta;
use App\Models\Conta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conta>
 */
class ContaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->randomElement(['Conta principal', 'Poupança', 'Carteira', 'Cartão']).' '.fake()->unique()->numberBetween(1, 9999),
            'tipo' => TipoConta::Corrente,
            'saldo_inicial' => 0,
            'arquivada' => false,
        ];
    }

    public function arquivada(): static
    {
        return $this->state(['arquivada' => true]);
    }
}
