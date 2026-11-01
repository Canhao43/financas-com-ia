<?php

namespace App\Http\Controllers;

use App\Enums\TipoConta;
use App\Http\Requests\SalvarContaRequest;
use App\Models\Conta;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Conta não se exclui: com lançamentos, ela é arquivada — o histórico continua lá.
 */
class ContaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('contas/Index', [
            'contas' => Conta::query()->comSaldo()->orderBy('arquivada')->orderBy('nome')->get()
                ->map(fn (Conta $conta) => [
                    'id' => $conta->id,
                    'nome' => $conta->nome,
                    'tipo' => $conta->tipo->rotulo(),
                    'arquivada' => $conta->arquivada,
                    'saldo' => $conta->saldoAtual(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contas/Form', ['conta' => null, 'tipos' => TipoConta::opcoes()]);
    }

    public function store(SalvarContaRequest $request): RedirectResponse
    {
        Conta::query()->create($request->dadosDaConta());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conta criada.']);

        return to_route('contas.index');
    }

    public function edit(Conta $conta): Response
    {
        return Inertia::render('contas/Form', [
            'conta' => [
                'id' => $conta->id,
                'nome' => $conta->nome,
                'tipo' => $conta->tipo->value,
                'saldo_inicial' => Dinheiro::paraTexto($conta->saldo_inicial),
                'arquivada' => $conta->arquivada,
            ],
            'tipos' => TipoConta::opcoes(),
        ]);
    }

    public function update(SalvarContaRequest $request, Conta $conta): RedirectResponse
    {
        $conta->update($request->dadosDaConta());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conta atualizada.']);

        return to_route('contas.index');
    }
}
