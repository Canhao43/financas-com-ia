<?php

namespace App\Support;

final class Porcentagem
{
    /**
     * Quanto a parte é do todo, em pontos-base (10000 = 100%; 2534 = 25,34%), arredondado para
     * o mais próximo, com o meio para longe do zero. Inteiro do começo ao fim: a mesma conta
     * dá sempre o mesmo resultado, sem a sobra do ponto flutuante.
     */
    public static function emPontosBase(int $parte, int $todo): int
    {
        if ($todo === 0) {
            return 0;
        }

        $sinal = ($parte < 0) !== ($todo < 0) ? -1 : 1;
        $parte = abs($parte);
        $todo = abs($todo);

        return $sinal * intdiv(2 * $parte * 10000 + $todo, 2 * $todo);
    }
}
