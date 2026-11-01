<?php

namespace App\Http\Controllers;

use App\Relatorios\RelatorioMensal;
use App\Support\Mes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioController extends Controller
{
    public function __invoke(Request $request, RelatorioMensal $relatorios): Response
    {
        $mes = Mes::doTexto($request->string('mes')->toString());

        return Inertia::render('relatorios/Mes', [
            'mes' => $mes->format('Y-m'),
            'mesAnterior' => $mes->subMonthNoOverflow()->format('Y-m'),
            'mesSeguinte' => $mes->addMonthNoOverflow()->format('Y-m'),
            // Todos os números vêm prontos e testados; a tela só formata.
            ...$relatorios->gerar($mes)->paraArray(),
        ]);
    }
}
