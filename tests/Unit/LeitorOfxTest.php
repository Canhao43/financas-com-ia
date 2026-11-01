<?php

namespace Tests\Unit;

use App\Ofx\Extrato;
use App\Ofx\LeitorOfx;
use App\Ofx\OfxInvalido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeitorOfxTest extends TestCase
{
    private function ler(string $conteudo): Extrato
    {
        return (new LeitorOfx)->ler($conteudo);
    }

    /**
     * Um extrato SGML mínimo, numa linha só, com uma transação por item.
     *
     * @param  list<string>  $transacoes  o miolo de cada <STMTTRN>
     */
    private function sgml(array $transacoes): string
    {
        $blocos = implode('', array_map(fn (string $miolo) => "<STMTTRN>{$miolo}</STMTTRN>", $transacoes));

        return "<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKACCTFROM><ACCTID>1</BANKACCTFROM><BANKTRANLIST>{$blocos}</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
    }

    public function test_le_extrato_sgml_em_windows_1252(): void
    {
        $extrato = $this->ler((string) file_get_contents(__DIR__.'/../fixtures/ofx/conta-sgml-windows-1252.ofx'));

        $this->assertCount(3, $extrato->transacoes);

        [$salario, $padaria, $aluguel] = $extrato->transacoes;

        $this->assertSame('SALÁRIO EMPRESA FICTÍCIA', $salario->descricao);
        $this->assertSame(650000, $salario->valor);
        $this->assertSame('2026-10-05', $salario->data->toDateString());
        $this->assertSame('20261005001', $salario->fitid);

        $this->assertSame('PADARIA SÃO JOÃO', $padaria->descricao);

        // MEMO vazio: a descrição vem do NAME.
        $this->assertSame('ALUGUEL', $aluguel->descricao);
        $this->assertSame(-123456, $aluguel->valor);

        // "00012345-6": o dígito verificador conta.
        $this->assertSame('3456', $extrato->finalDaConta);
        $this->assertSame('2026-10-01', $extrato->inicio?->toDateString());
        $this->assertSame('2026-10-31', $extrato->fim?->toDateString());
        $this->assertSame(511544, $extrato->saldo);
        $this->assertSame('2026-10-31', $extrato->saldoEm?->toDateString());
    }

    public function test_le_extrato_de_cartao_em_xml(): void
    {
        $extrato = $this->ler((string) file_get_contents(__DIR__.'/../fixtures/ofx/cartao-xml.ofx'));

        $this->assertCount(2, $extrato->transacoes);
        $this->assertSame('Mercado & Cia', $extrato->transacoes[0]->descricao);
        $this->assertSame(-8990, $extrato->transacoes[0]->valor);
        $this->assertSame(50000, $extrato->transacoes[1]->valor);
        $this->assertSame('9876', $extrato->finalDaConta);
        $this->assertSame(-120010, $extrato->saldo);
    }

    public function test_le_arquivo_inteiro_numa_linha_so(): void
    {
        $extrato = $this->ler($this->sgml([
            '<TRNTYPE>DEBIT<DTPOSTED>20261010<TRNAMT>-10.00<FITID>A<MEMO>CAFE',
            '<TRNTYPE>DEBIT<DTPOSTED>20261011<TRNAMT>-20.00<FITID>B<MEMO>PAO',
        ]));

        $this->assertSame(['CAFE', 'PAO'], array_map(fn ($transacao) => $transacao->descricao, $extrato->transacoes));
        $this->assertSame([-1000, -2000], array_map(fn ($transacao) => $transacao->valor, $extrato->transacoes));
    }

    public function test_aceita_utf8_com_bom(): void
    {
        $extrato = $this->ler("\xEF\xBB\xBF".$this->sgml(['<DTPOSTED>20261010<TRNAMT>-1.00<MEMO>CAFÉ']));

        $this->assertSame('CAFÉ', $extrato->transacoes[0]->descricao);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function valores(): array
    {
        return [
            'sem casas decimais' => ['-150', -15000],
            'uma casa' => ['-1234.5', -123450],
            'vírgula como decimal' => ['-50,25', -5025],
            'sinal de mais' => ['+10.00', 1000],
            'zeros a mais' => ['-10.500', -1050],
            'sem parte inteira' => ['-.50', -50],
            // Em float, 0.1 + 0.2 ≠ 0.3; aqui é texto até virar inteiro.
            'centavos que o float erra' => ['0.29', 29],
        ];
    }

    #[DataProvider('valores')]
    public function test_le_o_valor_em_centavos_sem_float(string $texto, int $centavos): void
    {
        $extrato = $this->ler($this->sgml(["<DTPOSTED>20261010<TRNAMT>{$texto}<MEMO>X"]));

        $this->assertSame($centavos, $extrato->transacoes[0]->valor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valoresRecusados(): array
    {
        return [
            'fração de centavo' => ['-10.999'],
            'separador de milhar' => ['1.234,56'],
            'texto' => ['dez'],
            'vazio' => [''],
        ];
    }

    #[DataProvider('valoresRecusados')]
    public function test_recusa_valor_que_nao_da_para_ler_com_certeza(string $texto): void
    {
        $this->expectException(OfxInvalido::class);
        $this->expectExceptionMessage('O lançamento 1 do arquivo tem um valor que não dá para ler');

        $this->ler($this->sgml(["<DTPOSTED>20261010<TRNAMT>{$texto}<MEMO>X"]));
    }

    public function test_recusa_data_invalida(): void
    {
        $this->expectExceptionObject(new OfxInvalido('O lançamento 2 do arquivo está sem data válida.'));

        $this->ler($this->sgml([
            '<DTPOSTED>20261010<TRNAMT>-1.00<MEMO>OK',
            '<DTPOSTED>20261332<TRNAMT>-1.00<MEMO>MÊS 13',
        ]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function arquivosRecusados(): array
    {
        return [
            'não é OFX' => ['data;valor;descricao', 'O arquivo não parece um extrato OFX.'],
            'sem extrato' => ['<OFX><SIGNONMSGSRSV1></SIGNONMSGSRSV1></OFX>', 'O arquivo não tem extrato de conta nem de cartão.'],
            'sem lançamentos' => ['<OFX><STMTRS><BANKTRANLIST></BANKTRANLIST></STMTRS></OFX>', 'O arquivo não tem nenhum lançamento.'],
            'duas contas' => ['<OFX><STMTRS></STMTRS><CCSTMTRS></CCSTMTRS></OFX>', 'O arquivo tem mais de uma conta. Exporte uma de cada vez.'],
        ];
    }

    #[DataProvider('arquivosRecusados')]
    public function test_recusa_arquivo_que_nao_e_um_extrato(string $conteudo, string $mensagem): void
    {
        $this->expectExceptionObject(new OfxInvalido($mensagem));

        $this->ler($conteudo);
    }
}
