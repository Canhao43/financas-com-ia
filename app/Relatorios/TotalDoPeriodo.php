<?php

namespace App\Relatorios;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use App\Models\Lancamento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Despesas ou receitas de um período qualquer, de uma categoria ou de todas. Mesma regra do
 * relatório do mês: transferência entre contas não é despesa nem receita.
 */
final class TotalDoPeriodo
{
    /**
     * @param  'despesas'|'receitas'  $tipo
     * @param  Categoria|'sem'|null  $categoria  null: todas; "sem": só os lançamentos sem categoria.
     * @return array{total: int, quantidade: int}
     */
    public function calcular(CarbonImmutable $de, CarbonImmutable $ate, string $tipo, Categoria|string|null $categoria = null): array
    {
        $consulta = Lancamento::query()
            ->noPeriodo($de, $ate)
            ->where('valor', $tipo === 'despesas' ? '<' : '>', 0)
            ->when($categoria === 'sem', fn (Builder $q) => $q->whereNull('categoria_id'))
            ->when($categoria instanceof Categoria, fn (Builder $q) => $q->where('categoria_id', $categoria instanceof Categoria ? $categoria->id : null))
            ->when($categoria === null, fn (Builder $q) => $q->whereDoesntHave('categoria', fn (Builder $c) => $c
                ->where('tipo', TipoCategoria::Transferencia)));

        return [
            'total' => (int) (clone $consulta)->sum('valor'),
            'quantidade' => (clone $consulta)->count(),
        ];
    }
}
