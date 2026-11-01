<?php

namespace App\Assistente;

/**
 * A prova de que a IA não fez conta: todo valor em reais e toda porcentagem da resposta
 * precisam ter saído de uma consulta. O que não saiu de nenhuma é mostrado na tela como "sem
 * fonte", em vez de passar como se fosse do banco.
 *
 * Compara pela forma normalizada: "R$ 1.234,56", "-R$ 1.234,56" e "R$ 1234,56" são o mesmo
 * valor; o sinal fica de fora porque a resposta diz "gastou R$ 718,90" para uma saída de
 * -R$ 718,90.
 */
final class ConferenciaDeValores
{
    private const string REAIS = '/R\$\s?[\d.]+,\d{2}/u';

    private const string PORCENTAGEM = '/\d+(?:,\d+)?\s?%/u';

    /**
     * Os valores do texto, cada um com a forma em que aparece e a normalizada.
     *
     * @return array<string, string> normalizado => como aparece
     */
    public static function valores(string $texto): array
    {
        $valores = [];

        preg_match_all(self::REAIS, $texto, $reais);
        foreach ($reais[0] as $valor) {
            $valores['R$'.str_replace(['R$', '.', ' ', "\u{a0}"], '', $valor)] ??= $valor;
        }

        preg_match_all(self::PORCENTAGEM, $texto, $porcentagens);
        foreach ($porcentagens[0] as $valor) {
            $valores[str_replace([' ', "\u{a0}"], '', $valor)] ??= $valor;
        }

        return $valores;
    }

    /**
     * @param  list<string>  $permitidos  formas normalizadas, das consultas e da própria pergunta
     * @return list<string> os valores da resposta que não vieram de lugar nenhum, como aparecem
     */
    public static function semFonte(string $resposta, array $permitidos): array
    {
        return array_values(array_diff_key(self::valores($resposta), array_flip($permitidos)));
    }
}
