<?php

namespace App\Models;

use App\Enums\SituacaoDaResposta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma pergunta (papel "usuario") ou uma resposta (papel "assistente").
 */
#[Fillable(['papel', 'texto', 'hoje', 'situacao', 'consultas', 'valores_das_consultas', 'valores_sem_fonte', 'modelo', 'uso'])]
class Mensagem extends Model
{
    protected $table = 'mensagens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hoje' => 'immutable_date',
            'situacao' => SituacaoDaResposta::class,
            'consultas' => 'array',
            'valores_das_consultas' => 'array',
            'valores_sem_fonte' => 'array',
            'uso' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversa, $this>
     */
    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class);
    }
}
