<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarImportacaoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decisoes' => ['sometimes', 'array'],
            'decisoes.*' => ['string', 'in:importar,ignorar,vincular'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function decisoes(): array
    {
        /** @var array<int|string, string> $decisoes */
        $decisoes = $this->validated('decisoes', []);
        $porLinha = [];

        foreach ($decisoes as $indice => $decisao) {
            $porLinha[(int) $indice] = $decisao;
        }

        return $porLinha;
    }
}
