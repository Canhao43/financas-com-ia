<?php

namespace App\Relatorios;

use App\Enums\TipoCategoria;
use App\Models\Lancamento;
use App\Support\Porcentagem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Toda conta de relatório do app passa por aqui: a tela de relatório, o painel e, no M5, as
 * ferramentas do assistente. Um lugar só para calcular é o que garante que a tela e a IA nunca
 * deem dois números diferentes para a mesma pergunta.
 */
final class RelatorioMensal
{
    public function gerar(CarbonImmutable $mes): Relatorio
    {
        $mes = $mes->startOfMonth();
        $atual = $this->somasPorCategoria($mes);
        $anterior = $this->somasPorCategoria($mes->subMonthNoOverflow());

        $receitas = new Comparacao($this->total($atual, 'entradas'), $this->total($anterior, 'entradas'));
        $despesas = new Comparacao($this->total($atual, 'saidas'), $this->total($anterior, 'saidas'));

        return new Relatorio(
            mes: $mes,
            receitas: $receitas,
            despesas: $despesas,
            resultado: new Comparacao($receitas->atual + $despesas->atual, $receitas->anterior + $despesas->anterior),
            // O que saiu de uma conta para outra. Conta só a ponta da saída, senão cada
            // transferência apareceria duas vezes.
            transferencias: (int) abs($atual->where('transferencia', true)->sum('saidas')),
            despesasPorCategoria: $this->linhas($atual, $anterior, 'saidas'),
            receitasPorCategoria: $this->linhas($atual, $anterior, 'entradas'),
        );
    }

    /**
     * Receitas e despesas do mês, sem as transferências.
     *
     * @return array{receitas: int, despesas: int}
     */
    public function totais(CarbonImmutable $mes): array
    {
        $somas = $this->somasPorCategoria($mes->startOfMonth());

        return [
            'receitas' => $this->total($somas, 'entradas'),
            'despesas' => $this->total($somas, 'saidas'),
        ];
    }

    /**
     * Uma consulta por mês: entradas e saídas somadas por categoria. Separar por sinal, e não
     * pelo tipo da categoria, garante que a soma das linhas é sempre o total.
     *
     * @return Collection<string, array{categoriaId: int|null, nome: string, transferencia: bool, entradas: int, saidas: int}>
     */
    private function somasPorCategoria(CarbonImmutable $mes): Collection
    {
        return Lancamento::query()
            ->doMes($mes)
            ->leftJoin('categorias', 'categorias.id', '=', 'lancamentos.categoria_id')
            ->groupBy('lancamentos.categoria_id', 'categorias.nome', 'categorias.tipo')
            ->select(['lancamentos.categoria_id', 'categorias.nome', 'categorias.tipo'])
            ->selectRaw('sum(case when lancamentos.valor > 0 then lancamentos.valor else 0 end) as entradas')
            ->selectRaw('sum(case when lancamentos.valor < 0 then lancamentos.valor else 0 end) as saidas')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $linha) => [
                // Sem categoria é uma linha como as outras, com chave própria.
                ($linha->categoria_id === null ? 'sem' : (string) $linha->categoria_id) => [
                    'categoriaId' => $linha->categoria_id === null ? null : (int) $linha->categoria_id,
                    'nome' => $linha->nome === null ? 'Sem categoria' : (string) $linha->nome,
                    'transferencia' => $linha->tipo === TipoCategoria::Transferencia->value,
                    'entradas' => (int) $linha->entradas,
                    'saidas' => (int) $linha->saidas,
                ],
            ]);
    }

    /**
     * @param  Collection<string, array{categoriaId: int|null, nome: string, transferencia: bool, entradas: int, saidas: int}>  $somas
     * @param  'entradas'|'saidas'  $lado
     */
    private function total(Collection $somas, string $lado): int
    {
        return (int) $somas->where('transferencia', false)->sum($lado);
    }

    /**
     * Uma linha por categoria que teve valor neste mês ou no anterior: a categoria que zerou
     * também aparece, porque "parei de gastar com isso" é parte da comparação. Da maior para
     * a menor; no empate, a que era maior no mês anterior, e depois o nome.
     *
     * @param  Collection<string, array{categoriaId: int|null, nome: string, transferencia: bool, entradas: int, saidas: int}>  $atual
     * @param  Collection<string, array{categoriaId: int|null, nome: string, transferencia: bool, entradas: int, saidas: int}>  $anterior
     * @param  'entradas'|'saidas'  $lado
     * @return list<LinhaDoRelatorio>
     */
    private function linhas(Collection $atual, Collection $anterior, string $lado): array
    {
        $total = $this->total($atual, $lado);

        return array_values($atual->keys()->merge($anterior->keys())->unique()
            ->map(fn (string $chave) => [$atual->get($chave), $anterior->get($chave)])
            ->reject(fn (array $par) => ($par[0]['transferencia'] ?? false) || ($par[1]['transferencia'] ?? false))
            ->map(fn (array $par) => new LinhaDoRelatorio(
                categoriaId: $par[0]['categoriaId'] ?? $par[1]['categoriaId'] ?? null,
                nome: $par[0]['nome'] ?? $par[1]['nome'] ?? 'Sem categoria',
                valor: new Comparacao($par[0][$lado] ?? 0, $par[1][$lado] ?? 0),
                participacao: Porcentagem::emPontosBase(abs($par[0][$lado] ?? 0), abs($total)),
            ))
            ->reject(fn (LinhaDoRelatorio $linha) => $linha->valor->atual === 0 && $linha->valor->anterior === 0)
            ->sort(fn (LinhaDoRelatorio $a, LinhaDoRelatorio $b) => [abs($b->valor->atual), abs($b->valor->anterior), $a->nome]
                <=> [abs($a->valor->atual), abs($a->valor->anterior), $b->nome])
            ->all());
    }
}
