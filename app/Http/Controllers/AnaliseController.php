<?php

namespace App\Http\Controllers;

use App\Assistente\AnaliseNaoEscrita;
use App\Assistente\AnalistaDoMes;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnaliseController extends Controller
{
    /**
     * Escreve (ou escreve de novo) a análise do mês e volta para o relatório. Se não der certo,
     * a análise anterior continua lá, e o motivo aparece no lugar dela.
     */
    public function __invoke(Request $request, AnalistaDoMes $analista): RedirectResponse
    {
        $mes = Mes::doTexto($request->validate(['mes' => ['required', 'date_format:Y-m']])['mes']);

        if (! $analista->configurado()) {
            throw ValidationException::withMessages([
                'analise' => 'A análise está desligada: falta a chave da API da Anthropic (ANTHROPIC_API_KEY) no .env.',
            ]);
        }

        // Uma chamada com raciocínio, e às vezes a correção: o limite padrão do PHP em produção
        // (30 s) cortaria no meio.
        set_time_limit(300);

        try {
            $analista->escrever($mes);
        } catch (AnaliseNaoEscrita $erro) {
            throw ValidationException::withMessages(['analise' => $erro->getMessage()]);
        }

        return to_route('relatorios', ['mes' => $mes->format('Y-m')]);
    }
}
