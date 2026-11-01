<?php

namespace App\Ofx;

use Carbon\CarbonImmutable;

/**
 * Uma transação como o banco mandou: valor em centavos com o sinal do OFX (saída negativa).
 */
final readonly class TransacaoOfx
{
    public function __construct(
        public CarbonImmutable $data,
        public int $valor,
        public string $descricao,
        public ?string $fitid,
    ) {}
}
