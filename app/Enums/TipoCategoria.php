<?php

namespace App\Enums;

enum TipoCategoria: string
{
    case Despesa = 'despesa';
    case Receita = 'receita';

    public function rotulo(): string
    {
        return match ($this) {
            self::Despesa => 'Despesa',
            self::Receita => 'Receita',
        };
    }

    /**
     * A categoria que combina com a direção do lançamento: saída é despesa, entrada é receita.
     */
    public static function daDirecao(string $direcao): self
    {
        return $direcao === 'entrada' ? self::Receita : self::Despesa;
    }
}
