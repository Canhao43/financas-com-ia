<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PerguntarRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Pergunta curta: cada caractere vai para a API, e pergunta longa raramente é uma só.
            'pergunta' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pergunta.required' => 'Escreva a pergunta.',
            'pergunta.max' => 'Pergunta longa demais: até 500 caracteres.',
        ];
    }

    public function pergunta(): string
    {
        return $this->string('pergunta')->squish()->toString();
    }
}
