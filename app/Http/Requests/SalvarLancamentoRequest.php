<?php

namespace App\Http\Requests;

use App\Enums\TipoCategoria;
use App\Models\Lancamento;
use App\Support\Dinheiro;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarLancamentoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direcao' => ['required', Rule::in(['entrada', 'saida'])],
            'valor' => ['required', 'string', function (string $atributo, mixed $valor, Closure $falhar) {
                $centavos = Dinheiro::paraCentavos((string) $valor);

                if ($centavos === null) {
                    $falhar('Informe o valor no formato 1.234,56.');
                } elseif ($centavos < 1) {
                    $falhar('O valor deve ser maior que zero.');
                }
            }],
            'data' => ['required', 'date_format:Y-m-d'],
            'descricao' => ['required', 'string', 'max:255'],
            // Conta arquivada não recebe lançamento novo, mas o que já é dela continua editável.
            'conta_id' => ['required', 'integer', Rule::exists('contas', 'id')->where(fn (Builder $query) => $query
                ->where('arquivada', false)
                ->when($this->lancamentoEditado(), fn (Builder $query, Lancamento $editado) => $query
                    ->orWhere('id', $editado->conta_id)))],
            // Saída só com categoria de despesa; entrada, de receita.
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')
                ->where('tipo', TipoCategoria::daDirecao((string) $this->input('direcao'))->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conta_id.exists' => 'Escolha uma conta ativa.',
            'categoria_id.exists' => 'Use categoria de despesa para saídas e de receita para entradas.',
            'data.date_format' => 'Informe uma data válida.',
        ];
    }

    /**
     * O lançamento da rota, na edição; null na criação.
     */
    private function lancamentoEditado(): ?Lancamento
    {
        $lancamento = $this->route('lancamento');

        return $lancamento instanceof Lancamento ? $lancamento : null;
    }

    /**
     * Os campos do lançamento, com o valor em centavos e o sinal da direção.
     *
     * @return array{conta_id: int, categoria_id: int|null, data: string, descricao: string, valor: int}
     */
    public function dadosDoLancamento(): array
    {
        $centavos = (int) Dinheiro::paraCentavos($this->string('valor')->toString());

        return [
            'conta_id' => $this->integer('conta_id'),
            'categoria_id' => $this->filled('categoria_id') ? $this->integer('categoria_id') : null,
            'data' => $this->string('data')->toString(),
            'descricao' => $this->string('descricao')->trim()->toString(),
            'valor' => $this->input('direcao') === 'entrada' ? $centavos : -$centavos,
        ];
    }
}
