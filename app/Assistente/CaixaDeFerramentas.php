<?php

namespace App\Assistente;

use App\Assistente\Ferramentas\BuscarLancamentos;
use App\Assistente\Ferramentas\CompararPeriodos;
use App\Assistente\Ferramentas\ListarCategorias;
use App\Assistente\Ferramentas\ResumoDoMes;
use App\Assistente\Ferramentas\SaldosDasContas;
use App\Assistente\Ferramentas\TotalNoPeriodo;
use Throwable;

/**
 * As ferramentas que o Claude pode pedir, e quem as executa. Toda resposta volta como texto
 * JSON; erro volta marcado como erro, para o modelo corrigir a chamada em vez de inventar.
 */
final class CaixaDeFerramentas
{
    /** @var array<string, Ferramenta> */
    private array $ferramentas = [];

    public function __construct(
        ResumoDoMes $resumoDoMes,
        TotalNoPeriodo $totalNoPeriodo,
        CompararPeriodos $compararPeriodos,
        BuscarLancamentos $buscarLancamentos,
        ListarCategorias $listarCategorias,
        SaldosDasContas $saldosDasContas,
    ) {
        foreach (func_get_args() as $ferramenta) {
            $this->ferramentas[$ferramenta->nome()] = $ferramenta;
        }
    }

    /**
     * Sempre na mesma ordem e com o mesmo texto: as definições abrem o prompt, e qualquer byte
     * diferente desfaz o cache entre uma pergunta e outra.
     *
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function definicoes(): array
    {
        return array_values(array_map(fn (Ferramenta $ferramenta) => $ferramenta->definicao(), $this->ferramentas));
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    public function executar(string $nome, array $entrada): ResultadoDaFerramenta
    {
        $ferramenta = $this->ferramentas[$nome] ?? null;

        if ($ferramenta === null) {
            return ResultadoDaFerramenta::erro("A ferramenta \"{$nome}\" não existe.");
        }

        try {
            return ResultadoDaFerramenta::ok($ferramenta->executar($entrada));
        } catch (EntradaInvalida $erro) {
            return ResultadoDaFerramenta::erro($erro->getMessage());
        } catch (Throwable $erro) {
            // O detalhe fica no log; para fora vai só o aviso.
            report($erro);

            return ResultadoDaFerramenta::erro('A consulta falhou no servidor. Diga à pessoa que não foi possível consultar agora.');
        }
    }
}
