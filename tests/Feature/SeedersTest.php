<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\CategoriaPadraoSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_padrao_nao_cria_usuario(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertGreaterThan(0, Categoria::query()->count());
        $this->assertDatabaseHas('categorias', ['nome' => 'Transferência entre contas', 'tipo' => 'transferencia']);
    }

    public function test_categorias_padrao_nao_duplicam_ao_rodar_de_novo(): void
    {
        $this->seed(CategoriaPadraoSeeder::class);
        $antes = Categoria::query()->count();

        $this->seed(CategoriaPadraoSeeder::class);

        $this->assertSame($antes, Categoria::query()->count());
    }

    public function test_demonstracao_gera_dados_ficticios(): void
    {
        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame(2, Conta::query()->count());
        $this->assertSame(21, Lancamento::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_demonstracao_nao_roda_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);

        // Direto, sem o db:seed: em produção o comando pararia antes, pedindo confirmação.
        $this->app->make(DemonstracaoSeeder::class)->run();
    }
}
