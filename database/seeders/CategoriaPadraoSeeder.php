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

    public const TRANSFERENCIAS = ['Transferência entre contas'];

    public function run(): void
    {
        $categorias = [
            TipoCategoria::Despesa->value => self::DESPESAS,
            TipoCategoria::Receita->value => self::RECEITAS,
            TipoCategoria::Transferencia->value => self::TRANSFERENCIAS,
        ];

        foreach ($categorias as $tipo => $nomes) {
            foreach ($nomes as $nome) {
                Categoria::query()->firstOrCreate(['nome' => $nome, 'tipo' => $tipo]);
            }
        }
    }
}
