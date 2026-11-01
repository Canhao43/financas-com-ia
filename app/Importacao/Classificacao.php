<?php

namespace App\Importacao;

use App\Enums\SituacaoDaLinha;
use App\Models\Lancamento;

final readonly class Classificacao
{
    public function __construct(
        /** Posição da linha no arquivo: é por ela que a tela manda a decisão de cada uma. */
        public int $indice,
        public Linha $linha,
        public SituacaoDaLinha $situacao,
        /** O lançamento manual que parece ser o mesmo, quando a situação é Parecida. */
        public ?Lancamento $parecidoCom = null,
    ) {}
}
