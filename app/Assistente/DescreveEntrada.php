<?php

namespace App\Assistente;

/**
 * Pedaços de texto para as descrições das consultas, a partir da entrada crua do modelo.
 */
trait DescreveEntrada
{
    /**
     * @param  array<array-key, mixed>  $entrada
     */
    private function campo(array $entrada, string $campo, string $padrao = '?'): string
    {
        $valor = $entrada[$campo] ?? null;

        return is_string($valor) || is_int($valor) ? (string) $valor : $padrao;
    }

    /**
     * "2026-10-01" vira "01/10/2026"; o que não for data passa como veio.
     *
     * @param  array<array-key, mixed>  $entrada
     */
    private function dataDe(array $entrada, string $campo): string
    {
        $valor = $this->campo($entrada, $campo);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) ? "{$partes[3]}/{$partes[2]}/{$partes[1]}" : $valor;
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     */
    private function periodoDe(array $entrada): string
    {
        return $this->dataDe($entrada, 'de').' a '.$this->dataDe($entrada, 'ate');
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     */
    private function categoriaDe(array $entrada): string
    {
        $categoria = $entrada['categoria'] ?? null;

        return is_string($categoria) && trim($categoria) !== '' ? ', '.trim($categoria) : '';
    }
}
