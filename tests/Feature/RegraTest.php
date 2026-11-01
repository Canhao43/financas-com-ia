<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lancamento;
use App\Models\Regra;
use App\Models\User;
use App\Regras\Categorizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function regra(string $texto, Categoria $categoria): Regra
    {
        return Regra::query()->create(['texto' => $texto, 'categoria_id' => $categoria->id]);
    }

    private function categoriaPara(string $descricao, int $valor): ?string
    {
        // Um categorizador novo por consulta: ele guarda as regras da primeira vez que as lê.
        return app(Categorizador::class)->categoriaPara($descricao, $valor)?->nome;
    }

    public function test_cria_a_regra_com_o_texto_normalizado(): void
    {
        $transporte = Categoria::factory()->create(['nome' => 'Transporte']);

        $this->post(route('regras.store'), ['texto' => '  Uber   Viagem ', 'categoria_id' => $transporte->id])
            ->assertRedirect(route('regras.index'));

        $this->assertSame('UBER VIAGEM', Regra::query()->sole()->texto);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function textosRecusados(): array
    {
        return [
            'mesmo texto com outra grafia' => ['ifood', 'Já existe uma regra com esse texto.'],
            'curto demais' => ['ab', 'Use pelo menos 3 letras: texto curto demais combina com quase tudo.'],
        ];
    }

    #[DataProvider('textosRecusados')]
    public function test_texto_repetido_ou_curto_e_recusado(string $texto, string $mensagem): void
    {
        $categoria = Categoria::factory()->create();
        $this->regra('IFOOD', $categoria);

        $this->post(route('regras.store'), ['texto' => $texto, 'categoria_id' => $categoria->id])
            ->assertSessionHasErrors(['texto' => $mensagem]);
    }

    public function test_edita_mantendo_o_proprio_texto_e_exclui(): void
    {
        $antiga = Categoria::factory()->create();
        $nova = Categoria::factory()->create();
        $regra = $this->regra('IFOOD', $antiga);

        $this->put(route('regras.update', $regra), ['texto' => 'iFood', 'categoria_id' => $nova->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($nova->id, $regra->refresh()->categoria_id);

        $this->delete(route('regras.destroy', $regra))->assertRedirect(route('regras.index'));
        $this->assertModelMissing($regra);
    }

    public function test_a_regra_mais_especifica_vence_sem_depender_da_ordem_de_cadastro(): void
    {
        $this->regra('UBER', Categoria::factory()->create(['nome' => 'Transporte']));
        $this->regra('UBER EATS', Categoria::factory()->create(['nome' => 'Alimentação fora']));

        $this->assertSame('Alimentação fora', $this->categoriaPara('Uber Eats *Pedido 123', -4590));
        $this->assertSame('Transporte', $this->categoriaPara('UBER *TRIP', -2310));
        $this->assertNull($this->categoriaPara('PADARIA', -500));
    }

    public function test_a_regra_so_vale_para_a_direcao_da_categoria(): void
    {
        $this->regra('UBER', Categoria::factory()->create(['nome' => 'Transporte']));
        $this->regra('EMPRESA', Categoria::factory()->receita()->create(['nome' => 'Salário']));

        // O estorno da corrida é entrada: não é gasto com transporte.
        $this->assertNull($this->categoriaPara('ESTORNO UBER', 2310));
        $this->assertSame('Salário', $this->categoriaPara('PAGTO EMPRESA FICTICIA', 650000));
        $this->assertNull($this->categoriaPara('PIX PARA EMPRESA', -10000));
    }

    public function test_regra_de_transferencia_vale_nas_duas_direcoes(): void
    {
        $this->regra('FATURA CARTAO', Categoria::factory()->transferencia()->create(['nome' => 'Entre contas']));

        // Sai da conta corrente e entra no cartão.
        $this->assertSame('Entre contas', $this->categoriaPara('PAGTO FATURA CARTAO', -120000));
        $this->assertSame('Entre contas', $this->categoriaPara('FATURA CARTAO RECEBIDA', 120000));
    }

    public function test_acento_e_caixa_nao_importam(): void
    {
        $this->regra('PADARIA SAO JOAO', Categoria::factory()->create(['nome' => 'Padaria']));

        $this->assertSame('Padaria', $this->categoriaPara('Padaria  São João', -500));
    }

    public function test_aplicar_categoriza_so_os_lancamentos_sem_categoria(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $outra = Categoria::factory()->create(['nome' => 'Outra']);
        $this->regra('MERCADO', $mercado);

        $sem = Lancamento::factory()->saida(1000)->create(['descricao' => 'Mercado do bairro', 'categoria_id' => null]);
        $escolhido = Lancamento::factory()->saida(1000)->create(['descricao' => 'Mercado do bairro', 'categoria_id' => $outra->id]);
        $semRegra = Lancamento::factory()->saida(1000)->create(['descricao' => 'Farmácia', 'categoria_id' => null]);

        $this->post(route('regras.aplicar'))
            ->assertRedirect(route('regras.index'))
            ->assertSessionHas('inertia.flash_data.toast.message', '1 lançamento categorizado.');

        $this->assertSame($mercado->id, $sem->refresh()->categoria_id);
        // A escolha da pessoa vale mais que a regra.
        $this->assertSame($outra->id, $escolhido->refresh()->categoria_id);
        $this->assertNull($semRegra->refresh()->categoria_id);
    }

    public function test_excluir_a_categoria_leva_as_regras_dela(): void
    {
        $categoria = Categoria::factory()->create();
        $regra = $this->regra('IFOOD', $categoria);

        $this->get(route('categorias.edit', $categoria))
            ->assertInertia(fn (Assert $page) => $page->where('categoria.regras', 1));

        $this->delete(route('categorias.destroy', $categoria));

        $this->assertModelMissing($regra);
    }

    public function test_lista_as_regras_e_quantos_lancamentos_estao_sem_categoria(): void
    {
        $this->regra('UBER', Categoria::factory()->create(['nome' => 'Transporte']));
        Lancamento::factory()->count(2)->create(['categoria_id' => null]);

        $this->get(route('regras.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('regras/Index')
                ->has('regras', 1)
                ->where('regras.0.texto', 'UBER')
                ->where('regras.0.categoria', 'Transporte')
                ->where('regras.0.tipo', 'despesa')
                ->where('semCategoria', 2));

        $this->get(route('regras.create'))
            ->assertInertia(fn (Assert $page) => $page->component('regras/Form')->has('categorias', 1));
    }
}
