<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function valoresValidos(): array
    {
        return [
            'milhar e centavos' => ['1.234,56', 123456],
            'sem ponto de milhar' => ['1234,56', 123456],
            'um dígito de centavo' => ['1234,5', 123450],
            'inteiro' => ['1234', 123400],
            'um centavo' => ['0,01', 1],
            'vários milhares' => ['1.234.567,89', 123456789],
            'ponto como decimal (teclado do celular)' => ['12.50', 1250],
            'ponto com três dígitos é milhar' => ['1.234', 123400],
            'espaços em volta' => [' 10,00 ', 1000],
        ];
    }

    #[DataProvider('valoresValidos')]
    public function test_converte_texto_em_centavos(string $texto, int $centavos): void
    {
        $this->assertSame($centavos, Dinheiro::paraCentavos($texto));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valoresInvalidos(): array
    {
        return [
            'vazio' => [''],
            'letras' => ['abc'],
            'três casas decimais' => ['12,345'],
            'duas vírgulas' => ['1,234,56'],
            'milhar mal formado' => ['1.23.45'],
            'símbolo de moeda' => ['R$ 10,00'],
            'negativo sem permissão' => ['-10,00'],
            'grande demais' => ['12345678901234'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_recusa_o_que_nao_e_valor(string $texto): void
    {
        $this->assertNull(Dinheiro::paraCentavos($texto));
    }

    public function test_negativo_quando_permitido(): void
    {
        $this->assertSame(-125000, Dinheiro::paraCentavos('-1.250,00', permitirNegativo: true));
        $this->assertSame(-1, Dinheiro::paraCentavos('- 0,01', permitirNegativo: true));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function centavosParaTexto(): array
    {
        return [
            'zero' => [0, '0,00'],
            'só centavos' => [5, '0,05'],
            'milhar' => [123456, '1.234,56'],
            'milhão' => [100000000, '1.000.000,00'],
            'negativo' => [-125000, '-1.250,00'],
        ];
    }

    #[DataProvider('centavosParaTexto')]
    public function test_formata_centavos_para_digitacao(int $centavos, string $texto): void
    {
        $this->assertSame($texto, Dinheiro::paraTexto($centavos));
    }

    public function test_ida_e_volta_nao_perde_centavo(): void
    {
        foreach ([1, 99, 100, 101, 123456, 999999999] as $centavos) {
            $this->assertSame($centavos, Dinheiro::paraCentavos(Dinheiro::paraTexto($centavos)));
        }
    }
}
