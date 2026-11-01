<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Texto
{
    /**
     * Maiúsculas, sem acento e sem espaço repetido. É a forma em que descrição de banco e texto de
     * regra são comparados: "Padaria  São João" e "PADARIA SAO JOAO" viram a mesma coisa.
     */
    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->upper()->squish()->toString();
    }
}
