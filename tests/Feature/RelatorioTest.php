<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use App\Relatorios\LinhaDoRelatorio;
use App\Relatorios\Relatorio;
use App\Relatorios\RelatorioMensal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-15 12:00:00');
        $this->conta = Conta::factory()->create();
    }

    private function lancar(int $valor, string $data, ?Categoria $categoria = null, ?Conta $conta = null): void
    {
        Lancamento::factory()->for($conta ?? $this->conta)->create([
            'valor' => $valor,
            'data' => $data,
            'categoria_id' => $categoria?->id,
        ]);
    }

    private function relatorio(string $mes = '2026-10'): Relatorio
    {
        return app(RelatorioMensal::class)->gerar(CarbonImmutable::parse("{$mes}-01"));
    }

    /**
     * @param  list<LinhaDoRelatorio>  $linhas
     * @return list<array{string, int, int, int|null, int}>
     */
    private function resumo(array $linhas): array
    {
        return array_map(fn (LinhaDoRelatorio $linha) => [
            $linha->nome, $linha->valor->atual, $linha->valor->anterior, $linha->valor->variacao(), $linha->participacao,
        ], $linhas);
    }

    public function test_soma_por_categoria_do_maior_para_o_menor(): void
    {
        // Criadas fora de ordem: o banco devolve na ordem do id, e a lista certa só sai se o
        // relatório ordenar de verdade.
        $transporte = Categoria::factory()->create(['nome' => 'Transporte']);
        $lazer = Categoria::factory()->create(['nome' => 'Lazer']);
        $farmacia = Categoria::factory()->create(['nome' => 'Farmácia']);
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);

        $this->lancar(-30000, '2026-10-02', $mercado);
        $this->lancar(-20000, '2026-10-20', $mercado);
        $this->lancar(-20000, '2026-10-05', $transporte);
        $this->lancar(-20000, '2026-10-07', $farmacia);
        $this->lancar(-30000, '2026-10-06', $lazer);
        $this->lancar(500000, '2026-10-05', $salario);

        $relatorio = $this->relatorio();

        $this->assertSame(500000, $relatorio->receitas->atual);
        $this->assertSame(-120000, $relatorio->despesas->atual);
        $this->assertSame(380000, $relatorio->resultado->atual);
        // Farmácia e Transporte empatam no valor: vale a ordem do nome.
        $this->assertSame([
            ['Mercado', -50000, 0, null, 4167],
            ['Lazer', -30000, 0, null, 2500],
            ['Farmácia', -20000, 0, null, 1667],
            ['Transporte', -20000, 0, null, 1667],
        ], $this->resumo($relatorio->despesasPorCategoria));
        $this->assertSame([['Salário', 500000, 0, null, 10000]], $this->resumo($relatorio->receitasPorCategoria));
    }

    public function test_sem_categoria_vira_uma_linha_de_cada_lado(): void
    {
        $this->lancar(-1500, '2026-10-03');
        $this->lancar(2000, '2026-10-04');

        $relatorio = $this->relatorio();

        $this->assertSame([['Sem categoria', -1500, 0, null, 10000]], $this->resumo($relatorio->despesasPorCategoria));
        $this->assertSame([['Sem categoria', 2000, 0, null, 10000]], $this->resumo($relatorio->receitasPorCategoria));
        $this->assertNull($relatorio->despesasPorCategoria[0]->categoriaId);
    }

    public function test_transferencia_fica_fora_dos_totais_e_aparece_somada(): void
    {
        $cartao = Conta::factory()->create();
        $entreContas = Categoria::factory()->transferencia()->create();
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);

        $this->lancar(-80000, '2026-10-03', $mercado, $cartao);
        $this->lancar(-120000, '2026-10-10', $entreContas);
        $this->lancar(120000, '2026-10-10', $entreContas, $cartao);

        $relatorio = $this->relatorio();

        $this->assertSame(0, $relatorio->receitas->atual);
        $this->assertSame(-80000, $relatorio->despesas->atual);
        $this->assertSame(120000, $relatorio->transferencias);
        $this->assertSame([['Mercado', -80000, 0, null, 10000]], $this->resumo($relatorio->despesasPorCategoria));
        $this->assertSame([], $relatorio->receitasPorCategoria);
    }

    public function test_compara_cada_categoria_com_o_mes_anterior(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $lazer = Categoria::factory()->create(['nome' => 'Lazer']);
        $transporte = Categoria::factory()->create(['nome' => 'Transporte']);
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);

        $this->lancar(-40000, '2026-09-10', $mercado);
        $this->lancar(-10000, '2026-09-11', $lazer);
        $this->lancar(500000, '2026-09-05', $salario);
        $this->lancar(-50000, '2026-10-10', $mercado);
        $this->lancar(-20000, '2026-10-11', $transporte);
        $this->lancar(500000, '2026-10-05', $salario);

        $relatorio = $this->relatorio();

        // Gastar mais é variação positiva; a categoria que zerou continua na lista, com -100%.
        $this->assertSame([
            ['Mercado', -50000, -40000, 2500, 7143],
            ['Transporte', -20000, 0, null, 2857],
            ['Lazer', 0, -10000, -10000, 0],
        ], $this->resumo($relatorio->despesasPorCategoria));
        $this->assertSame([['Salário', 500000, 500000, 0, 10000]], $this->resumo($relatorio->receitasPorCategoria));
        $this->assertSame(4000, $relatorio->despesas->variacao());
        $this->assertSame(['atual' => 430000, 'anterior' => 450000], [
            'atual' => $relatorio->resultado->atual,
            'anterior' => $relatorio->resultado->anterior,
        ]);
    }

    public function test_o_ultimo_dia_do_mes_entra_e_o_primeiro_do_seguinte_nao(): void
    {
        $this->lancar(-1000, '2026-09-30');
        $this->lancar(-2000, '2026-10-01');
        $this->lancar(-3000, '2026-10-31');
        $this->lancar(-4000, '2026-11-01');

        $relatorio = $this->relatorio();

        $this->assertSame(-5000, $relatorio->despesas->atual);
        $this->assertSame(-1000, $relatorio->despesas->anterior);
    }

    public function test_janeiro_compara_com_dezembro_do_ano_anterior(): void
    {
        $this->lancar(-1000, '2026-12-20');
        $this->lancar(-3000, '2027-01-20');

        $relatorio = $this->relatorio('2027-01');

        $this->assertSame(-3000, $relatorio->despesas->atual);
        $this->assertSame(-1000, $relatorio->despesas->anterior);
        $this->assertSame(20000, $relatorio->despesas->variacao());
    }

    public function test_conta_arquivada_continua_no_relatorio(): void
    {
        $this->lancar(-7000, '2026-10-08', conta: Conta::factory()->arquivada()->create());

        $this->assertSame(-7000, $this->relatorio()->despesas->atual);
    }

    public function test_mes_vazio_da_zero_e_nenhuma_linha(): void
    {
        $relatorio = $this->relatorio();

        $this->assertSame([0, 0, 0], [$relatorio->receitas->atual, $relatorio->despesas->atual, $relatorio->transferencias]);
        $this->assertNull($relatorio->despesas->variacao());
        $this->assertSame([], $relatorio->despesasPorCategoria);
    }

    public function test_a_pagina_entrega_o_relatorio_pronto(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $this->lancar(-40000, '2026-08-10', $mercado);
        $this->lancar(-50000, '2026-09-10', $mercado);

        $this->get(route('relatorios', ['mes' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('relatorios/Mes')
                ->where('mes', '2026-09')
                ->where('mesAnterior', '2026-08')
                ->where('mesSeguinte', '2026-10')
                ->where('despesas', ['atual' => -50000, 'anterior' => -40000, 'variacao' => 2500])
                ->where('resultado', ['atual' => -50000, 'anterior' => -40000])
                ->where('transferencias', 0)
                ->where('despesasPorCategoria', [[
                    'categoriaId' => $mercado->id,
                    'nome' => 'Mercado',
                    'total' => -50000,
                    'anterior' => -40000,
                    'variacao' => 2500,
                    'participacao' => 10000,
                ]])
                ->where('receitasPorCategoria', []));
    }

    public function test_mes_invalido_cai_no_mes_atual(): void
    {
        $this->get(route('relatorios', ['mes' => '2026-13']))
            ->assertInertia(fn (Assert $page) => $page->where('mes', '2026-10'));
    }

    public function test_o_painel_mostra_os_mesmos_totais_do_relatorio(): void
    {
        $entreContas = Categoria::factory()->transferencia()->create();
        $this->lancar(-12345, '2026-10-02', Categoria::factory()->create());
        $this->lancar(-678, '2026-10-03');
        $this->lancar(98765, '2026-10-04', Categoria::factory()->receita()->create());
        $this->lancar(-50000, '2026-10-05', $entreContas);
        $this->lancar(50000, '2026-10-05', $entreContas);
        $relatorio = $this->relatorio();

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('receitasDoMes', $relatorio->receitas->atual)
                ->where('despesasDoMes', $relatorio->despesas->atual));
        $this->assertSame([98765, -13023], [$relatorio->receitas->atual, $relatorio->despesas->atual]);
    }
}
