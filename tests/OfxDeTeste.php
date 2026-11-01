<?php

namespace Tests;

use Illuminate\Http\UploadedFile;

/**
 * Monta extratos OFX fictícios para os testes, no formato SGML que os bancos brasileiros exportam.
 */
final class OfxDeTeste
{
    /**
     * @param  list<array{0: string, 1: string, 2: string, 3?: string|null}>  $transacoes  [data AAAA-MM-DD, valor como no OFX, descrição, FITID]
     */
    public static function conteudo(array $transacoes, string $conta = '00012345-6', ?string $saldo = null, string $saldoEm = '2026-10-31'): string
    {
        $blocos = '';

        foreach ($transacoes as $transacao) {
            [$data, $valor, $descricao] = $transacao;
            $fitid = $transacao[3] ?? null;
            $blocos .= "<STMTTRN>\r\n<TRNTYPE>".(str_starts_with($valor, '-') ? 'DEBIT' : 'CREDIT')."\r\n"
                .'<DTPOSTED>'.str_replace('-', '', $data)."000000[-3:BRT]\r\n"
                ."<TRNAMT>{$valor}\r\n"
                .($fitid === null ? '' : "<FITID>{$fitid}\r\n")
                ."<MEMO>{$descricao}\r\n</STMTTRN>\r\n";
        }

        $saldoOfx = $saldo === null ? '' : "<LEDGERBAL>\r\n<BALAMT>{$saldo}\r\n<DTASOF>".str_replace('-', '', $saldoEm)."\r\n</LEDGERBAL>\r\n";

        return "OFXHEADER:100\r\nDATA:OFXSGML\r\nVERSION:102\r\n\r\n<OFX>\r\n<BANKMSGSRSV1>\r\n<STMTTRNRS>\r\n<STMTRS>\r\n"
            ."<BANKACCTFROM>\r\n<ACCTID>{$conta}\r\n</BANKACCTFROM>\r\n"
            ."<BANKTRANLIST>\r\n<DTSTART>20261001\r\n<DTEND>20261031\r\n{$blocos}</BANKTRANLIST>\r\n"
            ."{$saldoOfx}</STMTRS>\r\n</STMTTRNRS>\r\n</BANKMSGSRSV1>\r\n</OFX>\r\n";
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3?: string|null}>  $transacoes
     */
    public static function arquivo(array $transacoes, string $conta = '00012345-6', ?string $saldo = null, string $saldoEm = '2026-10-31'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('extrato.ofx', self::conteudo($transacoes, $conta, $saldo, $saldoEm));
    }
}
