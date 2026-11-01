<?php

namespace Tests\Feature;

use App\Enums\TipoConta;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_saldo_atual_e_o_inicial_mais_os_lancamentos(): void
    {
        $banco = Conta::factory()->create(['nome' => 'A banco', 'saldo_inicial' => 100000]);
        Lancamento::factory()->for($banco)->entrada(50000)->create();
        Lancamento::factory()->for($banco)->saida(30000)->create();
        Conta::factory()->create(['nome' => 'B cartão', 'saldo_inicial' => -5000]);
        Conta::factory()->arquivada()->create(['nome' => 'C antiga']);

        $this->get(route('contas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('contas/Index')
                ->has('contas', 3)
                ->where('contas.0.saldo', 120000)
                ->where('contas.1.saldo', -5000)
                // Arquivadas vão para o fim da lista.
                ->where('contas.2.nome', 'C antiga')
                ->where('contas.2.arquivada', true));
    }

    public function test_cria_conta_com_saldo_inicial_negativo(): void
    {
        $this->post(route('contas.store'), [
            'nome' => 'Cartão',
            'tipo' => 'cartao_de_credito',
            'saldo_inicial' => '-1.250,00',
        ])->assertRedirect(route('contas.index'));

        $conta = Conta::query()->sole();
        $this->assertSame(-125000, $conta->saldo_inicial);
        $this->assertSame(TipoConta::CartaoDeCredito, $conta->tipo);
        $this->assertFalse($conta->arquivada);
    }

    public function test_saldo_inicial_vazio_vira_zero(): void
    {
        $this->post(route('contas.store'), ['nome' => 'Carteira', 'tipo' => 'dinheiro', 'saldo_inicial' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Conta::query()->sole()->saldo_inicial);
    }

    public function test_recusa_tipo_e_saldo_invalidos(): void
    {
        $this->post(route('contas.store'), ['nome' => 'X', 'tipo' => 'cofrinho', 'saldo_inicial' => '10,999'])
            ->assertSessionHasErrors(['tipo', 'saldo_inicial']);

        $this->assertDatabaseCount('contas', 0);
    }

    public function test_arquiva_e_desarquiva(): void
    {
        $conta = Conta::factory()->create();
        $campos = ['nome' => $conta->nome, 'tipo' => 'corrente', 'saldo_inicial' => '0,00'];

        $this->put(route('contas.update', $conta), [...$campos, 'arquivada' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($conta->refresh()->arquivada);

        $this->put(route('contas.update', $conta), [...$campos, 'arquivada' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($conta->refresh()->arquivada);
    }

    public function test_conta_nao_se_exclui(): void
    {
        $conta = Conta::factory()->create();

        $this->delete('/contas/'.$conta->id)->assertMethodNotAllowed();
        $this->assertModelExists($conta);
    }
}
