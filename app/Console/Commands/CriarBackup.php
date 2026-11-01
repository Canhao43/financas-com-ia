<?php

namespace App\Console\Commands;

use App\Backup\BackupDoBanco;
use Illuminate\Console\Command;

class CriarBackup extends Command
{
    protected $signature = 'backup:criar';

    protected $description = 'Faz o backup cifrado do banco e envia para fora do servidor';

    public function handle(BackupDoBanco $backup): int
    {
        $registro = $backup->criar();

        if (! $registro->sucesso) {
            $this->error("O backup falhou: {$registro->erro}");

            return self::FAILURE;
        }

        $this->info("Backup {$registro->arquivo} enviado (".max(1, intdiv((int) $registro->tamanho, 1024)).' KB).');

        return self::SUCCESS;
    }
}
