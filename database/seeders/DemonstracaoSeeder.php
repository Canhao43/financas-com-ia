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

        // Mercado e restaurante mudam de um mês para o outro, para o relatório ter o que comparar.
        $mercado = [2 => -55010, 1 => -62345, 0 => -71890];
        $restaurante = [2 => -9800, 1 => -14250, 0 => -12100];

        foreach ([2, 1, 0] as $mesesAtras) {
            $mes = CarbonImmutable::now()->subMonthsNoOverflow($mesesAtras)->startOfMonth();
            // A fatura do cartão é paga pela conta corrente: transferência, não despesa.
            $fatura = 62345 + 5590 + 14250;

            $lancamentos = [
                [$corrente, 5, 'Salário', 'Salário', TipoCategoria::Receita, 650000],
                [$corrente, 6, 'Aluguel', 'Moradia', TipoCategoria::Despesa, -180000],
                [$cartao, 8, 'Supermercado', 'Mercado', TipoCategoria::Despesa, $mercado[$mesesAtras]],
                [$corrente, 10, 'Pagamento da fatura', 'Transferência entre contas', TipoCategoria::Transferencia, -$fatura],
                [$cartao, 10, 'Pagamento recebido', 'Transferência entre contas', TipoCategoria::Transferencia, $fatura],
                [$cartao, 12, 'Streaming', 'Assinaturas', TipoCategoria::Despesa, -5590],
                [$corrente, 15, 'Farmácia', 'Saúde', TipoCategoria::Despesa, -8790],
                [$cartao, 20, 'Restaurante', 'Alimentação fora', TipoCategoria::Despesa, $restaurante[$mesesAtras]],
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
