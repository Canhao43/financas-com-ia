<?php

namespace App\Enums;

/**
 * Como cada linha do extrato se compara com o que já está no app.
 */
enum SituacaoDaLinha: string
{
    case Nova = 'nova';
    /** Já importada antes: pelo FITID ou pela impressão digital. Nunca entra de novo. */
    case JaExiste = 'ja_existe';
    /** Mesmo valor de um lançamento manual, com data próxima: provavelmente o mesmo gasto. */
    case Parecida = 'parecida';
}
