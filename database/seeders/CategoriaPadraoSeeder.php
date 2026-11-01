<?php

namespace Database\Seeders;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use Illuminate\Database\Seeder;

/**
 * Categorias de partida. Rodar de novo não duplica: só cria as que faltam.
 */
class CategoriaPadraoSeeder extends Seeder
{
    public const DESPESAS = ['Moradia', 'Mercado', 'Alimentação fora', 'Transporte', 'Saúde', 'Educação', 'Lazer', 'Assinaturas', 'Compras', 'Impostos e taxas', 'Outras despesas'];

    public const RECEITAS = ['Salário', 'Renda extra', 'Rendimentos', 'Reembolso', 'Outras receitas'];

    public function run(): void
    {
        foreach (self::DESPESAS as $nome) {
            Categoria::query()->firstOrCreate(['nome' => $nome, 'tipo' => TipoCategoria::Despesa]);
        }

        foreach (self::RECEITAS as $nome) {
            Categoria::query()->firstOrCreate(['nome' => $nome, 'tipo' => TipoCategoria::Receita]);
        }
    }
}
