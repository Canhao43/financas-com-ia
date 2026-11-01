<?php

namespace Tests\Feature;

use App\Backup\BackupDoBanco;
use App\Backup\BackupFalhou;
use App\Models\Backup;
use App\Models\Conta;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery\MockInterface;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VACUUM INTO não roda dentro de transação, e o RefreshDatabase põe cada teste numa:
 * por isso aqui o banco é recriado a cada teste.
 *
 * O age e o rclone são de mentira. O age só copia o arquivo, para o teste conseguir abrir o
 * "cifrado" e conferir o conteúdo. Os de verdade rodam no teste da imagem Docker, no CI.
 */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    private const string ARQUIVO = 'financas-2026-11-01-030000.sqlite.age';

    private string $pasta;

    private ?string $falhaDo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasta = storage_path('framework/testing/backups-'.uniqid());
        config([
            'backup.pasta' => $this->pasta,
            'backup.chave_publica' => 'age1chavedeteste',
            'backup.destino' => 'remoto:financas/',
            'backup.manter_no_servidor' => 3,
        ]);
        $this->travelTo('2026-11-01 03:00:00');
        Process::fake(fn (PendingProcess $processo) => $this->processoDeMentira($processo));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pasta);

        parent::tearDown();
    }

    private function processoDeMentira(PendingProcess $processo): mixed
    {
        $comando = (array) $processo->command;

        if ($comando[0] === $this->falhaDo) {
            return Process::result(errorOutput: "{$comando[0]}: falha de teste\n", exitCode: 1);
        }

        if ($comando[0] === 'age') {
            $saida = $comando[array_search('--output', $comando, true) + 1];
            copy((string) end($comando), (string) $saida);
        }

        return Process::result();
    }

    /**
     * @return list<string>
     */
    private function arquivosNaPasta(): array
    {
        return collect(File::files($this->pasta))->map->getFilename()->sort()->values()->all();
    }

    private function criarCopiaAntiga(string $dia): void
    {
        File::ensureDirectoryExists($this->pasta);
        File::put("{$this->pasta}/financas-{$dia}-030000.sqlite.age", 'cifrado');
    }

    public function test_copia_cifra_e_envia_para_fora_do_servidor(): void
    {
        Conta::factory()->create(['nome' => 'Conta fictícia']);

        $this->artisan('backup:criar')->assertSuccessful();

        $cifrada = "{$this->pasta}/".self::ARQUIVO;
        Process::assertRan(fn (PendingProcess $p) => $p->command === [
            'age', '--encrypt', '--recipient', 'age1chavedeteste', '--output', $cifrada,
            "{$this->pasta}/financas-2026-11-01-030000.sqlite",
        ]);
        Process::assertRan(fn (PendingProcess $p) => $p->command === [
            'rclone', 'copyto', $cifrada, 'remoto:financas/'.self::ARQUIVO,
        ]);

        // O age de mentira só copiou: dá para abrir e ver que o banco inteiro foi junto.
        $copia = new PDO("sqlite:{$cifrada}");
        $this->assertSame('Conta fictícia', $copia->query('select nome from contas')?->fetchColumn());
        $copia = null;

        $registro = Backup::query()->sole();
        $this->assertTrue($registro->sucesso);
        $this->assertSame(self::ARQUIVO, $registro->arquivo);
        $this->assertSame(filesize($cifrada), $registro->tamanho);
    }

    public function test_a_copia_sem_cifra_nao_sobra_no_disco(): void
    {
        $this->artisan('backup:criar')->assertSuccessful();

        $this->assertSame([self::ARQUIVO], $this->arquivosNaPasta());
    }

    public function test_se_o_age_falha_nada_e_enviado_e_nada_sobra(): void
    {
        $this->falhaDo = 'age';

        $this->artisan('backup:criar')
            ->expectsOutputToContain('age: falha de teste')
            ->assertFailed();

        Process::assertDidntRun(fn (PendingProcess $p) => $p->command[0] === 'rclone');
        $this->assertSame([], $this->arquivosNaPasta());
        $this->assertFalse(Backup::query()->sole()->sucesso);
    }

    public function test_se_o_envio_falha_a_copia_cifrada_fica_no_servidor(): void
    {
        $this->falhaDo = 'rclone';

        $this->artisan('backup:criar')->assertFailed();

        $registro = Backup::query()->sole();
        $this->assertFalse($registro->sucesso);
        $this->assertStringContainsString('rclone: falha de teste', (string) $registro->erro);
        $this->assertSame([self::ARQUIVO], $this->arquivosNaPasta());
    }

    public function test_copia_que_nao_passa_na_conferencia_nao_e_cifrada_nem_enviada(): void
    {
        $this->partialMock(BackupDoBanco::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('conferir')->andThrow(new BackupFalhou('Cópia corrompida.')));

        $this->artisan('backup:criar')->assertFailed();

        Process::assertNothingRan();
        $this->assertSame('Cópia corrompida.', Backup::query()->sole()->erro);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function configuracaoIncompleta(): array
    {
        return [
            'sem chave' => ['backup.chave_publica', 'BACKUP_CHAVE_PUBLICA'],
            'sem destino' => ['backup.destino', 'BACKUP_DESTINO'],
        ];
    }

    #[DataProvider('configuracaoIncompleta')]
    public function test_sem_configuracao_falha_dizendo_o_que_falta(string $chave, string $variavel): void
    {
        config([$chave => null]);

        $this->artisan('backup:criar')
            ->expectsOutputToContain($variavel)
            ->assertFailed();

        Process::assertNothingRan();
        $this->assertFalse(Backup::query()->sole()->sucesso);
    }

    public function test_guarda_no_servidor_so_as_ultimas_copias(): void
    {
        foreach (['2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31'] as $dia) {
            $this->criarCopiaAntiga($dia);
        }
        File::put("{$this->pasta}/leia-me.txt", 'não é backup');

        $this->artisan('backup:criar')->assertSuccessful();

        $this->assertSame([
            'financas-2026-10-30-030000.sqlite.age',
            'financas-2026-10-31-030000.sqlite.age',
            self::ARQUIVO,
            'leia-me.txt',
        ], $this->arquivosNaPasta());
    }

    public function test_backup_que_falha_nao_apaga_as_copias_antigas(): void
    {
        foreach (['2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31'] as $dia) {
            $this->criarCopiaAntiga($dia);
        }
        $this->falhaDo = 'rclone';

        $this->artisan('backup:criar')->assertFailed();

        $this->assertCount(5, $this->arquivosNaPasta());
    }

    private function copiaDoBanco(string $nome): string
    {
        File::ensureDirectoryExists($this->pasta);
        $copia = "{$this->pasta}/{$nome}.sqlite";
        DB::statement('VACUUM INTO ?', [$copia]);

        return $copia;
    }

    public function test_a_conferencia_aceita_a_copia_boa_e_recusa_a_que_nao_serve(): void
    {
        $conferencia = app(BackupDoBanco::class);
        $conferencia->conferir($this->copiaDoBanco('boa'));

        // Cabeçalho estragado: o SQLite nem reconhece o arquivo como banco. Estragar o meio do
        // arquivo daria um erro diferente a cada tabela nova, conforme a página atingida.
        $corrompida = $this->copiaDoBanco('corrompida');
        $conteudo = (string) file_get_contents($corrompida);
        file_put_contents($corrompida, substr_replace($conteudo, str_repeat("\xFF", 100), 0, 100));

        // Abre e consulta normalmente, mas o índice não bate com a tabela.
        $inconsistente = $this->copiaDoBanco('inconsistente');
        $banco = new PDO("sqlite:{$inconsistente}");
        $banco->exec('create table t (a, b); create index t_a on t (a); insert into t values (1, 2), (3, 4);');
        $banco->exec('pragma writable_schema = on');
        $banco->exec("update sqlite_master set sql = 'CREATE INDEX t_a ON t (b)' where name = 't_a'");
        $banco = null;

        $deOutroApp = "{$this->pasta}/de-outro-app.sqlite";
        $banco = new PDO("sqlite:{$deOutroApp}");
        $banco->exec('create table migrations (id integer)');
        $banco = null;

        $casos = [
            'não abre' => $corrompida,
            'integridade' => $inconsistente,
            'não tem as tabelas do app' => $deOutroApp,
            'saiu vazia' => "{$this->pasta}/nao-existe.sqlite",
        ];

        foreach ($casos as $motivo => $copia) {
            try {
                $conferencia->conferir($copia);
                $this->fail('A conferência aceitou '.basename($copia));
            } catch (BackupFalhou $erro) {
                $this->assertStringContainsString($motivo, $erro->getMessage());
            }
        }
    }

    public function test_o_backup_roda_todo_dia_as_tres_da_manha(): void
    {
        $agendado = collect(app(Schedule::class)->events())
            ->filter(fn (Event $evento) => str_contains((string) $evento->command, 'backup:criar'));

        $this->assertCount(1, $agendado);
        $this->assertSame('0 3 * * *', $agendado->first()?->expression);
    }
}
