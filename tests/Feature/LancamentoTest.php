<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LancamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-15 12:00:00');
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function formulario(Conta $conta, array $campos = []): array
    {
        return [
            'direcao' => 'saida',
            'valor' => '10,00',
            'data' => '2026-10-20',
            'descricao' => 'Mercado',
            'conta_id' => $conta->id,
            'categoria_id' => null,
            ...$campos,
        ];
    }

    public function test_lista_so_o_mes_pedido_com_totais_do_servidor(): void
    {
        $conta = Conta::factory()->create();
        Lancamento::factory()->for($conta)->entrada(500000)->create(['data' => '2026-10-01']);
        Lancamento::factory()->for($conta)->saida(123456)->create(['data' => '2026-10-31']);
        Lancamento::factory()->for($conta)->saida(9999)->create(['data' => '2026-11-01']);
        Lancamento::factory()->for($conta)->saida(1111)->create(['data' => '2026-09-30']);

        $this->get(route('lancamentos.index', ['mes' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('lancamentos/Index')
                ->where('mes', '2026-10')
                ->where('mesAnterior', '2026-09')
                ->where('mesSeguinte', '2026-11')
                ->has('lancamentos', 2)
                ->where('lancamentos.0.data', '2026-10-31')
                ->where('lancamentos.1.data', '2026-10-01')
                ->where('totais.entradas', 500000)
                ->where('totais.saidas', -123456)
                ->where('totais.saldo', 376544));
    }

    public function test_filtra_por_conta(): void
    {
        $banco = Conta::factory()->create();
        $carteira = Conta::factory()->create();
        Lancamento::factory()->for($banco)->saida(1000)->create(['data' => '2026-10-10']);
        Lancamento::factory()->for($carteira)->saida(2000)->create(['data' => '2026-10-10']);

        $this->get(route('lancamentos.index', ['mes' => '2026-10', 'conta' => $carteira->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contaId', $carteira->id)
                ->has('lancamentos', 1)
                ->where('totais.saldo', -2000));
    }

    public function test_filtra_pela_categoria_que_vem_do_relatorio(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        Lancamento::factory()->saida(1000)->create(['data' => '2026-10-10', 'categoria_id' => $mercado->id]);
        Lancamento::factory()->saida(2000)->create(['data' => '2026-10-11', 'categoria_id' => null]);
        Lancamento::factory()->saida(4000)->create(['data' => '2026-10-12', 'categoria_id' => Categoria::factory()]);

        $this->get(route('lancamentos.index', ['mes' => '2026-10', 'categoria' => $mercado->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categoria', ['filtro' => (string) $mercado->id, 'nome' => 'Mercado'])
                ->where('totais.saldo', -1000));

        $this->get(route('lancamentos.index', ['mes' => '2026-10', 'categoria' => 'sem']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categoria', ['filtro' => 'sem', 'nome' => 'Sem categoria'])
                ->where('totais.saldo', -2000));

        // Filtro que não é id nem "sem" é ignorado, não vira lista vazia.
        $this->get(route('lancamentos.index', ['mes' => '2026-10', 'categoria' => 'xyz']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categoria', null)
                ->where('totais.saldo', -7000));
    }

    public function test_mes_invalido_cai_no_mes_atual(): void
    {
        $this->get(route('lancamentos.index', ['mes' => '2026-13']))
            ->assertInertia(fn (Assert $page) => $page->where('mes', '2026-10'));
    }

    public function test_saida_e_gravada_negativa_em_centavos(): void
    {
        $conta = Conta::factory()->create();
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);

        $this->post(route('lancamentos.store'), $this->formulario($conta, [
            'valor' => '1.234,56',
            'categoria_id' => $mercado->id,
        ]))->assertRedirect(route('lancamentos.index', ['mes' => '2026-10']));

        $this->assertDatabaseHas('lancamentos', [
            'conta_id' => $conta->id,
            'categoria_id' => $mercado->id,
            'valor' => -123456,
        ]);
    }

    public function test_entrada_e_gravada_positiva(): void
    {
        $conta = Conta::factory()->create();
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);

        $this->post(route('lancamentos.store'), $this->formulario($conta, [
            'direcao' => 'entrada',
            'valor' => '6.500,00',
            'categoria_id' => $salario->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(650000, Lancamento::query()->sole()->valor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valoresRecusados(): array
    {
        return [
            'zero' => ['0,00'],
            'texto' => ['dez reais'],
            'três casas' => ['10,999'],
            'negativo (a direção é que dá o sinal)' => ['-10,00'],
        ];
    }

    #[DataProvider('valoresRecusados')]
    public function test_valor_invalido_e_recusado(string $valor): void
    {
        $conta = Conta::factory()->create();

        $this->post(route('lancamentos.store'), $this->formulario($conta, ['valor' => $valor]))
            ->assertSessionHasErrors('valor');

        $this->assertDatabaseCount('lancamentos', 0);
    }

    public function test_conta_arquivada_nao_recebe_lancamento_novo(): void
    {
        $conta = Conta::factory()->arquivada()->create();

        $this->post(route('lancamentos.store'), $this->formulario($conta))
            ->assertSessionHasErrors(['conta_id' => 'Escolha uma conta ativa.']);
    }

    public function test_categoria_precisa_combinar_com_a_direcao(): void
    {
        $conta = Conta::factory()->create();
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);

        $this->post(route('lancamentos.store'), $this->formulario($conta, [
            'direcao' => 'entrada',
            'categoria_id' => $mercado->id,
        ]))->assertSessionHasErrors('categoria_id');
    }

    public function test_transferencia_serve_para_saida_e_para_entrada(): void
    {
        $conta = Conta::factory()->create();
        $entreContas = Categoria::factory()->transferencia()->create();

        foreach (['saida', 'entrada'] as $direcao) {
            $this->post(route('lancamentos.store'), $this->formulario($conta, [
                'direcao' => $direcao,
                'categoria_id' => $entreContas->id,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $entreContas->lancamentos()->count());

        $this->get(route('lancamentos.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categorias.0.direcoes', ['saida', 'entrada']));
    }

    public function test_lancamento_de_conta_arquivada_continua_editavel(): void
    {
        $arquivada = Conta::factory()->arquivada()->create();
        $outraArquivada = Conta::factory()->arquivada()->create();
        $lancamento = Lancamento::factory()->for($arquivada)->create(['data' => '2026-08-10']);

        $this->put(route('lancamentos.update', $lancamento), $this->formulario($arquivada, [
            'descricao' => 'Descrição corrigida',
            'data' => '2026-08-10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Descrição corrigida', $lancamento->refresh()->descricao);

        // Mas não dá para mover para outra conta arquivada.
        $this->put(route('lancamentos.update', $lancamento), $this->formulario($outraArquivada))
            ->assertSessionHasErrors('conta_id');
    }

    public function test_formulario_so_oferece_contas_ativas_e_a_do_proprio_lancamento(): void
    {
        $ativa = Conta::factory()->create();
        $arquivada = Conta::factory()->arquivada()->create();
        $lancamento = Lancamento::factory()->for($arquivada)->create();

        $this->get(route('lancamentos.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('lancamentos/Form')
                ->has('contas', 1)
                ->where('contas.0.id', $ativa->id)
                ->where('padrao.data', '2026-10-15'));

        $this->get(route('lancamentos.edit', $lancamento))
            ->assertInertia(fn (Assert $page) => $page->has('contas', 2));
    }

    public function test_a_data_padrao_e_o_dia_de_hoje_em_sao_paulo(): void
    {
        // 23h30 de 30/11 em São Paulo; em UTC já é 01/12.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 02:30:00', 'UTC'));

        $this->get(route('lancamentos.create'))
            ->assertInertia(fn (Assert $page) => $page->where('padrao.data', '2026-11-30'));
    }

    public function test_edicao_devolve_o_valor_no_formato_de_digitacao(): void
    {
        $lancamento = Lancamento::factory()->saida(123456)->create();

        $this->get(route('lancamentos.edit', $lancamento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lancamento.direcao', 'saida')
                ->where('lancamento.valor', '1.234,56'));
    }

    public function test_exclui_e_volta_para_o_mes_do_lancamento(): void
    {
        $lancamento = Lancamento::factory()->create(['data' => '2026-08-10']);

        $this->delete(route('lancamentos.destroy', $lancamento))
            ->assertRedirect(route('lancamentos.index', ['mes' => '2026-08']));

        $this->assertModelMissing($lancamento);
    }
}
