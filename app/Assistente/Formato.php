<?php

namespace App\Assistente;

use App\Relatorios\Comparacao;
use Carbon\CarbonImmutable;

/**
 * Como os números saem para o Claude: já formatados, prontos para repetir. O modelo recebe
 * "R$ 1.234,56", e não 123456 centavos, para não ter conta nenhuma a fazer, nem a de dividir
 * por cem. A conferência da resposta procura exatamente estes textos.
 */
final class Formato
{
    /** -123456 → "-R$ 1.234,56". Só inteiros: nenhum centavo passa por float. */
    public static function reais(int $centavos): string
    {
        $positivo = abs($centavos);
        $texto = 'R$ '.number_format(intdiv($positivo, 100), 0, '', '.').','.str_pad((string) ($positivo % 100), 2, '0', STR_PAD_LEFT);

        return $centavos < 0 ? "-{$texto}" : $texto;
    }

    /** Pontos-base para "25,3%" (uma casa, como a tela); com sinal, "+12,3%" ou "-12,3%". */
    public static function porcentagem(int $pontosBase, bool $comSinal = false): string
    {
        $decimos = intdiv(abs($pontosBase) + 5, 10);
        $texto = intdiv($decimos, 10).($decimos % 10 === 0 ? '' : ','.($decimos % 10)).'%';

        if (! $comSinal || $decimos === 0) {
            return $texto;
        }

        return ($pontosBase > 0 ? '+' : '-').$texto;
    }

    /** A diferença pelo tamanho: "R$ 73,95 a mais", "R$ 10,00 a menos" ou "igual". */
    public static function diferenca(int $atual, int $anterior): string
    {
        $diferenca = abs($atual) - abs($anterior);

        return match (true) {
            $diferenca > 0 => self::reais($diferenca).' a mais',
            $diferenca < 0 => self::reais(-$diferenca).' a menos',
            default => 'igual',
        };
    }

    /**
     * Receita ou despesa e o mesmo valor no mês anterior, pelo tamanho (sem o sinal).
     *
     * @return array{total: string, mes_anterior: string, diferenca: string, variacao: string|null}
     */
    public static function comparacao(Comparacao $comparacao): array
    {
        $variacao = $comparacao->variacao();

        return [
            'total' => self::reais(abs($comparacao->atual)),
            'mes_anterior' => self::reais(abs($comparacao->anterior)),
            'diferenca' => self::diferenca($comparacao->atual, $comparacao->anterior),
            'variacao' => $variacao === null ? null : self::porcentagem($variacao, comSinal: true),
        ];
    }

    /** "outubro de 2026", sem depender do idioma configurado no Carbon. */
    public static function mes(CarbonImmutable $mes): string
    {
        $nomes = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        return $nomes[$mes->month - 1].' de '.$mes->year;
    }

    public static function data(CarbonImmutable $data): string
    {
        return $data->format('d/m/Y');
    }

    public static function periodo(CarbonImmutable $de, CarbonImmutable $ate): string
    {
        return self::data($de).' a '.self::data($ate);
    }
}
