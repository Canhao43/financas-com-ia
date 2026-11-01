<?php

namespace App\Ofx;

use Carbon\CarbonImmutable;

/**
 * O que interessa de um arquivo OFX. Da conta, só os 4 últimos dígitos: bastam para
 * avisar de arquivo trocado, e o número completo não precisa ficar guardado.
 */
final readonly class Extrato
{
    /**
     * @param  list<TransacaoOfx>  $transacoes
     */
    public function __construct(
        public array $transacoes,
        public ?string $finalDaConta = null,
        public ?CarbonImmutable $inicio = null,
        public ?CarbonImmutable $fim = null,
        public ?int $saldo = null,
        public ?CarbonImmutable $saldoEm = null,
    ) {}
}
