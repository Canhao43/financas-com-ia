<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Conta;
use App\Models\Lancamento;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $mes = CarbonImmutable::today()->startOfMonth();
        $doMes = Lancamento::query()->doMes($mes);

        return Inertia::render('Dashboard', [
            'mes' => $mes->format('Y-m'),
            // Saldo de todas as contas ativas: inicial mais lançamentos, somado aqui.
            'saldoTotal' => Conta::query()->where('arquivada', false)->comSaldo()->get()
                ->sum(fn (Conta $conta) => $conta->saldoAtual()),
            'entradasDoMes' => (int) (clone $doMes)->where('valor', '>', 0)->sum('valor'),
            'saidasDoMes' => (int) (clone $doMes)->where('valor', '<', 0)->sum('valor'),
            'ultimos' => Lancamento::query()->with('categoria:id,nome')->orderByDesc('data')->orderByDesc('id')->limit(5)->get()
                ->map(fn (Lancamento $lancamento) => [
                    'id' => $lancamento->id,
                    'data' => $lancamento->data->toDateString(),
                    'descricao' => $lancamento->descricao,
                    'valor' => $lancamento->valor,
                    'categoria' => $lancamento->categoria?->nome,
                ]),
            'avisoDeBackup' => $this->avisoDeBackup(),
        ]);
    }

    /**
     * Backup que falha em silêncio só é descoberto no dia em que se precisa dele. O aviso
     * fica na tela que o dono abre todo dia, sem depender de serviço de monitoramento.
     */
    private function avisoDeBackup(): ?string
    {
        if (! config()->boolean('backup.avisar')) {
            return null;
        }

        $ultimo = Backup::query()->where('sucesso', true)->latest('id')->first();
        $quando = $ultimo?->created_at;

        if ($quando !== null && $quando->greaterThan(now()->subHours(config()->integer('backup.aviso_depois_de_horas')))) {
            return null;
        }

        $aviso = $quando === null
            ? 'Nenhum backup deu certo até agora.'
            : 'O último backup que deu certo foi em '.$quando->format('d/m/Y').' às '.$quando->format('H:i').'.';

        $falha = Backup::query()->where('sucesso', false)->where('id', '>', $ultimo->id ?? 0)->latest('id')->value('erro');

        return is_string($falha) ? "{$aviso} Última falha: {$falha}" : $aviso;
    }
}
