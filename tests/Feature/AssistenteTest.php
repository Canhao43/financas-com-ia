<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\SituacaoDaResposta;
use App\Models\Categoria;
use App\Models\Conversa;
use App\Models\Lancamento;
use App\Models\Mensagem;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as ClienteHttpFalso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nenhuma chamada de verdade à API: o cliente HTTP do SDK é trocado por um que devolve
 * respostas gravadas no formato da API, e cada teste confere o que o app mandou.
 */
class AssistenteTest extends TestCase
{
    use RefreshDatabase;

    private ClienteHttpFalso $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-11-15 10:00:00');
        config(['assistente.chave' => 'chave-de-teste']);

        $this->http = new ClienteHttpFalso;
        $this->app->bind(Client::class, fn () => new Client(
            apiKey: 'chave-de-teste',
            requestOptions: ['transporter' => $this->http, 'maxRetries' => 0],
        ));

        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        Lancamento::factory()->saida(62345)->create(['data' => '2026-09-08', 'categoria_id' => $mercado->id]);
        Lancamento::factory()->saida(71890)->create(['data' => '2026-10-08', 'categoria_id' => $mercado->id]);
    }

    /**
     * @param  list<array<string, mixed>>  $conteudo
     */
    private function apiResponde(array $conteudo, string $parada, string $modelo = 'claude-opus-5-5'): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'id' => 'msg_'.count($this->http->getRequests()).uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => $modelo,
            'content' => $conteudo,
            'stop_reason' => $parada,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 80, 'cache_read_input_tokens' => 1000, 'cache_creation_input_tokens' => 0],
        ])));
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function consulta(string $id, string $ferramenta, array $entrada): array
    {
        return ['type' => 'tool_use', 'id' => $id, 'name' => $ferramenta, 'input' => $entrada];
    }

    /**
     * @return array<string, mixed>
     */
    private function corpo(int $requisicao): array
    {
        return json_decode((string) $this->http->getRequests()[$requisicao]->getBody(), true);
    }

    private function ultimaResposta(): Mensagem
    {
        return Mensagem::query()->where('papel', 'assistente')->latest('id')->firstOrFail();
    }

    public function test_responde_consultando_o_banco_e_confere_os_valores(): void
    {
        $this->apiResponde([
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'assinatura-1'],
            $this->consulta('toolu_1', 'resumo_do_mes', ['mes' => '2026-10']),
        ], 'tool_use');
        $this->apiResponde([['type' => 'text', 'text' => 'Em outubro você gastou R$ 718,90 com mercado, +15,3% em relação a setembro.']], 'end_turn');

        $resposta = $this->post(route('assistente.store'), ['pergunta' => 'Quanto gastei com mercado em outubro?']);

        $conversa = Conversa::query()->sole();
        $resposta->assertRedirect(route('assistente.show', $conversa));
        $this->assertCount(2, $this->http->getRequests());

        // A primeira chamada: modelo, troca automática em caso de recusa, raciocínio adaptativo,
        // cache nas instruções, as seis ferramentas e a pergunta com a data do dia.
        $primeira = $this->http->getRequests()[0];
        $this->assertSame('Bearer chave-de-teste', $primeira->getHeaderLine('Authorization'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $primeira->getHeaderLine('anthropic-beta'));
        $corpo = $this->corpo(0);
        $this->assertSame('claude-opus-5-5', $corpo['model']);
        $this->assertSame('default', $corpo['fallbacks']);
        $this->assertSame(['type' => 'adaptive'], $corpo['thinking']);
        $this->assertSame(['effort' => 'medium'], $corpo['output_config']);
        $this->assertSame(['type' => 'ephemeral'], $corpo['cache_control']);
        $this->assertSame(['type' => 'ephemeral'], $corpo['system'][0]['cache_control']);
        $this->assertCount(6, $corpo['tools']);
        $this->assertSame([[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Hoje é domingo, 15/11/2026.'],
                ['type' => 'text', 'text' => 'Quanto gastei com mercado em outubro?'],
            ],
        ]], $corpo['messages']);

        // A segunda: a resposta do Claude volta inteira, com o raciocínio assinado, e o
        // resultado da consulta vem com os números prontos.
        $mensagens = $this->corpo(1)['messages'];
        // Mesmo conteúdo e mesma assinatura; a ordem das chaves no JSON é do SDK.
        $this->assertEquals(['type' => 'thinking', 'thinking' => '', 'signature' => 'assinatura-1'], $mensagens[1]['content'][0]);
        $this->assertSame('toolu_1', $mensagens[2]['content'][0]['tool_use_id']);
        $this->assertFalse($mensagens[2]['content'][0]['is_error']);
        $this->assertStringContainsString('"total":"R$ 718,90"', $mensagens[2]['content'][0]['content']);

        $gravada = $this->ultimaResposta();
        $this->assertSame(SituacaoDaResposta::Respondida, $gravada->situacao);
        $this->assertSame([], $gravada->valores_sem_fonte);
        $this->assertSame([['ferramenta' => 'resumo_do_mes', 'descricao' => 'Resumo de 10/2026', 'erro' => false]], $gravada->consultas);
        $this->assertSame('claude-opus-5-5', $gravada->modelo);
        $this->assertSame(['entrada' => 2400, 'saida' => 160, 'cache_lida' => 2000, 'cache_criada' => 0, 'chamadas' => 2], $gravada->uso);

        $this->get(route('assistente.show', $conversa))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assistente/Conversa')
                ->where('conversa.titulo', 'Quanto gastei com mercado em outubro?')
                ->has('mensagens', 2)
                ->where('mensagens.1.texto', 'Em outubro você gastou R$ 718,90 com mercado, +15,3% em relação a setembro.')
                ->where('mensagens.1.valoresSemFonte', []));
    }

    public function test_valor_que_o_claude_calculou_sozinho_fica_marcado_sem_fonte(): void
    {
        $this->apiResponde([$this->consulta('toolu_1', 'resumo_do_mes', ['mes' => '2026-10'])], 'tool_use');
        $this->apiResponde([['type' => 'text', 'text' => 'Em outubro foram R$ 718,90; somando com setembro, R$ 1.342,35.']], 'end_turn');

        $this->post(route('assistente.store'), ['pergunta' => 'Quanto gastei com mercado?']);

        $this->assertSame(['R$ 1.342,35'], $this->ultimaResposta()->valores_sem_fonte);
    }

    public function test_valor_citado_na_pergunta_nao_e_sem_fonte(): void
    {
        $this->apiResponde([$this->consulta('toolu_1', 'resumo_do_mes', ['mes' => '2026-10'])], 'tool_use');
        $this->apiResponde([['type' => 'text', 'text' => 'Sim, passou de R$ 500,00: foram R$ 718,90.']], 'end_turn');

        $this->post(route('assistente.store'), ['pergunta' => 'Gastei mais que R$ 500,00 com mercado em outubro?']);

        $this->assertSame([], $this->ultimaResposta()->valores_sem_fonte);
    }

    public function test_consulta_com_erro_volta_para_o_claude_corrigir(): void
    {
        $this->apiResponde([$this->consulta('toolu_1', 'resumo_do_mes', ['mes' => 'outubro'])], 'tool_use');
        $this->apiResponde([$this->consulta('toolu_2', 'resumo_do_mes', ['mes' => '2026-10'])], 'tool_use');
        $this->apiResponde([['type' => 'text', 'text' => 'Foram R$ 718,90.']], 'end_turn');

        $this->post(route('assistente.store'), ['pergunta' => 'Quanto gastei em outubro?']);

        $resultado = $this->corpo(1)['messages'][2]['content'][0];
        $this->assertTrue($resultado['is_error']);
        $this->assertStringContainsString('AAAA-MM', $resultado['content']);
        $gravada = $this->ultimaResposta();
        $this->assertSame([true, false], array_column($gravada->consultas ?? [], 'erro'));
        $this->assertSame([], $gravada->valores_sem_fonte);
    }

    public function test_o_historico_leva_so_o_texto_das_perguntas_respondidas(): void
    {
        $conversa = Conversa::query()->create(['titulo' => 'Mercado']);
        $conversa->mensagens()->create(['papel' => 'usuario', 'texto' => 'Quanto gastei com mercado em outubro?', 'hoje' => '2026-11-14']);
        $conversa->mensagens()->create(['papel' => 'assistente', 'texto' => 'Foram R$ 718,90.', 'situacao' => 'respondida', 'valores_das_consultas' => ['R$718,90']]);
        $conversa->mensagens()->create(['papel' => 'usuario', 'texto' => 'Pergunta que deu erro', 'hoje' => '2026-11-14']);
        $conversa->mensagens()->create(['papel' => 'assistente', 'texto' => 'O Claude não respondeu agora.', 'situacao' => 'erro']);

        // Repetir um valor de uma resposta anterior não é valor sem fonte.
        $this->apiResponde([['type' => 'text', 'text' => 'Como eu disse, foram R$ 718,90.']], 'end_turn');

        $this->post(route('assistente.perguntar', $conversa), ['pergunta' => 'Repete?'])
            ->assertRedirect(route('assistente.show', $conversa));

        $this->assertSame([
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Hoje é sábado, 14/11/2026.'],
                ['type' => 'text', 'text' => 'Quanto gastei com mercado em outubro?'],
            ]],
            ['role' => 'assistant', 'content' => 'Foram R$ 718,90.'],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Hoje é domingo, 15/11/2026.'],
                ['type' => 'text', 'text' => 'Repete?'],
            ]],
        ], $this->corpo(0)['messages']);
        $this->assertSame([], $this->ultimaResposta()->valores_sem_fonte);
    }

    public function test_recusa_vira_aviso(): void
    {
        $this->apiResponde([['type' => 'text', 'text' => '']], 'refusal');

        $this->post(route('assistente.store'), ['pergunta' => 'Pergunta qualquer']);

        $this->assertSame(SituacaoDaResposta::Recusada, $this->ultimaResposta()->situacao);
    }

    public function test_para_depois_do_teto_de_rodadas(): void
    {
        config(['assistente.max_rodadas' => 2]);
        $this->apiResponde([$this->consulta('toolu_1', 'listar_categorias', [])], 'tool_use');
        $this->apiResponde([$this->consulta('toolu_2', 'listar_categorias', [])], 'tool_use');
        $this->apiResponde([$this->consulta('toolu_3', 'listar_categorias', [])], 'tool_use');

        $this->post(route('assistente.store'), ['pergunta' => 'Pergunta que não acaba']);

        $this->assertCount(2, $this->http->getRequests());
        $this->assertSame(SituacaoDaResposta::Limite, $this->ultimaResposta()->situacao);
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
    public function test_erro_da_api_vira_mensagem_e_a_pergunta_fica_gravada(int $status, string $tipo, string $trecho): void
    {
        $this->http->addResponse(new Response($status, ['Content-Type' => 'application/json'], (string) json_encode([
            'type' => 'error',
            'error' => ['type' => $tipo, 'message' => 'erro de teste'],
        ])));

        $this->post(route('assistente.store'), ['pergunta' => 'Quanto gastei?']);

        $gravada = $this->ultimaResposta();
        $this->assertSame(SituacaoDaResposta::Erro, $gravada->situacao);
        $this->assertStringContainsString($trecho, $gravada->texto);
        $this->assertSame(1, Mensagem::query()->where('papel', 'usuario')->count());
    }

    public function test_sem_chave_o_assistente_fica_desligado(): void
    {
        config(['assistente.chave' => null]);

        $this->get(route('assistente.index'))
            ->assertInertia(fn (Assert $page) => $page->component('assistente/Index')->where('configurado', false));
        $this->post(route('assistente.store'), ['pergunta' => 'Quanto gastei?'])
            ->assertSessionHasErrors('pergunta');

        $this->assertCount(0, $this->http->getRequests());
        $this->assertSame(0, Conversa::query()->count());
    }

    public function test_pergunta_vazia_ou_longa_e_recusada(): void
    {
        $this->post(route('assistente.store'), ['pergunta' => '   '])->assertSessionHasErrors('pergunta');
        $this->post(route('assistente.store'), ['pergunta' => str_repeat('a', 501)])->assertSessionHasErrors('pergunta');

        $this->assertCount(0, $this->http->getRequests());
    }

    public function test_lista_e_apaga_conversas(): void
    {
        $antiga = Conversa::query()->create(['titulo' => 'Antiga']);
        $antiga->mensagens()->create(['papel' => 'usuario', 'texto' => 'Oi', 'hoje' => '2026-11-01']);
        $this->travel(1)->days();
        Conversa::query()->create(['titulo' => 'Recente']);

        $this->get(route('assistente.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('configurado', true)
                ->where('conversas.0.titulo', 'Recente')
                ->where('conversas.1.titulo', 'Antiga'));

        $this->delete(route('assistente.destroy', $antiga))->assertRedirect(route('assistente.index'));

        $this->assertModelMissing($antiga);
        $this->assertSame(0, Mensagem::query()->count());
    }
}
