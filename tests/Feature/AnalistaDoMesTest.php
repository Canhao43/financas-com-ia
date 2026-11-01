<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Assistente\AnaliseNaoEscrita;
use App\Assistente\AnalistaDoMes;
use App\Models\Analise;
use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\TransferException;
use Http\Mock\Client as ClienteHttpFalso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Como no assistente, nenhuma chamada de verdade: a API é trocada por respostas gravadas, e os
 * testes conferem o que o app mandou e o que gravou.
 */
class AnalistaDoMesTest extends TestCase
{
    use RefreshDatabase;

    private const string CERTA = 'Em outubro você recebeu R$ 5.000,00 e gastou R$ 718,90, R$ 95,45 a mais que em setembro (+15,3%).';

    private ClienteHttpFalso $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 10:00:00');
        config(['assistente.chave' => 'chave-de-teste']);

        $this->http = new ClienteHttpFalso;
        $this->app->bind(Client::class, fn () => new Client(
            apiKey: 'chave-de-teste',
            requestOptions: ['transporter' => $this->http, 'maxRetries' => 0],
        ));

        $conta = Conta::factory()->create(['nome' => 'Conta Fictícia do Banco X']);
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);
        Lancamento::factory()->saida(62345)->create(['data' => '2026-09-08', 'categoria_id' => $mercado->id, 'conta_id' => $conta->id]);
        Lancamento::factory()->saida(71890)->create([
            'data' => '2026-10-08', 'categoria_id' => $mercado->id, 'conta_id' => $conta->id, 'descricao' => 'COMPRA PADARIA DO ZE',
        ]);
        Lancamento::factory()->entrada(500000)->create(['data' => '2026-10-05', 'categoria_id' => $salario->id, 'conta_id' => $conta->id]);
    }

    private function analista(): AnalistaDoMes
    {
        return $this->app->make(AnalistaDoMes::class);
    }

    private function outubro(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-01');
    }

    /**
     * A resposta como a API devolve com saída estruturada: o raciocínio e um bloco de texto
     * com o JSON.
     *
     * @param  list<string>  $destaques
     */
    private function apiEscreve(string $resumo, array $destaques = [], string $parada = 'end_turn'): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'id' => 'msg_'.uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'assinatura-'.count($this->http->getRequests())],
                ['type' => 'text', 'text' => (string) json_encode(['resumo' => $resumo, 'destaques' => $destaques])],
            ],
            'stop_reason' => $parada,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 300],
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    private function corpo(int $requisicao): array
    {
        return json_decode((string) $this->http->getRequests()[$requisicao]->getBody(), true);
    }

    public function test_escreve_a_analise_com_os_numeros_do_mes_e_confere_os_valores(): void
    {
        $this->apiEscreve(self::CERTA, ['Mercado foi 100% das despesas.', 'O salário não tinha aparecido em setembro.']);

        $analise = $this->analista()->escrever($this->outubro());

        $this->assertCount(1, $this->http->getRequests());
        $requisicao = $this->http->getRequests()[0];
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $requisicao->getHeaderLine('anthropic-beta'));

        // Uma chamada sem ferramentas e sem cache, com a resposta presa ao formato.
        $corpo = $this->corpo(0);
        $this->assertSame('claude-opus-5-5', $corpo['model']);
        $this->assertSame('default', $corpo['fallbacks']);
        $this->assertSame(['type' => 'adaptive'], $corpo['thinking']);
        $this->assertSame('medium', $corpo['output_config']['effort']);
        $this->assertSame('json_schema', $corpo['output_config']['format']['type']);
        $this->assertSame(['resumo', 'destaques'], $corpo['output_config']['format']['schema']['required']);
        $this->assertStringContainsString('Todo valor e toda porcentagem do texto saem dos dados', $corpo['system']);
        $this->assertArrayNotHasKey('tools', $corpo);
        $this->assertArrayNotHasKey('cache_control', $corpo);

        // O pedido leva a data do dia e os números prontos, no formato da tela.
        $pedido = $corpo['messages'][0];
        $this->assertSame('user', $pedido['role']);
        $this->assertSame('Hoje é domingo, 15/11/2026.', $pedido['content'][0]['text']);
        $this->assertStringStartsWith('Escreva a análise de outubro de 2026.', $pedido['content'][1]['text']);
        $this->assertStringContainsString('"total": "R$ 718,90"', $pedido['content'][1]['text']);
        $this->assertStringContainsString('"variacao": "+15,3%"', $pedido['content'][1]['text']);
        $this->assertStringContainsString('"mes_em_andamento": false', $pedido['content'][1]['text']);

        // O mínimo de dado sai do app: nem a descrição do lançamento, nem o nome da conta.
        $enviado = (string) $requisicao->getBody();
        $this->assertStringNotContainsString('PADARIA', $enviado);
        $this->assertStringNotContainsString('Banco X', $enviado);

        $this->assertSame('2026-10', $analise->mes);
        $this->assertSame(self::CERTA, $analise->resumo);
        $this->assertSame(['Mercado foi 100% das despesas.', 'O salário não tinha aparecido em setembro.'], $analise->destaques);
        $this->assertSame([], $analise->valores_sem_fonte);
        $this->assertSame(0, $analise->correcoes);
        $this->assertSame('claude-opus-5-5', $analise->modelo);
        $this->assertSame(['entrada' => 1500, 'saida' => 300, 'cache_lida' => 0, 'cache_criada' => 0, 'chamadas' => 1], $analise->uso);
        $this->assertSame('R$ 718,90', $analise->fatos['despesas']['total']);
        $this->assertSame(64, strlen($analise->impressao));
        $this->assertFalse($this->analista()->desatualizada($analise));
    }

    public function test_valor_sem_fonte_volta_para_o_claude_corrigir(): void
    {
        $this->apiEscreve('Em outubro você gastou R$ 1.000,00 com mercado.', ['Sobraram 85% do que entrou.']);
        $this->apiEscreve(self::CERTA, ['Mercado foi 100% das despesas.']);

        $analise = $this->analista()->escrever($this->outubro());

        $this->assertCount(2, $this->http->getRequests());
        $mensagens = $this->corpo(1)['messages'];
        $this->assertCount(3, $mensagens);
        // A primeira resposta volta inteira, com o raciocínio assinado, e depois dela a lista
        // exata do que não bateu.
        $this->assertSame('assistant', $mensagens[1]['role']);
        $this->assertEquals(['type' => 'thinking', 'thinking' => '', 'signature' => 'assinatura-0'], $mensagens[1]['content'][0]);
        $this->assertSame('user', $mensagens[2]['role']);
        $this->assertStringStartsWith('Estes valores do seu texto não estão nos dados: R$ 1.000,00, 85%.', $mensagens[2]['content']);

        $this->assertSame(self::CERTA, $analise->resumo);
        $this->assertSame([], $analise->valores_sem_fonte);
        $this->assertSame(1, $analise->correcoes);
        $this->assertSame(2, $analise->uso['chamadas']);
    }

    public function test_valor_sem_fonte_que_persiste_fica_gravado_para_a_tela_avisar(): void
    {
        $this->apiEscreve('Em outubro você gastou R$ 1.000,00 com mercado.');
        $this->apiEscreve('Em outubro você gastou R$ 1.000,00 com mercado, de novo.', ['Gastou 12% a mais.']);

        $analise = $this->analista()->escrever($this->outubro());

        // Uma correção só: depois dela, grava com o aviso em vez de insistir e gastar mais.
        $this->assertCount(2, $this->http->getRequests());
        $this->assertSame('Em outubro você gastou R$ 1.000,00 com mercado, de novo.', $analise->resumo);
        $this->assertSame(['R$ 1.000,00', '12%'], $analise->valores_sem_fonte);
        $this->assertSame(1, $analise->correcoes);
    }

    public function test_sem_correcao_configurada_grava_direto_com_o_aviso(): void
    {
        config(['assistente.correcoes_da_analise' => 0]);
        $this->apiEscreve('Em outubro você gastou R$ 1.000,00 com mercado.');

        $analise = $this->analista()->escrever($this->outubro());

        $this->assertCount(1, $this->http->getRequests());
        $this->assertSame(['R$ 1.000,00'], $analise->valores_sem_fonte);
        $this->assertSame(0, $analise->correcoes);
    }

    public function test_escrever_de_novo_substitui_a_analise_do_mes(): void
    {
        $this->apiEscreve('Primeira versão, R$ 718,90.');
        $this->apiEscreve(self::CERTA);

        $this->analista()->escrever($this->outubro());
        $this->analista()->escrever($this->outubro());

        $this->assertSame(self::CERTA, Analise::query()->sole()->resumo);
    }

    public function test_mes_sem_lancamentos_nao_chama_a_api(): void
    {
        $this->expectExceptionObject(new AnaliseNaoEscrita('Não há lançamentos em agosto de 2026 para analisar.'));

        try {
            $this->analista()->escrever(CarbonImmutable::parse('2026-08-01'));
        } finally {
            $this->assertCount(0, $this->http->getRequests());
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function respostasSemAnalise(): array
    {
        return [
            'recusa' => ['refusal', self::CERTA, 'não quis escrever'],
            'cortada pelo tamanho' => ['max_tokens', self::CERTA, 'tamanho máximo'],
            'resumo vazio' => ['end_turn', '  ', 'análise vazia'],
        ];
    }

    #[DataProvider('respostasSemAnalise')]
    public function test_resposta_sem_analise_nao_apaga_a_anterior(string $parada, string $resumo, string $trecho): void
    {
        $anterior = Analise::query()->create([
            'mes' => '2026-10', 'resumo' => 'A análise de antes.', 'destaques' => [], 'fatos' => [],
            'impressao' => str_repeat('0', 64), 'valores_sem_fonte' => [],
        ]);
        $this->apiEscreve($resumo, [], $parada);

        try {
            $this->analista()->escrever($this->outubro());
            $this->fail('Deveria ter lançado AnaliseNaoEscrita.');
        } catch (AnaliseNaoEscrita $erro) {
            $this->assertStringContainsString($trecho, $erro->getMessage());
        }

        $this->assertSame('A análise de antes.', $anterior->fresh()?->resumo);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function errosDaApi(): array
    {
        return [
            'chave recusada' => [401, 'authentication_error', 'chave da API'],
            'limite de uso' => [429, 'rate_limit_error', 'limite de uso'],
            'servidor sobrecarregado' => [529, 'overloaded_error', 'não respondeu agora'],
        ];
    }

    #[DataProvider('errosDaApi')]
    public function test_erro_da_api_vira_mensagem_para_a_pessoa(int $status, string $tipo, string $trecho): void
    {
        $this->http->addResponse(new Response($status, ['Content-Type' => 'application/json'], (string) json_encode([
            'type' => 'error',
            'error' => ['type' => $tipo, 'message' => 'erro de teste'],
        ])));

        try {
            $this->analista()->escrever($this->outubro());
            $this->fail('Deveria ter lançado AnaliseNaoEscrita.');
        } catch (AnaliseNaoEscrita $erro) {
            $this->assertStringContainsString($trecho, $erro->getMessage());
        }

        $this->assertSame(0, Analise::query()->count());
    }

    public function test_sem_conexao_vira_mensagem_para_a_pessoa(): void
    {
        $this->http->addException(new TransferException('sem rede'));

        $this->expectExceptionObject(new AnaliseNaoEscrita('Não foi possível falar com o Claude (sem conexão ou demorou demais). Tente de novo.'));

        $this->analista()->escrever($this->outubro());
    }

    public function test_destaque_em_branco_fica_de_fora(): void
    {
        $this->apiEscreve(self::CERTA, ['Mercado foi 100% das despesas.', '   ', ' O salário apareceu. ']);

        $analise = $this->analista()->escrever($this->outubro());

        $this->assertSame(['Mercado foi 100% das despesas.', 'O salário apareceu.'], $analise->destaques);
    }

    public function test_analise_fica_desatualizada_quando_os_numeros_do_mes_mudam(): void
    {
        $this->apiEscreve(self::CERTA);
        $analise = $this->analista()->escrever($this->outubro());

        // Lançamento de outro mês não mexe na análise de outubro.
        Lancamento::factory()->saida(1000)->create(['data' => '2026-11-02']);
        $this->assertFalse($this->analista()->desatualizada($analise));

        Lancamento::factory()->saida(1000)->create(['data' => '2026-10-20']);
        $this->assertTrue($this->analista()->desatualizada($analise));
    }

    public function test_analise_do_mes_em_andamento_fica_desatualizada_quando_o_mes_acaba(): void
    {
        Lancamento::factory()->saida(5000)->create(['data' => '2026-11-03']);
        $this->apiEscreve('Até agora, em novembro, você gastou R$ 50,00.');

        $analise = $this->analista()->escrever(CarbonImmutable::parse('2026-11-01'));

        $this->assertTrue($analise->fatos['mes_em_andamento']);
        $this->assertStringContainsString('"mes_em_andamento": true', $this->corpo(0)['messages'][0]['content'][1]['text']);

        $this->travelTo('2026-11-30 23:00:00');
        $this->assertFalse($this->analista()->desatualizada($analise));

        $this->travelTo('2026-12-01 08:00:00');
        $this->assertTrue($this->analista()->desatualizada($analise));
    }
}
