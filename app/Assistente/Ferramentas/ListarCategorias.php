<?php

namespace App\Assistente\Ferramentas;

use App\Assistente\Ferramenta;
use App\Enums\TipoCategoria;
use App\Models\Categoria;

/**
 * Os nomes exatos, para o modelo traduzir "supermercado" ou "comida" para a categoria que existe.
 */
final class ListarCategorias implements Ferramenta
{
    public function nome(): string
    {
        return 'listar_categorias';
    }

    public function definicao(): array
    {
        return [
            'name' => $this->nome(),
            'description' => 'Os nomes das categorias, por tipo. Use antes de filtrar por categoria quando a pessoa '
                .'usar outro nome ("comida", "uber") e você não souber o nome exato.',
            'inputSchema' => ['type' => 'object', 'properties' => new \stdClass, 'additionalProperties' => false],
        ];
    }

    public function executar(array $entrada): array
    {
        $categorias = Categoria::query()->orderBy('nome')->get();

        return [
            'despesas' => $this->nomes($categorias->where('tipo', TipoCategoria::Despesa)),
            'receitas' => $this->nomes($categorias->where('tipo', TipoCategoria::Receita)),
            'transferencias_entre_contas' => $this->nomes($categorias->where('tipo', TipoCategoria::Transferencia)),
        ];
    }

    /**
     * @param  iterable<Categoria>  $categorias
     * @return list<string>
     */
    private function nomes(iterable $categorias): array
    {
        $nomes = [];
        foreach ($categorias as $categoria) {
            $nomes[] = $categoria->nome;
        }

        return $nomes;
    }
}
