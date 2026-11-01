<?php

namespace App\Http\Requests;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarCategoriaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60', Rule::unique('categorias')
                ->where('tipo', $this->input('tipo'))
                ->ignore($this->route('categoria'))],
            'tipo' => ['required', Rule::enum(TipoCategoria::class), function (string $atributo, mixed $valor, Closure $falhar) {
                $categoria = $this->route('categoria');

                // Trocar o tipo deixaria os lançamentos antigos com o sinal errado para a categoria.
                if ($categoria instanceof Categoria
                    && $categoria->tipo->value !== $valor
                    && $categoria->lancamentos()->exists()) {
                    $falhar('Categoria com lançamentos não pode trocar de tipo.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.unique' => 'Já existe uma categoria com esse nome.',
        ];
    }
}
