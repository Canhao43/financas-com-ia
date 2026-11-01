<?php

namespace Tests\Feature;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lista_com_a_quantidade_de_lancamentos(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        Lancamento::factory()->count(2)->create(['categoria_id' => $mercado->id]);

        $this->get(route('categorias.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('categorias/Index')
                ->where('categorias.0.nome', 'Mercado')
                ->where('categorias.0.lancamentos', 2));
    }

    public function test_nome_repetido_so_e_recusado_no_mesmo_tipo(): void
    {
        Categoria::factory()->create(['nome' => 'Outros', 'tipo' => TipoCategoria::Despesa]);

        $this->post(route('categorias.store'), ['nome' => 'Outros', 'tipo' => 'despesa'])
            ->assertSessionHasErrors(['nome' => 'Já existe uma categoria com esse nome.']);

        $this->post(route('categorias.store'), ['nome' => 'Outros', 'tipo' => 'receita'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('categorias', 2);
    }

    public function test_edita_mantendo_o_proprio_nome(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Farmácia']);

        $this->put(route('categorias.update', $categoria), ['nome' => 'Farmácia', 'tipo' => 'despesa'])
            ->assertSessionHasNoErrors();
    }

    public function test_tipo_nao_muda_quando_ja_tem_lancamentos(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Mercado']);
        Lancamento::factory()->create(['categoria_id' => $categoria->id]);

        $this->put(route('categorias.update', $categoria), ['nome' => 'Mercado', 'tipo' => 'receita'])
            ->assertSessionHasErrors(['tipo' => 'Categoria com lançamentos não pode trocar de tipo.']);

        $this->assertSame(TipoCategoria::Despesa, $categoria->refresh()->tipo);
    }

    public function test_tipo_muda_quando_ainda_nao_tem_lancamentos(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Reembolso']);

        $this->put(route('categorias.update', $categoria), ['nome' => 'Reembolso', 'tipo' => 'receita'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TipoCategoria::Receita, $categoria->refresh()->tipo);
    }

    public function test_excluir_deixa_os_lancamentos_sem_categoria(): void
    {
        $categoria = Categoria::factory()->create();
        $lancamento = Lancamento::factory()->create(['categoria_id' => $categoria->id]);

        $this->delete(route('categorias.destroy', $categoria))->assertRedirect(route('categorias.index'));

        $this->assertModelMissing($categoria);
        $this->assertNull($lancamento->refresh()->categoria_id);
    }
}
