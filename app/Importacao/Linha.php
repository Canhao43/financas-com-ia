<?php

namespace App\Importacao;

use App\Ofx\Extrato;
use App\Ofx\TransacaoOfx;
use App\Support\Texto;
use Carbon\CarbonImmutable;

/**
 * Uma linha do extrato pronta para comparar com o banco: com a impressão digital calculada e o
 * FITID só quando ele é confiável.
 */
final readonly class Linha
{
    public function __construct(
        public CarbonImmutable $data,
        public int $valor,
        public string $descricao,
        public ?string $fitid,
        public string $impressao,
    ) {}

    /**
     * A impressão é data + valor + descrição + a ordem dessa combinação no arquivo. Sem a ordem,
     * dois cafés iguais no mesmo dia teriam a mesma impressão e o segundo sumiria como duplicado.
     * A mesma transação ganha a mesma impressão em qualquer exportação que inclua o dia dela.
     *
     * @return list<self>
     */
    public static function doExtrato(Extrato $extrato): array
    {
        $fitids = array_map(fn (TransacaoOfx $transacao) => $transacao->fitid, $extrato->transacoes);
        $repeticoes = array_count_values(array_filter($fitids, fn (?string $fitid) => $fitid !== null));
        $vistas = [];
        $linhas = [];

        foreach ($extrato->transacoes as $transacao) {
            $chave = implode('|', [$transacao->data->toDateString(), $transacao->valor, Texto::normalizar($transacao->descricao)]);
            $vistas[$chave] = ($vistas[$chave] ?? 0) + 1;

            $linhas[] = new self(
                data: $transacao->data,
                valor: $transacao->valor,
                descricao: $transacao->descricao,
                // FITID repetido no mesmo arquivo não identifica nada (há banco que usa o número do
                // documento): nesse caso a linha fica só com a impressão.
                fitid: $transacao->fitid !== null && $repeticoes[$transacao->fitid] === 1 ? $transacao->fitid : null,
                impressao: hash('sha256', $chave.'|'.$vistas[$chave]),
            );
        }

        return $linhas;
    }

    /**
     * @return array{data: string, valor: int, descricao: string, fitid: string|null, impressao: string}
     */
    public function paraArray(): array
    {
        return [
            'data' => $this->data->toDateString(),
            'valor' => $this->valor,
            'descricao' => $this->descricao,
            'fitid' => $this->fitid,
            'impressao' => $this->impressao,
        ];
    }

    /**
     * @param  array{data: string, valor: int, descricao: string, fitid: string|null, impressao: string}  $dados
     */
    public static function deArray(array $dados): self
    {
        return new self(
            data: CarbonImmutable::createFromFormat('!Y-m-d', $dados['data']) ?: throw new \UnexpectedValueException('Data inválida na linha guardada.'),
            valor: $dados['valor'],
            descricao: $dados['descricao'],
            fitid: $dados['fitid'],
            impressao: $dados['impressao'],
        );
    }
}
