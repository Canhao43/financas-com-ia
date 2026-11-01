<?php

namespace App\Assistente\Ferramentas;

use App\Assistente\Ferramenta;
use App\Assistente\Formato;
use App\Assistente\LeitorDeEntrada;
use App\Relatorios\LinhaDoRelatorio;
use App\Relatorios\RelatorioMensal;

/**
 * O relatório do mês, o mesmo da tela: o assistente e a tela nunca dão números diferentes.
 */
final class ResumoDoMes implements Ferramenta
{
    public function __construct(private RelatorioMensal $relatorios) {}

    public function nome(): string
    {
        return 'resumo_do_mes';
    }

    public function definicao(): array
    {
        return [
            'name' => $this->nome(),
            'description' => 'Receitas, despesas e resultado de um mês, comparados com o mês anterior, e o total de cada '
                .'categoria com a participação no mês. Transferências entre contas (como o pagamento da fatura) não '
                .'são receita nem despesa e aparecem só somadas à parte. Use para "quanto gastei em outubro", '
                .'"em que mais gastei", "gastei mais que no mês passado". Valores de despesa vêm sem o sinal de menos.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'mes' => ['type' => 'string', 'description' => 'O mês, no formato AAAA-MM. Ex.: 2026-10.'],
                ],
                'required' => ['mes'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executar(array $entrada): array
    {
        $mes = LeitorDeEntrada::mes($entrada, 'mes');
        $relatorio = $this->relatorios->gerar($mes);

        return [
            'mes' => Formato::mes($mes),
            'mes_anterior' => Formato::mes($mes->subMonthNoOverflow()),
            'receitas' => Formato::comparacao($relatorio->receitas),
            'despesas' => Formato::comparacao($relatorio->despesas),
            // O resultado tem sinal: negativo é mês que gastou mais do que recebeu.
            'resultado' => [
                'total' => Formato::reais($relatorio->resultado->atual),
                'mes_anterior' => Formato::reais($relatorio->resultado->anterior),
            ],
            'transferencias_entre_contas' => Formato::reais($relatorio->transferencias),
            'despesas_por_categoria' => array_map($this->linha(...), $relatorio->despesasPorCategoria),
            'receitas_por_categoria' => array_map($this->linha(...), $relatorio->receitasPorCategoria),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function linha(LinhaDoRelatorio $linha): array
    {
        return [
            'categoria' => $linha->nome,
            'participacao_no_mes' => Formato::porcentagem($linha->participacao),
            ...Formato::comparacao($linha->valor),
        ];
    }
}
