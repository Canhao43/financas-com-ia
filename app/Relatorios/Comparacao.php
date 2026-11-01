<?php

namespace App\Relatorios;

use App\Support\Porcentagem;

/**
 * Um valor do mês e o mesmo valor no mês anterior, em centavos e com o sinal do app
 * (despesa negativa).
 */
final readonly class Comparacao
{
    public function __construct(
        public int $atual,
        public int $anterior,
    ) {}

    /**
     * Quanto cresceu ou caiu, em pontos-base, pelo tamanho do valor: gastar R$ 120 depois de
     * R$ 100 é +20%, seja despesa ou receita. Sem nada no mês anterior não há base, e a
     * resposta é null, não um "infinito por cento".
     */
    public function variacao(): ?int
    {
        if ($this->anterior === 0) {
            return null;
        }

        return Porcentagem::emPontosBase(abs($this->atual) - abs($this->anterior), abs($this->anterior));
    }

    /**
     * @return array{atual: int, anterior: int, variacao: int|null}
     */
    public function paraArray(): array
    {
        return [
            'atual' => $this->atual,
            'anterior' => $this->anterior,
            'variacao' => $this->variacao(),
        ];
    }
}
