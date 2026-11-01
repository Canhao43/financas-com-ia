<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarRegraRequest;
use App\Models\Categoria;
use App\Models\Lancamento;
use App\Models\Regra;
use App\Regras\Categorizador;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection as Lista;
use Inertia\Inertia;
use Inertia\Response;

class RegraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('regras/Index', [
            'regras' => Regra::query()->with('categoria:id,nome,tipo')->orderBy('texto')->get()
                ->map(fn (Regra $regra) => [
                    'id' => $regra->id,
                    'texto' => $regra->texto,
                    'categoria' => $regra->categoria->nome,
                    'tipo' => $regra->categoria->tipo->value,
                ]),
            'semCategoria' => Lancamento::query()->whereNull('categoria_id')->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('regras/Form', ['regra' => null, 'categorias' => $this->categorias()]);
    }

    public function store(SalvarRegraRequest $request): RedirectResponse
    {
        Regra::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Regra criada.']);

        return to_route('regras.index');
    }

    public function edit(Regra $regra): Response
    {
        return Inertia::render('regras/Form', [
            'regra' => ['id' => $regra->id, 'texto' => $regra->texto, 'categoria_id' => $regra->categoria_id],
            'categorias' => $this->categorias(),
        ]);
    }

    public function update(SalvarRegraRequest $request, Regra $regra): RedirectResponse
    {
        $regra->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Regra atualizada.']);

        return to_route('regras.index');
    }

    public function destroy(Regra $regra): RedirectResponse
    {
        $regra->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Regra excluída.']);

        return to_route('regras.index');
    }

    /**
     * Passa as regras nos lançamentos que ainda não têm categoria. Os que têm, nunca: a escolha da
     * pessoa vale mais que a regra.
     */
    public function aplicar(Categorizador $categorizador): RedirectResponse
    {
        $categorizados = 0;

        Lancamento::query()->whereNull('categoria_id')->chunkById(500, function (Collection $lancamentos) use ($categorizador, &$categorizados) {
            foreach ($lancamentos as $lancamento) {
                $categoria = $categorizador->categoriaPara($lancamento->descricao, $lancamento->valor);

                if ($categoria !== null) {
                    $lancamento->update(['categoria_id' => $categoria->id]);
                    $categorizados++;
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($categorizados) {
            0 => 'Nenhum lançamento sem categoria combina com as regras.',
            1 => '1 lançamento categorizado.',
            default => "{$categorizados} lançamentos categorizados.",
        }]);

        return to_route('regras.index');
    }

    /**
     * @return Lista<int, array{id: int, nome: string, tipo: string}>
     */
    private function categorias(): Lista
    {
        return Categoria::query()->orderBy('nome')->get()
            ->map(fn (Categoria $categoria) => ['id' => $categoria->id, 'nome' => $categoria->nome, 'tipo' => $categoria->tipo->value])
            ->toBase();
    }
}
