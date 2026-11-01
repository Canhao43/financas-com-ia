<?php

namespace App\Relatorios;

/**
 * Uma categoria no relatório do mês. Sem categoria também vira linha: o dinheiro existiu.
 */
final readonly class LinhaDoRelatorio
{
    public function __construct(
        // null: os lançamentos sem categoria.
        public ?int $categoriaId,
        public string $nome,
        public Comparacao $valor,
        // Quanto a linha é do total do grupo (despesas ou receitas) no mês, em pontos-base.
        public int $participacao,
    ) {}

    /**
     * @return array{categoriaId: int|null, nome: string, total: int, anterior: int, variacao: int|null, participacao: int}
     */
    public function paraArray(): array
    {
        return [
            'categoriaId' => $this->categoriaId,
            'nome' => $this->nome,
            'total' => $this->valor->atual,
            'anterior' => $this->valor->anterior,
            'variacao' => $this->valor->variacao(),
            'participacao' => $this->participacao,
        ];
    }
}
