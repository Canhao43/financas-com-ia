<?php

namespace App\Models;

use App\Enums\TipoConta;
use Database\Factories\ContaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'tipo', 'saldo_inicial', 'arquivada'])]
class Conta extends Model
{
    /** @use HasFactory<ContaFactory> */
    use HasFactory;

    protected $table = 'contas';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConta::class,
            'saldo_inicial' => 'integer',
            'arquivada' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Lancamento, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    /**
     * Saldo atual em centavos: o inicial mais a soma dos lançamentos. A soma é do banco.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeComSaldo(Builder $query): Builder
    {
        return $query->withSum('lancamentos as soma_lancamentos', 'valor');
    }

    /**
     * Usa a soma do comSaldo() se veio na consulta (null quando não há lançamentos); senão, consulta.
     */
    public function saldoAtual(): int
    {
        $soma = array_key_exists('soma_lancamentos', $this->getAttributes())
            ? $this->getAttribute('soma_lancamentos')
            : $this->lancamentos()->sum('valor');

        return $this->saldo_inicial + (int) $soma;
    }
}
