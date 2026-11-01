<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcessoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function paginas(): array
    {
        return [
            'painel' => ['dashboard'],
            'lançamentos' => ['lancamentos.index'],
            'novo lançamento' => ['lancamentos.create'],
            'contas' => ['contas.index'],
            'nova conta' => ['contas.create'],
            'categorias' => ['categorias.index'],
            'nova categoria' => ['categorias.create'],
            'importações' => ['importacoes.index'],
            'importar extrato' => ['importacoes.create'],
            'regras' => ['regras.index'],
            'nova regra' => ['regras.create'],
            'relatórios' => ['relatorios'],
            'assistente' => ['assistente.index'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_toda_pagina_exige_login(string $rota): void
    {
        $this->get(route($rota))->assertRedirect(route('login'));
    }

    public function test_raiz_leva_ao_painel(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }
}
