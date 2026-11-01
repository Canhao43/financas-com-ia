<?php

namespace Tests\Feature;

use App\Assistente\CaixaDeFerramentas;
use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FerramentasDoAssistenteTest extends TestCase
{
    use RefreshDatabase;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 12:00:00');
        $this->conta = Conta::factory()->create(['nome' => 'Conta corrente', 'saldo_inicial' => 100000]);
    }

    private function lancar(int $valor, string $data, ?Categoria $categoria = null, string $descricao = 'Lançamento', ?Conta $conta = null): void
    {
        Lancamento::factory()->for($conta ?? $this->conta)->create([
            'valor' => $valor,
            'data' => $data,
            'categoria_id' => $categoria?->id,
            'descricao' => $descricao,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function chamar(string $ferramenta, array $entrada = []): array
    {
        $resultado = app(CaixaDeFerramentas::class)->executar($ferramenta, $entrada);
        $this->assertFalse($resultado->erro, $resultado->conteudo);

        return json_decode($resultado->conteudo, true);
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    private function erro(string $ferramenta, array $entrada): string
    {
        $resultado = app(CaixaDeFerramentas::class)->executar($ferramenta, $entrada);
        $this->assertTrue($resultado->erro, 'Era para dar erro, deu: '.$resultado->conteudo);

        return $resultado->conteudo;
    }

    public function test_toda_ferramenta_tem_nome_unico_e_esquema_fechado(): void
    {
        $definicoes = app(CaixaDeFerramentas::class)->definicoes();
        $nomes = array_column($definicoes, 'name');

        $this->assertSame(['resumo_do_mes', 'total_no_periodo', 'comparar_periodos', 'buscar_lancamentos', 'listar_categorias', 'saldos_das_contas'], $nomes);
        foreach ($definicoes as $definicao) {
            $this->assertNotEmpty($definicao['description']);
            $this->assertSame('object', $definicao['inputSchema']['type']);
            $this->assertFalse($definicao['inputSchema']['additionalProperties'], $definicao['name']);
        }
        // Mesmos bytes a cada pergunta: é o que deixa o cache do prompt funcionar.
        $this->assertSame(json_encode($definicoes), json_encode(app(CaixaDeFerramentas::class)->definicoes()));
    }

    public function test_resumo_do_mes_entrega_o_relatorio_formatado(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);
        $this->lancar(-62345, '2026-09-08', $mercado);
        $this->lancar(-71890, '2026-10-08', $mercado);
        $this->lancar(650000, '2026-10-05', $salario);

        $resumo = $this->chamar('resumo_do_mes', ['mes' => '2026-10']);

        $this->assertSame('outubro de 2026', $resumo['mes']);
        $this->assertSame('setembro de 2026', $resumo['mes_anterior']);
        $this->assertSame(['total' => 'R$ 718,90', 'mes_anterior' => 'R$ 623,45', 'diferenca' => 'R$ 95,45 a mais', 'variacao' => '+15,3%'], $resumo['despesas']);
        $this->assertSame(['total' => 'R$ 5.781,10', 'mes_anterior' => '-R$ 623,45'], $resumo['resultado']);
        $this->assertSame([[
            'categoria' => 'Mercado',
            'participacao_no_mes' => '100%',
            'total' => 'R$ 718,90',
            'mes_anterior' => 'R$ 623,45',
            'diferenca' => 'R$ 95,45 a mais',
            'variacao' => '+15,3%',
        ]], $resumo['despesas_por_categoria']);
        $this->assertNull($resumo['receitas']['variacao']);
    }

    public function test_total_no_periodo_soma_varios_meses_sem_transferencias(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $entreContas = Categoria::factory()->transferencia()->create(['nome' => 'Entre contas']);
        $this->lancar(-10000, '2026-08-31', $mercado);
        $this->lancar(-20000, '2026-09-01', $mercado);
        $this->lancar(-30000, '2026-10-31', $mercado);
        $this->lancar(-5000, '2026-10-10');
        $this->lancar(-99999, '2026-10-12', $entreContas);
        $this->lancar(-40000, '2026-11-01', $mercado);

        $todas = $this->chamar('total_no_periodo', ['de' => '2026-09-01', 'ate' => '2026-10-31', 'tipo' => 'despesas']);
        $this->assertSame(['periodo' => '01/09/2026 a 31/10/2026', 'tipo' => 'despesas', 'categoria' => 'todas', 'total' => 'R$ 550,00', 'quantidade_de_lancamentos' => 3], $todas);

        // O nome da categoria vale sem acento e sem maiúscula.
        $this->assertSame('R$ 500,00', $this->chamar('total_no_periodo', ['de' => '2026-09-01', 'ate' => '2026-10-31', 'tipo' => 'despesas', 'categoria' => 'mercado'])['total']);
        $this->assertSame('R$ 50,00', $this->chamar('total_no_periodo', ['de' => '2026-09-01', 'ate' => '2026-10-31', 'tipo' => 'despesas', 'categoria' => 'Sem categoria'])['total']);
        $this->assertStringContainsString('transferência', $this->erro('total_no_periodo', ['de' => '2026-09-01', 'ate' => '2026-10-31', 'tipo' => 'despesas', 'categoria' => 'Entre contas']));
    }

    public function test_comparar_periodos_ja_entrega_diferenca_e_variacao(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $this->lancar(-50000, '2026-08-15', $mercado);
        $this->lancar(-60000, '2026-10-15', $mercado);

        $comparacao = $this->chamar('comparar_periodos', [
            'tipo' => 'despesas',
            'categoria' => 'Mercado',
            'periodo' => ['de' => '2026-10-01', 'ate' => '2026-10-31'],
            'comparado_com' => ['de' => '2026-08-01', 'ate' => '2026-08-31'],
        ]);

        $this->assertSame(['datas' => '01/10/2026 a 31/10/2026', 'total' => 'R$ 600,00'], $comparacao['periodo']);
        $this->assertSame(['datas' => '01/08/2026 a 31/08/2026', 'total' => 'R$ 500,00'], $comparacao['comparado_com']);
        $this->assertSame('R$ 100,00 a mais', $comparacao['diferenca_do_periodo_para_o_comparado']);
        $this->assertSame('+20%', $comparacao['variacao']);
    }

    public function test_buscar_lancamentos_filtra_ordena_e_soma_todos_os_encontrados(): void
    {
        $alimentacao = Categoria::factory()->create(['nome' => 'Alimentação fora']);
        $this->lancar(-4590, '2026-10-02', $alimentacao, 'IFOOD *Pedido 1');
        $this->lancar(-8800, '2026-10-09', $alimentacao, 'iFood pedido 2');
        $this->lancar(-2100, '2026-10-20', $alimentacao, 'Ifóod pedido 3');
        $this->lancar(-99000, '2026-10-05', descricao: 'Aluguel');
        $this->lancar(1500, '2026-10-21', descricao: 'Estorno IFOOD');

        $busca = $this->chamar('buscar_lancamentos', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'texto' => 'ifood', 'limite' => 2]);

        $this->assertSame(4, $busca['encontrados']);
        $this->assertSame(2, $busca['mostrando']);
        // A soma é de todos os encontrados, não só dos dois mostrados.
        $this->assertSame('-R$ 154,90', $busca['soma_das_saidas']);
        $this->assertSame('R$ 15,00', $busca['soma_das_entradas']);
        $this->assertSame(['-R$ 88,00', '-R$ 45,90'], array_column($busca['lancamentos'], 'valor'));
        $this->assertSame(['data' => '09/10/2026', 'descricao' => 'iFood pedido 2', 'valor' => '-R$ 88,00', 'categoria' => 'Alimentação fora', 'conta' => 'Conta corrente'], $busca['lancamentos'][0]);

        $recentes = $this->chamar('buscar_lancamentos', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'ordem' => 'recentes', 'limite' => 1]);
        $this->assertSame('Estorno IFOOD', $recentes['lancamentos'][0]['descricao']);
    }

    public function test_lista_categorias_e_saldos(): void
    {
        Categoria::factory()->create(['nome' => 'Mercado']);
        Categoria::factory()->receita()->create(['nome' => 'Salário']);
        Categoria::factory()->transferencia()->create(['nome' => 'Entre contas']);
        $cartao = Conta::factory()->create(['nome' => 'Cartão', 'tipo' => 'cartao_de_credito', 'saldo_inicial' => 0]);
        Conta::factory()->arquivada()->create(['nome' => 'Antiga', 'saldo_inicial' => 999900]);
        $this->lancar(-30000, '2026-11-02', conta: $cartao);
        $this->lancar(-2500, '2026-11-03');

        $this->assertSame(['despesas' => ['Mercado'], 'receitas' => ['Salário'], 'transferencias_entre_contas' => ['Entre contas']], $this->chamar('listar_categorias'));

        $saldos = $this->chamar('saldos_das_contas');
        $this->assertSame([
            ['conta' => 'Cartão', 'tipo' => 'Cartão de crédito', 'saldo' => '-R$ 300,00'],
            ['conta' => 'Conta corrente', 'tipo' => 'Conta corrente', 'saldo' => 'R$ 975,00'],
        ], $saldos['contas']);
        $this->assertSame('R$ 675,00', $saldos['saldo_total']);
    }

    public function test_falha_do_banco_vira_aviso_sem_detalhe_interno(): void
    {
        Schema::drop('lancamentos');

        $mensagem = $this->erro('total_no_periodo', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'tipo' => 'despesas']);

        $this->assertStringContainsString('não foi possível consultar', $mensagem);
        $this->assertStringNotContainsString('lancamentos', $mensagem);
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function entradasRuins(): array
    {
        return [
            'mês fora do formato' => ['resumo_do_mes', ['mes' => '10/2026'], 'AAAA-MM'],
            'mês 13' => ['resumo_do_mes', ['mes' => '2026-13'], 'AAAA-MM'],
            'data que não existe' => ['total_no_periodo', ['de' => '2026-02-30', 'ate' => '2026-03-01', 'tipo' => 'despesas'], 'data que existe'],
            'período invertido' => ['total_no_periodo', ['de' => '2026-10-31', 'ate' => '2026-10-01', 'tipo' => 'despesas'], 'Troque'],
            'tipo desconhecido' => ['total_no_periodo', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'tipo' => 'gastos'], 'despesas, receitas'],
            'categoria que não existe' => ['total_no_periodo', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'tipo' => 'despesas', 'categoria' => 'Viagens'], 'As que existem'],
            'limite alto demais' => ['buscar_lancamentos', ['de' => '2026-10-01', 'ate' => '2026-10-31', 'limite' => 500], 'de 1 a 30'],
            'período sem objeto' => ['comparar_periodos', ['tipo' => 'despesas', 'periodo' => '2026-10', 'comparado_com' => '2026-09'], 'objeto'],
            'ferramenta que não existe' => ['apagar_tudo', [], 'não existe'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    #[DataProvider('entradasRuins')]
    public function test_entrada_ruim_volta_como_erro_que_o_modelo_entende(string $ferramenta, array $entrada, string $trecho): void
    {
        $this->assertStringContainsString($trecho, $this->erro($ferramenta, $entrada));
    }
}
