<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarLancamentoRequest;
use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LancamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $mes = $this->mes($request->string('mes')->toString());
        $contaId = $request->integer('conta') ?: null;

        $lancamentos = Lancamento::query()
            ->with(['conta:id,nome', 'categoria:id,nome'])
            ->doMes($mes)
            ->when($contaId, fn ($query) => $query->where('conta_id', $contaId))
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('lancamentos/Index', [
            'mes' => $mes->format('Y-m'),
            'mesAnterior' => $mes->subMonthNoOverflow()->format('Y-m'),
            'mesSeguinte' => $mes->addMonthNoOverflow()->format('Y-m'),
            'contaId' => $contaId,
            'contas' => Conta::query()->orderBy('nome')->get(['id', 'nome']),
            'lancamentos' => $lancamentos->map(fn (Lancamento $lancamento) => [
                'id' => $lancamento->id,
                'data' => $lancamento->data->toDateString(),
                'descricao' => $lancamento->descricao,
                'valor' => $lancamento->valor,
                'conta' => $lancamento->conta->nome,
                'categoria' => $lancamento->categoria?->nome,
            ]),
            // Totais calculados aqui e testados — nunca no front, nunca por IA.
            'totais' => [
                'entradas' => (int) $lancamentos->where('valor', '>', 0)->sum('valor'),
                'saidas' => (int) $lancamentos->where('valor', '<', 0)->sum('valor'),
                'saldo' => (int) $lancamentos->sum('valor'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('lancamentos/Form', [
            ...$this->opcoes(),
            'lancamento' => null,
            'padrao' => ['data' => CarbonImmutable::today()->toDateString()],
        ]);
    }

    public function store(SalvarLancamentoRequest $request): RedirectResponse
    {
        $lancamento = Lancamento::query()->create($request->dadosDoLancamento());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lançamento salvo.']);

        return to_route('lancamentos.index', ['mes' => $lancamento->data->format('Y-m')]);
    }

    public function edit(Lancamento $lancamento): Response
    {
        return Inertia::render('lancamentos/Form', [
            ...$this->opcoes($lancamento),
            'lancamento' => [
                'id' => $lancamento->id,
                'direcao' => $lancamento->ehEntrada() ? 'entrada' : 'saida',
                'valor' => Dinheiro::paraTexto(abs($lancamento->valor)),
                'data' => $lancamento->data->toDateString(),
                'descricao' => $lancamento->descricao,
                'conta_id' => $lancamento->conta_id,
                'categoria_id' => $lancamento->categoria_id,
            ],
            'padrao' => null,
        ]);
    }

    public function update(SalvarLancamentoRequest $request, Lancamento $lancamento): RedirectResponse
    {
        $lancamento->update($request->dadosDoLancamento());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lançamento atualizado.']);

        return to_route('lancamentos.index', ['mes' => $lancamento->data->format('Y-m')]);
    }

    public function destroy(Lancamento $lancamento): RedirectResponse
    {
        $mes = $lancamento->data->format('Y-m');
        $lancamento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lançamento excluído.']);

        return to_route('lancamentos.index', ['mes' => $mes]);
    }

    /**
     * O mês pedido em "AAAA-MM"; qualquer outra coisa vira o mês atual.
     */
    private function mes(string $texto): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $texto)
            ? CarbonImmutable::createFromFormat('!Y-m', $texto)
            : CarbonImmutable::today()->startOfMonth();
    }

    /**
     * @return array{contas: mixed, categorias: mixed}
     */
    private function opcoes(?Lancamento $lancamento = null): array
    {
        return [
            // Arquivadas ficam de fora, exceto a do próprio lançamento em edição.
            'contas' => Conta::query()
                ->where(fn ($query) => $query
                    ->where('arquivada', false)
                    ->when($lancamento, fn ($query, Lancamento $editado) => $query->orWhere('id', $editado->conta_id)))
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'categorias' => Categoria::query()->orderBy('nome')->get(['id', 'nome', 'tipo'])
                ->map(fn (Categoria $categoria) => [
                    'id' => $categoria->id,
                    'nome' => $categoria->nome,
                    'direcoes' => $categoria->tipo->direcoes(),
                ]),
        ];
    }
}
