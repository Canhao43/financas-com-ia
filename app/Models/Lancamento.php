<?php

namespace App\Models;

use App\Enums\OrigemLancamento;
use Carbon\CarbonImmutable;
use Database\Factories\LancamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Só o que o formulário edita. Origem e vínculo com a importação quem grava é o importador.
#[Fillable(['conta_id', 'categoria_id', 'data', 'descricao', 'valor'])]
class Lancamento extends Model
{
    /** @use HasFactory<LancamentoFactory> */
    use HasFactory;

    protected $table = 'lancamentos';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'origem' => 'manual',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'immutable_date',
            'valor' => 'integer',
            'origem' => OrigemLancamento::class,
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
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return BelongsTo<Importacao, $this>
     */
    public function importacao(): BelongsTo
    {
        return $this->belongsTo(Importacao::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDoMes(Builder $query, CarbonImmutable $mes): Builder
    {
        return $query->noPeriodo($mes->startOfMonth(), $mes->endOfMonth());
    }

    /**
     * Do dia "de" ao dia "até", os dois inclusive.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNoPeriodo(Builder $query, CarbonImmutable $de, CarbonImmutable $ate): Builder
    {
        // Intervalo semiaberto: com "between", o SQLite (que guarda "2026-10-31 00:00:00" como texto)
        // deixaria o último dia de fora. Coluna com o nome da tabela: o relatório junta as categorias
        // na mesma consulta.
        return $query
            ->where($query->qualifyColumn('data'), '>=', $de->toDateString())
            ->where($query->qualifyColumn('data'), '<', $ate->startOfDay()->addDay()->toDateString());
    }

    public function ehEntrada(): bool
    {
        return $this->valor > 0;
    }
}
