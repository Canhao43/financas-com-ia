<?php

namespace App\Http\Controllers;

use App\Enums\OrigemLancamento;
use App\Enums\StatusImportacao;
use App\Http\Requests\ConfirmarImportacaoRequest;
use App\Http\Requests\ImportarExtratoRequest;
use App\Importacao\Classificacao;
use App\Importacao\Conciliador;
use App\Importacao\Importador;
use App\Models\Conta;
use App\Models\Importacao;
use App\Models\Lancamento;
use App\Ofx\LeitorOfx;
use App\Ofx\OfxInvalido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importar é em dois passos: o arquivo vira uma prévia, e só a confirmação grava lançamentos.
 */
class ImportacaoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('importacoes/Index', [
            'importacoes' => Importacao::query()->with('conta:id,nome')->latest('id')->get()
                ->map(fn (Importacao $importacao) => $this->dados($importacao)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('importacoes/Create', [
            'contas' => Conta::query()->where('arquivada', false)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(ImportarExtratoRequest $request, LeitorOfx $leitor, Importador $importador): RedirectResponse
    {
        $arquivo = $request->arquivo();

        try {
            $extrato = $leitor->ler($arquivo->get() ?: '');
        } catch (OfxInvalido $erro) {
            throw ValidationException::withMessages(['arquivo' => $erro->getMessage()]);
        }

        $importacao = $importador->preparar($request->conta(), $arquivo->getClientOriginalName(), $extrato);

        return to_route('importacoes.show', $importacao);
    }

    public function show(Importacao $importacao, Conciliador $conciliador): Response
    {
        return $importacao->pendente()
            ? $this->previa($importacao, $conciliador)
            : $this->resumo($importacao);
    }

    public function confirmar(ConfirmarImportacaoRequest $request, Importacao $importacao, Importador $importador): RedirectResponse
    {
        $confirmou = $importador->confirmar($importacao, $request->decisoes());

        Inertia::flash('toast', $confirmou
            ? ['type' => 'success', 'message' => 'Extrato importado.']
            : ['type' => 'info', 'message' => 'Este extrato já tinha sido importado.']);

        return to_route('importacoes.show', $importacao);
    }

    public function destroy(Importacao $importacao, Importador $importador): RedirectResponse
    {
        $mensagem = $importacao->pendente() ? 'Prévia descartada.' : 'Importação desfeita.';

        $importador->desfazer($importacao);

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensagem]);

        return to_route('importacoes.index');
    }

    private function previa(Importacao $importacao, Conciliador $conciliador): Response
    {
        $conta = $importacao->conta;
        $contaAnterior = $conta->importacoes()
            ->where('status', StatusImportacao::Concluida)
            ->whereNotNull('final_da_conta')
            ->latest('id')
            ->value('final_da_conta');

        return Inertia::render('importacoes/Previa', [
            'importacao' => $this->dados($importacao),
            // Conta e cartão do mesmo banco: o arquivo trocado é o erro mais provável, e aqui ainda dá para voltar.
            'contaDiferente' => $contaAnterior !== null && $importacao->final_da_conta !== null && $contaAnterior !== $importacao->final_da_conta
                ? ['arquivo' => $importacao->final_da_conta, 'anterior' => $contaAnterior]
                : null,
            'linhas' => array_map(fn (Classificacao $item) => [
                'indice' => $item->indice,
                'data' => $item->linha->data->toDateString(),
                'descricao' => $item->linha->descricao,
                'valor' => $item->linha->valor,
                'situacao' => $item->situacao->value,
                'parecidoCom' => $item->parecidoCom === null ? null : $this->lancamento($item->parecidoCom),
            ], $conciliador->classificar($conta, $importacao->linhasDoExtrato())),
        ]);
    }

    private function resumo(Importacao $importacao): Response
    {
        return Inertia::render('importacoes/Resumo', [
            'importacao' => $this->dados($importacao),
            'lancamentos' => $importacao->lancamentos()->with('categoria:id,nome')->orderByDesc('data')->orderByDesc('id')->get()
                ->map(fn (Lancamento $lancamento) => [
                    ...$this->lancamento($lancamento),
                    'vinculado' => $lancamento->origem === OrigemLancamento::Manual,
                ]),
            'conferencia' => $this->conferencia($importacao),
        ]);
    }

    /**
     * O saldo do app no dia em que o banco informou o dele. Calculado com o estado de hoje: se uma
     * edição ou outra importação desalinhar a conta, a diferença aparece aqui.
     *
     * @return array{em: string, banco: int, app: int, diferenca: int}|null
     */
    private function conferencia(Importacao $importacao): ?array
    {
        if ($importacao->saldo_banco === null || $importacao->saldo_em === null) {
            return null;
        }

        $noApp = $importacao->conta->saldoEm($importacao->saldo_em);

        return [
            'em' => $importacao->saldo_em->toDateString(),
            'banco' => $importacao->saldo_banco,
            'app' => $noApp,
            'diferenca' => $noApp - $importacao->saldo_banco,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Importacao $importacao): array
    {
        return [
            'id' => $importacao->id,
            'status' => $importacao->status->value,
            'arquivo' => $importacao->arquivo,
            'conta' => $importacao->conta->nome,
            'contaId' => $importacao->conta_id,
            'inicio' => $importacao->inicio?->toDateString(),
            'fim' => $importacao->fim?->toDateString(),
            'criadaEm' => $importacao->created_at?->toDateString(),
            'importados' => $importacao->importados,
            'vinculados' => $importacao->vinculados,
            'jaExistiam' => $importacao->ja_existiam,
            'ignorados' => $importacao->ignorados,
        ];
    }

    /**
     * @return array{id: int, data: string, descricao: string, valor: int, categoria: string|null}
     */
    private function lancamento(Lancamento $lancamento): array
    {
        return [
            'id' => $lancamento->id,
            'data' => $lancamento->data->toDateString(),
            'descricao' => $lancamento->descricao,
            'valor' => $lancamento->valor,
            'categoria' => $lancamento->categoria?->nome,
        ];
    }
}
