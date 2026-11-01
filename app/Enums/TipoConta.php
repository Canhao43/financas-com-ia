<?php

namespace App\Enums;

enum TipoConta: string
{
    case Corrente = 'corrente';
    case Poupanca = 'poupanca';
    case CartaoDeCredito = 'cartao_de_credito';
    case Dinheiro = 'dinheiro';
    case Investimento = 'investimento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Corrente => 'Conta corrente',
            self::Poupanca => 'Poupança',
            self::CartaoDeCredito => 'Cartão de crédito',
            self::Dinheiro => 'Dinheiro',
            self::Investimento => 'Investimento',
        };
    }

    /**
     * @return list<array{valor: string, rotulo: string}>
     */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }
}
