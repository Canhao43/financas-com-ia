<?php

namespace Tests\Unit;

use App\Assistente\ConferenciaDeValores;
use PHPUnit\Framework\TestCase;

class ConferenciaDeValoresTest extends TestCase
{
    public function test_acha_reais_e_porcentagens_e_ignora_sinal_e_separador(): void
    {
        $valores = ConferenciaDeValores::valores('Gastou R$ 1.234,56 (era -R$ 1.000,00, +23,5%), e R$1234,56 de novo; 100 % do mês.');

        $this->assertSame(['R$1234,56', 'R$1000,00', '23,5%', '100%'], array_keys($valores));
        $this->assertSame('R$ 1.234,56', $valores['R$1234,56']);
    }

    public function test_aponta_so_o_que_nao_veio_das_consultas(): void
    {
        $consultas = array_keys(ConferenciaDeValores::valores('{"total":"R$ 718,90","variacao":"+15,3%","mes_anterior":"R$ 623,45"}'));
        $resposta = 'Em outubro você gastou R$ 718,90 com mercado, 15,3% a mais que os R$ 623,45 de setembro. Somando os dois meses, R$ 1.342,35.';

        $this->assertSame(['R$ 1.342,35'], ConferenciaDeValores::semFonte($resposta, $consultas));
    }

    public function test_resposta_sem_numero_nao_tem_o_que_conferir(): void
    {
        $this->assertSame([], ConferenciaDeValores::semFonte('Não há lançamentos nesse período.', []));
    }
}
