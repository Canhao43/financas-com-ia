<?php

namespace App\Assistente\Ferramentas;

use App\Assistente\DescreveEntrada;
use App\Assistente\EntradaInvalida;
use App\Assistente\Ferramenta;
use App\Assistente\Formato;
use App\Assistente\LeitorDeEntrada;
use App\Enums\TipoCategoria;
use App\Models\Categoria;
use App\Relatorios\Comparacao;
use App\Relatorios\TotalDoPeriodo;

/**
 * Dois períodos lado a lado, com a diferença e a variação já calculadas: "gastei mais em
 * agosto ou em outubro?" não pode depender de o modelo subtrair dois números.
 */
final class CompararPeriodos implements Ferramenta
{
    use DescreveEntrada;

    public function __construct(private TotalDoPeriodo $totais) {}

    public function descrever(array $entrada): string
    {
        $periodo = is_array($entrada['periodo'] ?? null) ? $entrada['periodo'] : [];
        $comparado = is_array($entrada['comparado_com'] ?? null) ? $entrada['comparado_com'] : [];

        return 'Comparação de '.$this->campo($entrada, 'tipo', 'despesas').': '.$this->periodoDe($periodo)
            .' com '.$this->periodoDe($comparado).$this->categoriaDe($entrada);
    }

    public function nome(): string
    {
        return 'comparar_periodos';
    }

    public function definicao(): array
    {
        $periodo = [
            'type' => 'object',
            'properties' => [
                'de' => ['type' => 'string', 'description' => 'Primeiro dia, AAAA-MM-DD.'],
                'ate' => ['type' => 'string', 'description' => 'Último dia, AAAA-MM-DD (entra no total).'],
            ],
            'required' => ['de', 'ate'],
            'additionalProperties' => false,
        ];

        return [
            'name' => $this->nome(),
            'description' => 'Compara o total de despesas ou de receitas de dois períodos, de uma categoria ou de todas. '
                .'Devolve os dois totais, a diferença em reais e a variação do primeiro em relação ao segundo. Use para '
                .'qualquer comparação que não seja "este mês contra o anterior" (essa já vem no resumo_do_mes).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => ['type' => 'string', 'enum' => ['despesas', 'receitas']],
                    'categoria' => ['type' => 'string', 'description' => 'Nome da categoria, ou "Sem categoria". Omita para todas.'],
                    'periodo' => $periodo,
                    'comparado_com' => $periodo,
                ],
                'required' => ['tipo', 'periodo', 'comparado_com'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executar(array $entrada): array
    {
        /** @var 'despesas'|'receitas' $tipo */
        $tipo = LeitorDeEntrada::opcao($entrada, 'tipo', ['despesas', 'receitas']);
        $categoria = LeitorDeEntrada::categoria($entrada, 'categoria');

        if ($categoria instanceof Categoria && $categoria->tipo === TipoCategoria::Transferencia) {
            throw new EntradaInvalida("\"{$categoria->nome}\" é transferência entre contas, não despesa nem receita.");
        }

        [$deA, $ateA] = LeitorDeEntrada::periodo($this->objeto($entrada, 'periodo'));
        [$deB, $ateB] = LeitorDeEntrada::periodo($this->objeto($entrada, 'comparado_com'));
        $a = $this->totais->calcular($deA, $ateA, $tipo, $categoria);
        $b = $this->totais->calcular($deB, $ateB, $tipo, $categoria);
        $variacao = (new Comparacao($a['total'], $b['total']))->variacao();

        return [
            'tipo' => $tipo,
            'categoria' => match (true) {
                $categoria instanceof Categoria => $categoria->nome,
                $categoria === 'sem' => 'Sem categoria',
                default => 'todas',
            },
            'periodo' => ['datas' => Formato::periodo($deA, $ateA), 'total' => Formato::reais(abs($a['total']))],
            'comparado_com' => ['datas' => Formato::periodo($deB, $ateB), 'total' => Formato::reais(abs($b['total']))],
            'diferenca_do_periodo_para_o_comparado' => Formato::diferenca($a['total'], $b['total']),
            'variacao' => $variacao === null ? null : Formato::porcentagem($variacao, comSinal: true),
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<array-key, mixed>
     */
    private function objeto(array $entrada, string $campo): array
    {
        $valor = $entrada[$campo] ?? null;

        if (! is_array($valor)) {
            throw new EntradaInvalida("O campo \"{$campo}\" precisa ser um objeto com \"de\" e \"ate\".");
        }

        return $valor;
    }
}
