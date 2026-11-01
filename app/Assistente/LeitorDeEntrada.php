<?php

namespace App\Assistente;

use App\Models\Categoria;
use App\Support\Texto;
use Carbon\CarbonImmutable;

/**
 * Lê e valida o que o Claude mandou para uma ferramenta. A entrada é do modelo, não do código:
 * chega com o tipo errado, a data impossível ou a categoria com outro nome, e cada caso vira
 * uma mensagem que ele entende e corrige.
 */
final class LeitorDeEntrada
{
    /**
     * @param  array<array-key, mixed>  $entrada
     */
    public static function mes(array $entrada, string $campo): CarbonImmutable
    {
        $texto = $entrada[$campo] ?? null;

        if (! is_string($texto) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $texto)) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser um mês no formato AAAA-MM, como 2026-10.");
        }

        return CarbonImmutable::createFromFormat('!Y-m', $texto);
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     */
    public static function data(array $entrada, string $campo): CarbonImmutable
    {
        $texto = $entrada[$campo] ?? null;

        if (! is_string($texto) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $partes)
            || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser uma data que existe, no formato AAAA-MM-DD.");
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $texto);
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function periodo(array $entrada): array
    {
        $de = self::data($entrada, 'de');
        $ate = self::data($entrada, 'ate');

        if ($de->greaterThan($ate)) {
            throw new EntradaInvalida('A data "de" vem depois da data "ate". Troque as duas.');
        }

        return [$de, $ate];
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     * @param  list<string>  $opcoes
     */
    public static function opcao(array $entrada, string $campo, array $opcoes, ?string $padrao = null): string
    {
        $valor = $entrada[$campo] ?? $padrao;

        if (! is_string($valor) || ! in_array($valor, $opcoes, true)) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser um destes: ".implode(', ', $opcoes).'.');
        }

        return $valor;
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     */
    public static function inteiro(array $entrada, string $campo, int $minimo, int $maximo, int $padrao): int
    {
        $valor = $entrada[$campo] ?? $padrao;

        if (! is_int($valor) || $valor < $minimo || $valor > $maximo) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser um número inteiro de {$minimo} a {$maximo}.");
        }

        return $valor;
    }

    /**
     * @param  array<array-key, mixed>  $entrada
     */
    public static function textoOpcional(array $entrada, string $campo): ?string
    {
        $valor = $entrada[$campo] ?? null;

        if ($valor !== null && ! is_string($valor)) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser texto.");
        }

        return $valor === null || trim($valor) === '' ? null : trim($valor);
    }

    /**
     * A categoria pelo nome, sem ligar para acento e maiúscula. "Sem categoria" é a dos
     * lançamentos que não têm nenhuma; ausente é "todas".
     *
     * @param  array<array-key, mixed>  $entrada
     * @return Categoria|'sem'|null
     */
    public static function categoria(array $entrada, string $campo): Categoria|string|null
    {
        $nome = self::textoOpcional($entrada, $campo);

        if ($nome === null) {
            return null;
        }

        if (Texto::normalizar($nome) === 'SEM CATEGORIA') {
            return 'sem';
        }

        $categorias = Categoria::query()->orderBy('nome')->get();
        $encontrada = $categorias->first(fn (Categoria $categoria) => Texto::normalizar($categoria->nome) === Texto::normalizar($nome));

        if ($encontrada === null) {
            throw new EntradaInvalida("Não existe a categoria \"{$nome}\". As que existem: "
                .$categorias->pluck('nome')->implode(', ').', e "Sem categoria".');
        }

        return $encontrada;
    }
}
