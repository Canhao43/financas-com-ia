<?php

namespace App\Http\Controllers;

use App\Assistente\Assistente;
use App\Http\Requests\PerguntarRequest;
use App\Models\Conversa;
use App\Models\Mensagem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssistenteController extends Controller
{
    public function index(Assistente $assistente): Response
    {
        return Inertia::render('assistente/Index', [
            'configurado' => $assistente->configurado(),
            'conversas' => Conversa::query()->latest('updated_at')->latest('id')->limit(50)->get()
                ->map(fn (Conversa $conversa) => [
                    'id' => $conversa->id,
                    'titulo' => $conversa->titulo,
                    'atualizadaEm' => $conversa->updated_at?->toDateString(),
                ]),
        ]);
    }

    /**
     * Conversa nova, já com a primeira pergunta.
     */
    public function store(PerguntarRequest $request, Assistente $assistente): RedirectResponse
    {
        $this->exigirChave($assistente);

        $conversa = Conversa::query()->create(['titulo' => Str::limit($request->pergunta(), 117)]);
        $this->responder($assistente, $conversa, $request->pergunta());

        return to_route('assistente.show', $conversa);
    }

    public function show(Conversa $conversa, Assistente $assistente): Response
    {
        return Inertia::render('assistente/Conversa', [
            'configurado' => $assistente->configurado(),
            'conversa' => ['id' => $conversa->id, 'titulo' => $conversa->titulo],
            'mensagens' => $conversa->mensagens->map(fn (Mensagem $mensagem) => [
                'id' => $mensagem->id,
                'papel' => $mensagem->papel,
                'texto' => $mensagem->texto,
                'situacao' => $mensagem->situacao?->value,
                'consultas' => array_values($mensagem->consultas ?? []),
                'valoresSemFonte' => array_values($mensagem->valores_sem_fonte ?? []),
                'modelo' => $mensagem->modelo,
                'chamadas' => $mensagem->uso['chamadas'] ?? null,
            ]),
        ]);
    }

    public function perguntar(PerguntarRequest $request, Conversa $conversa, Assistente $assistente): RedirectResponse
    {
        $this->exigirChave($assistente);
        $this->responder($assistente, $conversa, $request->pergunta());

        return to_route('assistente.show', $conversa);
    }

    public function destroy(Conversa $conversa): RedirectResponse
    {
        $conversa->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conversa apagada.']);

        return to_route('assistente.index');
    }

    private function responder(Assistente $assistente, Conversa $conversa, string $pergunta): void
    {
        // Uma pergunta pode levar várias idas e voltas com a API; o limite padrão do PHP em
        // produção (30 s) cortaria a resposta no meio.
        set_time_limit(300);

        $assistente->perguntar($conversa, $pergunta);
    }

    private function exigirChave(Assistente $assistente): void
    {
        if (! $assistente->configurado()) {
            throw ValidationException::withMessages([
                'pergunta' => 'O assistente está desligado: falta a chave da API da Anthropic (ANTHROPIC_API_KEY) no .env.',
            ]);
        }
    }
}
