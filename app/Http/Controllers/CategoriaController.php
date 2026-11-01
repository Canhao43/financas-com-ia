<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('categorias/Index', [
            'categorias' => Categoria::query()->withCount('lancamentos')->orderBy('nome')->get()
                ->map(fn (Categoria $categoria) => [
                    'id' => $categoria->id,
                    'nome' => $categoria->nome,
                    'tipo' => $categoria->tipo->value,
                    'lancamentos' => $categoria->lancamentos_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categorias/Form', ['categoria' => null]);
    }

    public function store(SalvarCategoriaRequest $request): RedirectResponse
    {
        Categoria::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria criada.']);

        return to_route('categorias.index');
    }

    public function edit(Categoria $categoria): Response
    {
        return Inertia::render('categorias/Form', [
            'categoria' => [
                'id' => $categoria->id,
                'nome' => $categoria->nome,
                'tipo' => $categoria->tipo->value,
                'lancamentos' => $categoria->lancamentos()->count(),
                'regras' => $categoria->regras()->count(),
            ],
        ]);
    }

    public function update(SalvarCategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria atualizada.']);

        return to_route('categorias.index');
    }

    /**
     * Os lançamentos da categoria continuam existindo, sem categoria (a chave estrangeira cuida disso).
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria excluída.']);

        return to_route('categorias.index');
    }
}
