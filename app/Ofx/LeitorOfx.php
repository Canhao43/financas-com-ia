<?php

namespace App\Ofx;

use Carbon\CarbonImmutable;

/**
 * Lê extrato OFX de conta ou de cartão, nas duas versões do formato: a 1.x (SGML, em que
 * as tags de valor não fecham) e a 2.x (XML). Em vez de converter SGML em XML, extrai cada
 * valor até o próximo "<" ou quebra de linha — funciona nas duas e em arquivo de uma linha só.
 *
 * Feito aqui, e não com biblioteca, porque a mais usada em PHP (asgrim/ofxparser, sem versão
 * nova desde 2018) devolve float e lê "-150" como -1,50.
 */
final class LeitorOfx
{
    public function ler(string $bruto): Extrato
    {
        $texto = $this->emUtf8($bruto);
        $inicio = stripos($texto, '<OFX>');

        if ($inicio === false) {
            throw new OfxInvalido('O arquivo não parece um extrato OFX.');
        }

        $ofx = substr($texto, $inicio);
        $extratos = preg_match_all('/<(STMTRS|CCSTMTRS)>/i', $ofx);

        if ($extratos === 0) {
            throw new OfxInvalido('O arquivo não tem extrato de conta nem de cartão.');
        }

        // Com duas contas no mesmo arquivo, não dá para saber a qual delas cada linha pertence aqui.
        if ($extratos > 1) {
            throw new OfxInvalido('O arquivo tem mais de uma conta. Exporte uma de cada vez.');
        }

        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $ofx, $blocos);

        if ($blocos[1] === []) {
            throw new OfxInvalido('O arquivo não tem nenhum lançamento.');
        }

        $transacoes = [];

        foreach ($blocos[1] as $posicao => $bloco) {
            $transacoes[] = $this->transacao($bloco, $posicao + 1);
        }

        $saldo = $this->bloco($ofx, 'LEDGERBAL');

        return new Extrato(
            transacoes: $transacoes,
            finalDaConta: $this->finalDaConta($this->valor($ofx, 'ACCTID')),
            inicio: $this->data($this->valor($ofx, 'DTSTART')),
            fim: $this->data($this->valor($ofx, 'DTEND')),
            // Saldo ilegível não impede a importação: ele só serve para conferência.
            saldo: $saldo === null ? null : $this->centavos($this->valor($saldo, 'BALAMT') ?? ''),
            saldoEm: $saldo === null ? null : $this->data($this->valor($saldo, 'DTASOF')),
        );
    }

    private function transacao(string $bloco, int $posicao): TransacaoOfx
    {
        $data = $this->data($this->valor($bloco, 'DTPOSTED'));

        if ($data === null) {
            throw new OfxInvalido("O lançamento {$posicao} do arquivo está sem data válida.");
        }

        $textoDoValor = $this->valor($bloco, 'TRNAMT') ?? '';
        $valor = $this->centavos($textoDoValor);

        if ($valor === null) {
            throw new OfxInvalido("O lançamento {$posicao} do arquivo tem um valor que não dá para ler: \"{$textoDoValor}\".");
        }

        $descricao = $this->valor($bloco, 'MEMO') ?? $this->valor($bloco, 'NAME') ?? $this->valor($bloco, 'TRNTYPE') ?? 'Sem descrição';

        return new TransacaoOfx(
            data: $data,
            valor: $valor,
            descricao: mb_substr($descricao, 0, 255),
            fitid: $this->valor($bloco, 'FITID'),
        );
    }

    /**
     * O cabeçalho do OFX diz a codificação, mas há banco que declara uma e manda outra. Texto
     * em Windows-1252 com acento quase nunca é UTF-8 válido, então a checagem é pelo conteúdo.
     */
    private function emUtf8(string $bruto): string
    {
        if (str_starts_with($bruto, "\xEF\xBB\xBF")) {
            $bruto = substr($bruto, 3);
        }

        return mb_check_encoding($bruto, 'UTF-8') ? $bruto : mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
    }

    /**
     * O valor de uma tag folha: tudo até o próximo "<" ou quebra de linha. Vazio vira null.
     */
    private function valor(string $texto, string $tag): ?string
    {
        if (! preg_match('/<'.$tag.'>([^<\r\n]*)/i', $texto, $achado)) {
            return null;
        }

        $valor = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($achado[1], ENT_QUOTES | ENT_XML1, 'UTF-8')));

        return $valor === '' ? null : $valor;
    }

    private function bloco(string $texto, string $tag): ?string
    {
        return preg_match('/<'.$tag.'>(.*?)<\/'.$tag.'>/is', $texto, $achado) ? $achado[1] : null;
    }

    /**
     * Datas OFX começam por AAAAMMDD; hora e fuso que vêm depois não interessam aqui.
     */
    private function data(?string $texto): ?CarbonImmutable
    {
        if ($texto === null || ! preg_match('/^(\d{4})(\d{2})(\d{2})/', $texto, $partes)) {
            return null;
        }

        [, $ano, $mes, $dia] = array_map('intval', $partes);

        return checkdate($mes, $dia, $ano) ? CarbonImmutable::create($ano, $mes, $dia) : null;
    }

    /**
     * Valor do OFX em centavos, sem passar por float. No OFX não há separador de milhar: o
     * ponto é decimal — e há banco brasileiro que usa vírgula. Fração de centavo diferente de
     * zero é recusada em vez de arredondada.
     */
    private function centavos(string $texto): ?int
    {
        if (! preg_match('/^([+-]?)(\d{0,13})(?:[.,](\d+))?$/', trim($texto), $partes)) {
            return null;
        }

        $inteiro = $partes[2];
        $decimais = $partes[3] ?? '';

        if (($inteiro === '' && $decimais === '') || strlen(rtrim($decimais, '0')) > 2) {
            return null;
        }

        $centavos = (int) $inteiro * 100 + (int) str_pad(substr($decimais, 0, 2), 2, '0');

        return $partes[1] === '-' ? -$centavos : $centavos;
    }

    private function finalDaConta(?string $conta): ?string
    {
        $digitos = preg_replace('/[^0-9A-Za-z]/', '', (string) $conta);

        return $digitos === '' ? null : substr((string) $digitos, -4);
    }
}
