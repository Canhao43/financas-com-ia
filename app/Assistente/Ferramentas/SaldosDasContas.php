<?php

namespace App\Assistente\Ferramentas;

use App\Assistente\Ferramenta;
use App\Assistente\Formato;
use App\Models\Conta;

/**
 * O mesmo saldo do painel: inicial mais os lançamentos, contas ativas.
 */
final class SaldosDasContas implements Ferramenta
{
    public function nome(): string
    {
        return 'saldos_das_contas';
    }

    public function descrever(array $entrada): string
    {
        return 'Saldos das contas';
    }

    public function definicao(): array
    {
        return [
            'name' => $this->nome(),
            'description' => 'O saldo atual de cada conta ativa e o saldo total, já somado. Cartão de crédito com saldo '
                .'negativo é o que está devendo.',
            'inputSchema' => ['type' => 'object', 'properties' => new \stdClass, 'additionalProperties' => false],
        ];
    }

    public function executar(array $entrada): array
    {
        $contas = Conta::query()->where('arquivada', false)->comSaldo()->orderBy('nome')->get();

        return [
            'contas' => $contas->map(fn (Conta $conta) => [
                'conta' => $conta->nome,
                'tipo' => $conta->tipo->rotulo(),
                'saldo' => Formato::reais($conta->saldoAtual()),
            ])->all(),
            'saldo_total' => Formato::reais((int) $contas->sum(fn (Conta $conta) => $conta->saldoAtual())),
        ];
    }
}
