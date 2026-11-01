<?php

namespace App\Http\Requests;

use App\Support\Texto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarRegraRequest extends FormRequest
{
    /**
     * Normaliza antes de validar: "Uber" e "UBER" são a mesma regra, e o único tem que ver isso.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['texto' => Texto::normalizar((string) $this->input('texto'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'min:3', 'max:60', Rule::unique('regras', 'texto')->ignore($this->route('regra'))],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.unique' => 'Já existe uma regra com esse texto.',
            'texto.min' => 'Use pelo menos 3 letras: texto curto demais combina com quase tudo.',
            'categoria_id.required' => 'Escolha a categoria.',
        ];
    }
}
