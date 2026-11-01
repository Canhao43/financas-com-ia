<?php

namespace App\Enums;

enum TipoCategoria: string
{
    case Despesa = 'despesa';
    case Receita = 'receita';
    // Dinheiro que só muda de conta, como o pagamento da fatura do cartão ou a ida para a
    // poupança. Não é gasto nem ganho: contar como despesa somaria a fatura duas vezes, porque
    // as compras do cartão já são despesas.
    case Transferencia = 'transferencia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Despesa => 'Despesa',
            self::Receita => 'Receita',
            self::Transferencia => 'Transferência',
        };
    }

    /**
     * As direções de lançamento em que a categoria pode ser usada. A transferência tem duas
     * pontas: sai de uma conta e entra na outra.
     *
     * @return list<string>
     */
    public function direcoes(): array
    {
        return match ($this) {
            self::Despesa => ['saida'],
            self::Receita => ['entrada'],
            self::Transferencia => ['saida', 'entrada'],
        };
    }

    /**
     * Os tipos de categoria que servem a um lançamento nesta direção.
     *
     * @return list<self>
     */
    public static function daDirecao(string $direcao): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tipo) => in_array($direcao, $tipo->direcoes(), true),
        ));
    }
}
