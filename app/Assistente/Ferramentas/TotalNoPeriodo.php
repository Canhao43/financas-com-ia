<?php

namespace App\Assistente\Ferramentas;

use App\Assistente\EntradaInvalida;
use App\Assistente\Ferramenta;
use App\Assistente\Formato;
use App\Assistente\LeitorDeEntrada;
use App\Enums\TipoCategoria;
use App\Models\Categoria;
use App\Relatorios\TotalDoPeriodo;

/**
 * Soma de qualquer período: "quanto gastei com mercado nos últimos três meses" é uma conta, e
 * a conta é daqui, não do modelo somando três resumos.
 */
final class TotalNoPeriodo implements Ferramenta
{
    public function __construct(private TotalDoPeriodo $totais) {}

    public function nome(): string
    {
        return 'total_no_periodo';
    }

    public function definicao(): array
    {
        return [
            'name' => $this->nome(),
            'description' => 'Total de despesas ou de receitas num período qualquer (as duas datas entram), de uma '
                .'categoria ou de todas, e quantos lançamentos formam o total. Use sempre que a pergunta cobrir mais '
                .'de um mês ou um pedaço de mês: nunca some resultados de outras chamadas. Sem categoria, '
                .'transferências entre contas ficam de fora. O total de despesas vem sem o sinal de menos.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'de' => ['type' => 'string', 'description' => 'Primeiro dia, AAAA-MM-DD.'],
                    'ate' => ['type' => 'string', 'description' => 'Último dia, AAAA-MM-DD (entra no total).'],
                    'tipo' => ['type' => 'string', 'enum' => ['despesas', 'receitas']],
                    'categoria' => ['type' => 'string', 'description' => 'Nome da categoria, como em listar_categorias, ou "Sem categoria". Omita para todas.'],
                ],
                'required' => ['de', 'ate', 'tipo'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executar(array $entrada): array
    {
        [$de, $ate] = LeitorDeEntrada::periodo($entrada);
        /** @var 'despesas'|'receitas' $tipo */
        $tipo = LeitorDeEntrada::opcao($entrada, 'tipo', ['despesas', 'receitas']);
        $categoria = LeitorDeEntrada::categoria($entrada, 'categoria');

        if ($categoria instanceof Categoria && $categoria->tipo === TipoCategoria::Transferencia) {
            throw new EntradaInvalida("\"{$categoria->nome}\" é transferência entre contas, não despesa nem receita. Para ver esses lançamentos, use buscar_lancamentos.");
        }

        $resultado = $this->totais->calcular($de, $ate, $tipo, $categoria);

        return [
            'periodo' => Formato::periodo($de, $ate),
            'tipo' => $tipo,
            'categoria' => match (true) {
                $categoria instanceof Categoria => $categoria->nome,
                $categoria === 'sem' => 'Sem categoria',
                default => 'todas',
            },
            'total' => Formato::reais(abs($resultado['total'])),
            'quantidade_de_lancamentos' => $resultado['quantidade'],
        ];
    }
}
