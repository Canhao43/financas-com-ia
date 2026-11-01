<?php

namespace App\Support;

/**
 * Converte entre o que a pessoa digita ("1.234,56") e centavos inteiros, sem passar por float.
 */
final class Dinheiro
{
    /**
     * Vírgula é sempre o separador decimal; ponto é de milhar — exceto quando é o único
     * separador e tem um ou dois dígitos depois ("12.50"), como o teclado numérico do
     * celular costuma mandar. Devolve null se o texto não for um valor.
     */
    public static function paraCentavos(string $texto, bool $permitirNegativo = false): ?int
    {
        $texto = trim($texto);
        $sinal = 1;

        if ($permitirNegativo && str_starts_with($texto, '-')) {
            $sinal = -1;
            $texto = ltrim(substr($texto, 1));
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $texto) || preg_match('/^\d+(,\d{1,2})?$/', $texto)) {
            [$inteiro, $decimal] = array_pad(explode(',', str_replace('.', '', $texto)), 2, '');
        } elseif (preg_match('/^\d+\.\d{1,2}$/', $texto)) {
            [$inteiro, $decimal] = explode('.', $texto);
        } else {
            return null;
        }

        // Mais de 13 dígitos inteiros não cabe com folga num bigint de centavos.
        if (strlen(ltrim($inteiro, '0')) > 13) {
            return null;
        }

        return $sinal * ((int) $inteiro * 100 + (int) str_pad($decimal, 2, '0'));
    }

    /**
     * Centavos para o formato de digitação: 123456 → "1.234,56".
     */
    public static function paraTexto(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $sinal.number_format(intdiv($centavos, 100), 0, ',', '.').','.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
