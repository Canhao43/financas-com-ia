<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_painel_vazio_abre_com_tudo_zerado(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('mes', '2026-10')
                ->where('saldoTotal', 0)
                ->has('ultimos', 0));
    }

    public function test_soma_contas_ativas_e_separa_o_mes_atual(): void
    {
        $banco = Conta::factory()->create(['saldo_inicial' => 100000]);
        Lancamento::factory()->for($banco)->entrada(200000)->create(['data' => '2026-10-05']);
        Lancamento::factory()->for($banco)->saida(50000)->create(['data' => '2026-10-31']);
        Lancamento::factory()->for($banco)->saida(10000)->create(['data' => '2026-09-28']);
        Conta::factory()->arquivada()->create(['saldo_inicial' => 999900]);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('saldoTotal', 240000)
                ->where('entradasDoMes', 200000)
                ->where('saidasDoMes', -50000)
                ->has('ultimos', 3)
                ->where('ultimos.0.data', '2026-10-31'));
    }

    public function test_o_mes_do_painel_segue_o_horario_de_sao_paulo(): void
    {
        // 23h30 do último dia de novembro em São Paulo; em UTC já é dezembro.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 02:30:00', 'UTC'));

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('mes', '2026-11'));
    }

    public function test_mostra_so_os_cinco_ultimos_lancamentos(): void
    {
        Lancamento::factory()->count(7)->create();

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('ultimos', 5));
    }

    private function backup(string $quando, bool $sucesso = true, ?string $erro = null): void
    {
        Backup::query()->forceCreate([
            'arquivo' => 'financas.sqlite.age',
            'sucesso' => $sucesso,
            'erro' => $erro,
            'created_at' => $quando,
        ]);
    }

    private function avisoDeBackup(): mixed
    {
        return $this->get(route('dashboard'))->inertiaProps('avisoDeBackup');
    }

    public function test_backup_de_menos_de_36_horas_nao_gera_aviso(): void
    {
        config(['backup.avisar' => true]);
        // O painel está em 15/10 às 12h: isto foi 35h antes.
        $this->backup('2026-10-14 01:00:00');
        $this->backup('2026-10-15 03:00:00', sucesso: false, erro: 'Falhou hoje, mas o de ontem vale.');

        $this->assertNull($this->avisoDeBackup());
    }

    public function test_avisa_quando_o_ultimo_backup_bom_passou_de_36_horas(): void
    {
        config(['backup.avisar' => true]);
        // A falha de antes do último backup bom já foi superada: não entra no aviso.
        $this->backup('2026-10-12 03:00:00', sucesso: false, erro: 'Falha antiga, já superada.');
        $this->backup('2026-10-13 23:00:00');

        $this->assertSame('O último backup que deu certo foi em 13/10/2026 às 23:00.', $this->avisoDeBackup());
    }

    public function test_o_aviso_mostra_a_falha_mais_recente_depois_do_ultimo_backup_bom(): void
    {
        config(['backup.avisar' => true]);
        $this->backup('2026-10-10 03:00:00', sucesso: false, erro: 'Falha antiga, já superada.');
        $this->backup('2026-10-11 03:00:00');
        $this->backup('2026-10-12 03:00:00', sucesso: false, erro: 'Falta o destino do rclone em BACKUP_DESTINO.');
        $this->backup('2026-10-13 03:00:00', sucesso: false, erro: 'O rclone não enviou o backup: sem rede');

        $this->assertSame(
            'O último backup que deu certo foi em 11/10/2026 às 03:00. Última falha: O rclone não enviou o backup: sem rede',
            $this->avisoDeBackup(),
        );
    }

    public function test_avisa_quando_nenhum_backup_deu_certo(): void
    {
        config(['backup.avisar' => true]);
        $this->assertSame('Nenhum backup deu certo até agora.', $this->avisoDeBackup());

        $this->backup('2026-10-15 03:00:00', sucesso: false, erro: 'Falta a chave pública do age em BACKUP_CHAVE_PUBLICA.');
        $this->assertSame(
            'Nenhum backup deu certo até agora. Última falha: Falta a chave pública do age em BACKUP_CHAVE_PUBLICA.',
            $this->avisoDeBackup(),
        );
    }

    public function test_fora_de_producao_o_painel_nao_avisa_de_backup(): void
    {
        config(['backup.avisar' => false]);

        $this->assertNull($this->avisoDeBackup());
    }
}
