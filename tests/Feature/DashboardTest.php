<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_painel_vazio_abre_com_tudo_zerado(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('mes', '2026-10')
                ->where('saldoTotal', 0)
                ->has('ultimos', 0));
    }

    public function test_soma_contas_ativas_e_separa_o_mes_atual(): void
    {
        $banco = Conta::factory()->create(['saldo_inicial' => 100000]);
        Lancamento::factory()->for($banco)->entrada(200000)->create(['data' => '2026-10-05']);
        Lancamento::factory()->for($banco)->saida(50000)->create(['data' => '2026-10-31']);
        Lancamento::factory()->for($banco)->saida(10000)->create(['data' => '2026-09-28']);
        Conta::factory()->arquivada()->create(['saldo_inicial' => 999900]);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('saldoTotal', 240000)
                ->where('entradasDoMes', 200000)
                ->where('saidasDoMes', -50000)
                ->has('ultimos', 3)
                ->where('ultimos.0.data', '2026-10-31'));
    }

    public function test_mostra_so_os_cinco_ultimos_lancamentos(): void
    {
        Lancamento::factory()->count(7)->create();

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('ultimos', 5));
    }
}
