<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * O mês explicado em texto pelo Claude, com os números que ele recebeu e o resultado da
 * conferência.
 */
#[Fillable(['mes', 'resumo', 'destaques', 'fatos', 'impressao', 'valores_sem_fonte', 'correcoes', 'modelo', 'uso'])]
class Analise extends Model
{
    protected $table = 'analises';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destaques' => 'array',
            'fatos' => 'array',
            'valores_sem_fonte' => 'array',
            'correcoes' => 'integer',
            'uso' => 'array',
        ];
    }
}
