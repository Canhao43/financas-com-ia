<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\Analise;
use App\Models\Categoria;
use App\Models\Lancamento;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as ClienteHttpFalso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A análise pela tela do relatório: o botão, o que volta quando dá errado e o que a página
 * mostra. Nenhuma chamada de verdade à API.
 */
class AnaliseTest extends TestCase
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
     * @param  list<string>  $destaques
     */
    private function apiEscreve(string $resumo, array $destaques = []): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'id' => 'msg_'.uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => (string) json_encode(['resumo' => $resumo, 'destaques' => $destaques])]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 300],
        ])));
    }

    private function analiseAntiga(): Analise
    {
        return Analise::query()->create([
            'mes' => '2026-10', 'resumo' => 'A análise de antes.', 'destaques' => ['Um destaque.'], 'fatos' => [],
            'impressao' => str_repeat('0', 64), 'valores_sem_fonte' => ['R$ 1,00'], 'modelo' => 'claude-opus-5-5',
        ]);
    }

    public function test_escreve_a_analise_e_mostra_no_relatorio_do_mes(): void
    {
        $this->apiEscreve('Em outubro você gastou R$ 718,90, R$ 95,45 a mais que em setembro.', ['Mercado foi 100% das despesas.']);

        $this->post(route('relatorios.analise'), ['mes' => '2026-10'])
            ->assertRedirect(route('relatorios', ['mes' => '2026-10']));

        $this->assertCount(1, $this->http->getRequests());
        $this->get(route('relatorios', ['mes' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('relatorios/Mes')
                ->where('analiseLigada', true)
                ->where('temLancamentos', true)
                ->where('analise', [
                    'resumo' => 'Em outubro você gastou R$ 718,90, R$ 95,45 a mais que em setembro.',
                    'destaques' => ['Mercado foi 100% das despesas.'],
                    'valoresSemFonte' => [],
                    'desatualizada' => false,
                    'escritaEm' => '2026-11-15',
                    'modelo' => 'claude-opus-5-5',
                ]));
    }

    public function test_relatorio_avisa_quando_a_analise_ficou_para_tras(): void
    {
        $this->analiseAntiga();

        $this->get(route('relatorios', ['mes' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analise.resumo', 'A análise de antes.')
                ->where('analise.valoresSemFonte', ['R$ 1,00'])
                ->where('analise.desatualizada', true));
    }

    public function test_mes_sem_analise_nem_lancamentos_nao_mostra_a_de_outro_mes(): void
    {
        $this->analiseAntiga();

        $this->get(route('relatorios', ['mes' => '2026-08']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analise', null)
                ->where('temLancamentos', false));
    }

    public function test_erro_da_api_aparece_no_cartao_e_a_analise_anterior_fica(): void
    {
        $this->analiseAntiga();
        $this->http->addResponse(new Response(529, ['Content-Type' => 'application/json'], (string) json_encode([
            'type' => 'error',
            'error' => ['type' => 'overloaded_error', 'message' => 'erro de teste'],
        ])));

        $this->from(route('relatorios', ['mes' => '2026-10']))
            ->post(route('relatorios.analise'), ['mes' => '2026-10'])
            ->assertRedirect(route('relatorios', ['mes' => '2026-10']))
            ->assertSessionHasErrors(['analise' => 'O Claude não respondeu agora. Tente de novo em instantes.']);

        $this->assertSame('A análise de antes.', Analise::query()->sole()->resumo);
    }

    public function test_mes_sem_lancamentos_nao_chama_a_api(): void
    {
        $this->post(route('relatorios.analise'), ['mes' => '2026-08'])
            ->assertSessionHasErrors(['analise' => 'Não há lançamentos em agosto de 2026 para analisar.']);

        $this->assertCount(0, $this->http->getRequests());
    }

    public function test_sem_chave_a_analise_fica_desligada(): void
    {
        config(['assistente.chave' => null]);

        $this->get(route('relatorios', ['mes' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page->where('analiseLigada', false));
        $this->post(route('relatorios.analise'), ['mes' => '2026-10'])
            ->assertSessionHasErrors('analise');

        $this->assertCount(0, $this->http->getRequests());
        $this->assertSame(0, Analise::query()->count());
    }

    public function test_mes_invalido_e_recusado(): void
    {
        foreach (['', '2026-13', 'outubro', '2026-10-01'] as $mes) {
            $this->post(route('relatorios.analise'), ['mes' => $mes])->assertSessionHasErrors('mes');
        }

        $this->assertCount(0, $this->http->getRequests());
    }

    public function test_sem_login_nao_escreve(): void
    {
        auth()->logout();

        $this->post(route('relatorios.analise'), ['mes' => '2026-10'])->assertRedirect(route('login'));

        $this->assertCount(0, $this->http->getRequests());
    }
}
