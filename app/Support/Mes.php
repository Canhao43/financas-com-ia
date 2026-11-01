<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class Mes
{
    /**
     * O mês pedido em "AAAA-MM", no dia 1; qualquer outra coisa vira o mês atual.
     */
    public static function doTexto(string $texto): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $texto)
            ? CarbonImmutable::createFromFormat('!Y-m', $texto)
            : CarbonImmutable::today()->startOfMonth();
    }
}
