<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LancamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conta_id', 'categoria_id', 'data', 'descricao', 'valor'])]
class Lancamento extends Model
{
    /** @use HasFactory<LancamentoFactory> */
    use HasFactory;

    protected $table = 'lancamentos';

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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDoMes(Builder $query, CarbonImmutable $mes): Builder
    {
        // Intervalo semiaberto: com "between dia 1 e dia 31", o SQLite (que guarda "2026-10-31 00:00:00"
        // como texto) deixaria o último dia do mês de fora.
        return $query
            ->where('data', '>=', $mes->startOfMonth()->toDateString())
            ->where('data', '<', $mes->startOfMonth()->addMonth()->toDateString());
    }

    public function ehEntrada(): bool
    {
        return $this->valor > 0;
    }
}
