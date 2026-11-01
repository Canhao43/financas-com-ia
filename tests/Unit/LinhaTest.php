<?php

namespace Tests\Unit;

use App\Importacao\Linha;
use App\Ofx\Extrato;
use App\Ofx\TransacaoOfx;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LinhaTest extends TestCase
{
    /**
     * @param  list<array{0: string, 1: int, 2: string, 3?: string|null}>  $transacoes
     * @return list<Linha>
     */
    private function linhas(array $transacoes): array
    {
        return Linha::doExtrato(new Extrato(array_map(
            fn (array $transacao) => new TransacaoOfx(CarbonImmutable::parse($transacao[0]), $transacao[1], $transacao[2], $transacao[3] ?? null),
            $transacoes,
        )));
    }

    public function test_duas_compras_iguais_no_mesmo_dia_tem_impressoes_diferentes(): void
    {
        [$primeiro, $segundo] = $this->linhas([
            ['2026-10-10', -500, 'CAFE'],
            ['2026-10-10', -500, 'CAFE'],
        ]);

        $this->assertNotSame($primeiro->impressao, $segundo->impressao);
    }

    public function test_a_mesma_transacao_tem_a_mesma_impressao_em_outro_arquivo(): void
    {
        // O segundo arquivo começa antes e traz outra transação no meio: a ordem só conta entre iguais.
        $deOutubro = $this->linhas([
            ['2026-10-10', -500, 'CAFE'],
            ['2026-10-10', -500, 'CAFE'],
        ]);
        $deOutroPeriodo = $this->linhas([
            ['2026-10-09', -2000, 'MERCADO'],
            ['2026-10-10', -500, 'CAFE'],
            ['2026-10-10', -900, 'PAO'],
            ['2026-10-10', -500, 'CAFE'],
        ]);

        $this->assertSame($deOutubro[0]->impressao, $deOutroPeriodo[1]->impressao);
        $this->assertSame($deOutubro[1]->impressao, $deOutroPeriodo[3]->impressao);
    }

    public function test_diferenca_de_acento_caixa_e_espaco_nao_muda_a_impressao(): void
    {
        [$um] = $this->linhas([['2026-10-10', -500, 'Padaria  São João']]);
        [$outro] = $this->linhas([['2026-10-10', -500, 'PADARIA SAO JOAO ']]);

        $this->assertSame($um->impressao, $outro->impressao);
    }

    public function test_valor_ou_data_diferente_muda_a_impressao(): void
    {
        [$base, $outroValor, $outraData] = $this->linhas([
            ['2026-10-10', -500, 'CAFE'],
            ['2026-10-10', -501, 'CAFE'],
            ['2026-10-11', -500, 'CAFE'],
        ]);

        $this->assertNotSame($base->impressao, $outroValor->impressao);
        $this->assertNotSame($base->impressao, $outraData->impressao);
    }

    public function test_fitid_repetido_no_arquivo_e_descartado(): void
    {
        $linhas = $this->linhas([
            ['2026-10-10', -500, 'TARIFA', '000123'],
            ['2026-10-11', -900, 'TARIFA', '000123'],
            ['2026-10-12', -100, 'CAFE', 'UNICO'],
            ['2026-10-12', -100, 'CAFE'],
        ]);

        $this->assertSame([null, null, 'UNICO', null], array_map(fn (Linha $linha) => $linha->fitid, $linhas));
    }

    public function test_sobrevive_a_ida_e_volta_pelo_json(): void
    {
        [$linha] = $this->linhas([['2026-10-10', -500, 'CAFÉ', 'X1']]);

        $this->assertEquals($linha, Linha::deArray(json_decode((string) json_encode($linha->paraArray()), true)));
    }
}
