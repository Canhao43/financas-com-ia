<?php

namespace App\Relatorios;

use Carbon\CarbonImmutable;

/**
 * O mês em números: receitas, despesas e resultado, cada um com o mês anterior, e as duas
 * listas por categoria. Transferência entre contas fica fora de tudo e aparece só somada.
 */
final readonly class Relatorio
{
    /**
     * @param  list<LinhaDoRelatorio>  $despesasPorCategoria
     * @param  list<LinhaDoRelatorio>  $receitasPorCategoria
     */
    public function __construct(
        public CarbonImmutable $mes,
        public Comparacao $receitas,
        public Comparacao $despesas,
        public Comparacao $resultado,
        public int $transferencias,
        public array $despesasPorCategoria,
        public array $receitasPorCategoria,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paraArray(): array
    {
        return [
            'receitas' => $this->receitas->paraArray(),
            'despesas' => $this->despesas->paraArray(),
            // Sem variação: o resultado pode trocar de sinal, e "−150%" não diz nada.
            'resultado' => ['atual' => $this->resultado->atual, 'anterior' => $this->resultado->anterior],
            'transferencias' => $this->transferencias,
            'despesasPorCategoria' => array_map(fn (LinhaDoRelatorio $linha) => $linha->paraArray(), $this->despesasPorCategoria),
            'receitasPorCategoria' => array_map(fn (LinhaDoRelatorio $linha) => $linha->paraArray(), $this->receitasPorCategoria),
        ];
    }
}
