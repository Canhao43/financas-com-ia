<?php

namespace App\Http\Requests;

use App\Enums\TipoConta;
use App\Support\Dinheiro;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarContaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'tipo' => ['required', Rule::enum(TipoConta::class)],
            // Pode ser negativo: cartão com fatura em aberto, conta no cheque especial.
            'saldo_inicial' => ['nullable', 'string', function (string $atributo, mixed $valor, Closure $falhar) {
                if (Dinheiro::paraCentavos((string) $valor, permitirNegativo: true) === null) {
                    $falhar('Informe o saldo no formato 1.234,56 (use - na frente se for negativo).');
                }
            }],
            'arquivada' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{nome: string, tipo: string, saldo_inicial: int, arquivada: bool}
     */
    public function dadosDaConta(): array
    {
        return [
            'nome' => $this->string('nome')->trim()->toString(),
            'tipo' => $this->string('tipo')->toString(),
            'saldo_inicial' => $this->filled('saldo_inicial')
                ? (int) Dinheiro::paraCentavos($this->string('saldo_inicial')->toString(), permitirNegativo: true)
                : 0,
            'arquivada' => $this->boolean('arquivada'),
        ];
    }
}
