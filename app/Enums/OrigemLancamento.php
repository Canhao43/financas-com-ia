<?php

namespace App\Enums;

enum OrigemLancamento: string
{
    case Manual = 'manual';
    case Importado = 'importado';
}
