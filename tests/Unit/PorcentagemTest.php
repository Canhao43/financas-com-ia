<?php

namespace Tests\Unit;

use App\Support\Porcentagem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PorcentagemTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int}>
     */
    public static function casos(): array
    {
        return [
            'inteira' => [25, 100, 2500],
            'um terço arredonda para baixo' => [1, 3, 3333],
            'dois terços arredonda para cima' => [2, 3, 6667],
            'o meio sobe' => [1, 8, 1250],
            'o meio de 1/20000 sobe' => [1, 20000, 1],
            'menos que o meio desce' => [1, 20001, 0],
            'negativo: o meio vai para longe do zero' => [-1, 20000, -1],
            'queda de 20%' => [-20, 100, -2000],
            'mais que o todo' => [150, 100, 15000],
            'todo zero não divide' => [5, 0, 0],
            'parte zero' => [0, 7, 0],
            // Centavos de verdade: R$ 1.234,56 de R$ 9.876,54 = 12,4999...% → 12,50%.
            'centavos' => [123456, 987654, 1250],
        ];
    }

    #[DataProvider('casos')]
    public function test_calcula_em_pontos_base_arredondando_para_o_mais_proximo(int $parte, int $todo, int $esperado): void
    {
        $this->assertSame($esperado, Porcentagem::emPontosBase($parte, $todo));
    }
}
