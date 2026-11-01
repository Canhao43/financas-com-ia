<?php

namespace App\Models;

use App\Enums\StatusImportacao;
use App\Importacao\Linha;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um arquivo de extrato: primeiro a prévia (pendente), depois o registro do que entrou (concluída).
 */
#[Fillable([
    'conta_id', 'status', 'arquivo', 'final_da_conta', 'inicio', 'fim', 'saldo_banco', 'saldo_em', 'linhas',
    'importados', 'vinculados', 'ja_existiam', 'ignorados',
])]
class Importacao extends Model
{
    protected $table = 'importacoes';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusImportacao::class,
            'inicio' => 'immutable_date',
            'fim' => 'immutable_date',
            'saldo_banco' => 'integer',
            'saldo_em' => 'immutable_date',
            'linhas' => 'array',
            'importados' => 'integer',
            'vinculados' => 'integer',
            'ja_existiam' => 'integer',
            'ignorados' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conta, $this>
     */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }

    /**
     * Os que ela criou e os manuais que ela vinculou.
     *
     * @return HasMany<Lancamento, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    public function pendente(): bool
    {
        return $this->status === StatusImportacao::Pendente;
    }

    /**
     * @return list<Linha>
     */
    public function linhasDoExtrato(): array
    {
        /** @var list<array{data: string, valor: int, descricao: string, fitid: string|null, impressao: string}> $linhas */
        $linhas = $this->linhas ?? [];

        return array_map(Linha::deArray(...), $linhas);
    }
}
