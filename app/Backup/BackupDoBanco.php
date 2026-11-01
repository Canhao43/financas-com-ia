<?php

namespace App\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use PDOException;
use Throwable;

/**
 * Copia o banco, confere a cópia, cifra e manda para fora do servidor. Cada etapa só roda
 * se a anterior deu certo, e toda tentativa fica registrada em `backups`.
 */
class BackupDoBanco
{
    public function criar(): Backup
    {
        $pasta = config()->string('backup.pasta');
        $copia = $pasta.'/financas-'.now()->format('Y-m-d-His').'.sqlite';
        $cifrada = "{$copia}.age";

        try {
            [$chave, $destino] = $this->configuracao();
            File::ensureDirectoryExists($pasta);

            $this->copiar($copia);
            $this->conferir($copia);
            $this->cifrar($copia, $cifrada, $chave);
            $this->enviar($cifrada, $destino);
        } catch (Throwable $erro) {
            report($erro);

            return Backup::query()->create([
                'arquivo' => basename($cifrada),
                'sucesso' => false,
                'erro' => $erro->getMessage(),
            ]);
        } finally {
            // A cópia sem cifra é o banco inteiro legível: não pode sobrar no disco.
            File::delete($copia);
        }

        // Só depois de um envio que deu certo: backup quebrado nunca apaga os bons.
        $this->apagarAntigas($pasta);

        return Backup::query()->create([
            'arquivo' => basename($cifrada),
            'sucesso' => true,
            'tamanho' => File::size($cifrada),
        ]);
    }

    /**
     * Prova de que a cópia é um banco do app inteiro, e não um arquivo vazio ou corrompido.
     */
    public function conferir(string $copia): void
    {
        if (! is_file($copia) || filesize($copia) === 0) {
            throw new BackupFalhou('A cópia do banco saiu vazia.');
        }

        try {
            $banco = new PDO("sqlite:{$copia}", options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $integridade = $this->primeiroValor($banco, 'pragma integrity_check');
            $migracoes = $this->primeiroValor($banco, 'select count(*) from migrations');
        } catch (PDOException $erro) {
            throw new BackupFalhou("A cópia não abre ou não é o banco do app: {$erro->getMessage()}", previous: $erro);
        } finally {
            // Arquivo aberto não pode ser apagado no Windows, e a cópia é apagada logo depois.
            $banco = null;
        }

        if ($integridade !== 'ok') {
            throw new BackupFalhou("A cópia do banco não passou na conferência de integridade: {$integridade}");
        }

        if ((int) $migracoes === 0) {
            throw new BackupFalhou('A cópia não tem as tabelas do app.');
        }
    }

    private function primeiroValor(PDO $banco, string $sql): mixed
    {
        $consulta = $banco->query($sql);

        return $consulta === false ? null : $consulta->fetchColumn();
    }

    /**
     * @return array{string, string}
     */
    private function configuracao(): array
    {
        $chave = config('backup.chave_publica');
        $destino = config('backup.destino');

        if (! is_string($chave) || $chave === '') {
            throw new BackupFalhou('Falta a chave pública do age em BACKUP_CHAVE_PUBLICA.');
        }

        if (! is_string($destino) || $destino === '') {
            throw new BackupFalhou('Falta o destino do rclone em BACKUP_DESTINO.');
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new BackupFalhou('O backup só sabe copiar banco SQLite.');
        }

        return [$chave, $destino];
    }

    private function copiar(string $copia): void
    {
        // VACUUM INTO grava uma cópia consistente sem parar o site: copiar o arquivo no meio
        // de uma escrita pode levar um banco pela metade.
        DB::statement('VACUUM INTO ?', [$copia]);
    }

    private function cifrar(string $copia, string $cifrada, string $chave): void
    {
        $resultado = Process::timeout(120)
            ->run(['age', '--encrypt', '--recipient', $chave, '--output', $cifrada, $copia]);

        if ($resultado->failed()) {
            File::delete($cifrada);

            throw new BackupFalhou('O age não cifrou a cópia: '.trim($resultado->errorOutput()));
        }
    }

    private function enviar(string $cifrada, string $destino): void
    {
        $resultado = Process::timeout(600)
            ->run(['rclone', 'copyto', $cifrada, rtrim($destino, '/').'/'.basename($cifrada)]);

        if ($resultado->failed()) {
            throw new BackupFalhou('O rclone não enviou o backup: '.trim($resultado->errorOutput()));
        }
    }

    private function apagarAntigas(string $pasta): void
    {
        // O nome leva data e hora, então a ordem alfabética é a cronológica.
        collect(File::glob("{$pasta}/financas-*.sqlite.age"))
            ->sortDesc()
            ->slice(config()->integer('backup.manter_no_servidor'))
            ->each(fn (string $antiga) => File::delete($antiga));
    }
}
