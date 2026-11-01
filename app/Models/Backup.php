<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma tentativa de backup. O arquivo em si mora fora do servidor; aqui fica só o registro.
 */
#[Fillable(['arquivo', 'sucesso', 'tamanho', 'erro'])]
class Backup extends Model
{
    protected $table = 'backups';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'tamanho' => 'integer',
        ];
    }
}
