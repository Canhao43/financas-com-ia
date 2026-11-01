<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Os testes rodam com o banco em memória, que ignora o modo WAL. Aqui o banco é um arquivo,
 * como em produção.
 */
class BancoSqliteTest extends TestCase
{
    private string $arquivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arquivo = storage_path('framework/testing/banco-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->arquivo));
        touch($this->arquivo);

        config(['database.connections.arquivo' => [
            ...config('database.connections.sqlite'),
            'database' => $this->arquivo,
        ]]);
    }

    protected function tearDown(): void
    {
        DB::purge('arquivo');
        File::delete([$this->arquivo, "{$this->arquivo}-wal", "{$this->arquivo}-shm"]);

        parent::tearDown();
    }

    public function test_o_banco_em_arquivo_abre_em_wal_e_espera_quando_esta_ocupado(): void
    {
        $conexao = DB::connection('arquivo');

        $this->assertSame('wal', $conexao->selectOne('pragma journal_mode')->journal_mode);
        $this->assertSame(5000, $conexao->selectOne('pragma busy_timeout')->timeout);
    }
}
