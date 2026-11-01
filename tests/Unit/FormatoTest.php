<?php

namespace Tests\Unit;

use App\Assistente\Formato;
use App\Relatorios\Comparacao;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatoTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function reais(): array
    {
        return [
            'zero' => [0, 'R$ 0,00'],
            'centavos' => [5, 'R$ 0,05'],
            'um real' => [100, 'R$ 1,00'],
            'milhar' => [123456, 'R$ 1.234,56'],
            'milhões' => [123456789, 'R$ 1.234.567,89'],
            'negativo' => [-71890, '-R$ 718,90'],
        ];
    }

    #[DataProvider('reais')]
    public function test_formata_reais_so_com_inteiros(int $centavos, string $esperado): void
    {
        $this->assertSame($esperado, Formato::reais($centavos));
    }

    /**
     * @return array<string, array{int, bool, string}>
     */
    public static function porcentagens(): array
    {
        return [
            'inteira' => [2500, false, '25%'],
            'uma casa' => [2534, false, '25,3%'],
            'arredonda o décimo para cima' => [2535, false, '25,4%'],
            'cem por cento' => [10000, false, '100%'],
            'com sinal, subindo' => [1530, true, '+15,3%'],
            'com sinal, caindo' => [-1510, true, '-15,1%'],
            'quase zero não ganha sinal' => [-4, true, '0%'],
        ];
    }

    #[DataProvider('porcentagens')]
    public function test_formata_porcentagem_como_a_tela(int $pontosBase, bool $comSinal, string $esperado): void
    {
        $this->assertSame($esperado, Formato::porcentagem($pontosBase, $comSinal));
    }

    public function test_diferenca_pelo_tamanho_do_valor(): void
    {
        $this->assertSame('R$ 73,95 a mais', Formato::diferenca(-284800, -277405));
        $this->assertSame('R$ 21,50 a menos', Formato::diferenca(-12100, -14250));
        $this->assertSame('igual', Formato::diferenca(650000, 650000));
    }

    public function test_comparacao_sai_sem_o_sinal_de_menos(): void
    {
        $this->assertSame([
            'total' => 'R$ 718,90',
            'mes_anterior' => 'R$ 623,45',
            'diferenca' => 'R$ 95,45 a mais',
            'variacao' => '+15,3%',
        ], Formato::comparacao(new Comparacao(-71890, -62345)));

        $this->assertNull(Formato::comparacao(new Comparacao(-1000, 0))['variacao']);
    }

    public function test_mes_e_datas_em_portugues(): void
    {
        $this->assertSame('março de 2027', Formato::mes(CarbonImmutable::parse('2027-03-01')));
        $this->assertSame('01/10/2026 a 31/10/2026', Formato::periodo(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')));
    }
}
