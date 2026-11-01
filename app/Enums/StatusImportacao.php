<?php

namespace App\Enums;

enum StatusImportacao: string
{
    /** Arquivo lido, esperando a pessoa conferir a prévia. */
    case Pendente = 'pendente';
    case Concluida = 'concluida';
}
