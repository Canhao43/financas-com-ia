<?php

namespace App\Http\Controllers;

use App\Assistente\AnalistaDoMes;
use App\Models\Analise;
use App\Models\Lancamento;
use App\Relatorios\RelatorioMensal;
use App\Support\Mes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioController extends Controller
{
    public function __invoke(Request $request, RelatorioMensal $relatorios, AnalistaDoMes $analista): Response
    {
        $mes = Mes::doTexto($request->string('mes')->toString());
        $analise = Analise::query()->where('mes', $mes->format('Y-m'))->first();

        return Inertia::render('relatorios/Mes', [
            'mes' => $mes->format('Y-m'),
            'mesAnterior' => $mes->subMonthNoOverflow()->format('Y-m'),
            'mesSeguinte' => $mes->addMonthNoOverflow()->format('Y-m'),
            // Todos os números vêm prontos e testados; a tela só formata.
            ...$relatorios->gerar($mes)->paraArray(),
            'analise' => $analise === null ? null : [
                'resumo' => $analise->resumo,
                'destaques' => array_values($analise->destaques),
                'valoresSemFonte' => array_values($analise->valores_sem_fonte),
                'desatualizada' => $analista->desatualizada($analise),
                'escritaEm' => $analise->updated_at?->toDateString(),
                'modelo' => $analise->modelo,
            ],
            'analiseLigada' => $analista->configurado(),
            'temLancamentos' => Lancamento::query()->doMes($mes)->exists(),
        ]);
    }
}
