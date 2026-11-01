<?php

namespace Database\Seeders;

use App\Enums\TipoCategoria;
use App\Enums\TipoConta;
use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Dados fictícios para ver o app funcionando: duas contas e três meses de lançamentos.
 * Uso: php artisan db:seed --class=DemonstracaoSeeder
 */
class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Dados de demonstração não entram em produção.');
        }

        $this->call(CategoriaPadraoSeeder::class);

        $corrente = Conta::query()->create(['nome' => 'Conta corrente (demonstração)', 'tipo' => TipoConta::Corrente, 'saldo_inicial' => 250000]);
        $cartao = Conta::query()->create(['nome' => 'Cartão (demonstração)', 'tipo' => TipoConta::CartaoDeCredito, 'saldo_inicial' => 0]);

        $categoria = fn (string $nome, TipoCategoria $tipo) => Categoria::query()->where(['nome' => $nome, 'tipo' => $tipo])->value('id');

        foreach ([2, 1, 0] as $mesesAtras) {
            $mes = CarbonImmutable::now()->subMonthsNoOverflow($mesesAtras)->startOfMonth();

            $lancamentos = [
                [$corrente, 5, 'Salário', 'Salário', TipoCategoria::Receita, 650000],
                [$corrente, 6, 'Aluguel', 'Moradia', TipoCategoria::Despesa, -180000],
                [$cartao, 8, 'Supermercado', 'Mercado', TipoCategoria::Despesa, -62345],
                [$cartao, 12, 'Streaming', 'Assinaturas', TipoCategoria::Despesa, -5590],
                [$corrente, 15, 'Farmácia', 'Saúde', TipoCategoria::Despesa, -8790],
                [$cartao, 20, 'Restaurante', 'Alimentação fora', TipoCategoria::Despesa, -14250],
                [$corrente, 25, 'Transporte por aplicativo', 'Transporte', TipoCategoria::Despesa, -6430],
            ];

            foreach ($lancamentos as [$conta, $dia, $descricao, $nomeCategoria, $tipo, $valor]) {
                Lancamento::query()->create([
                    'conta_id' => $conta->id,
                    'categoria_id' => $categoria($nomeCategoria, $tipo),
                    'data' => $mes->day(min($dia, $mes->daysInMonth))->toDateString(),
                    'descricao' => $descricao,
                    'valor' => $valor,
                ]);
            }
        }
    }
}
