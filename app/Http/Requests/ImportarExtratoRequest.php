<?php

namespace App\Http\Requests;

use App\Models\Conta;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ImportarExtratoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conta_id' => ['required', 'integer', Rule::exists('contas', 'id')->where(fn (Builder $query) => $query->where('arquivada', false))],
            // A extensão só filtra o engano óbvio; quem decide se o conteúdo é um extrato é o leitor de OFX.
            'arquivo' => ['required', 'file', 'max:2048', 'extensions:ofx,qfx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conta_id.exists' => 'Escolha uma conta ativa.',
            'arquivo.required' => 'Escolha o arquivo do extrato.',
            'arquivo.extensions' => 'Envie o extrato em OFX (arquivo .ofx).',
            'arquivo.max' => 'O arquivo passa de 2 MB. Exporte um período menor.',
        ];
    }

    public function conta(): Conta
    {
        return Conta::query()->findOrFail($this->integer('conta_id'));
    }

    public function arquivo(): UploadedFile
    {
        /** @var UploadedFile $arquivo */
        $arquivo = $this->file('arquivo');

        return $arquivo;
    }
}
