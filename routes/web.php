<?php

use App\Http\Controllers\AssistenteController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\RegraController;
use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;

// App pessoal: a raiz leva direto ao painel (ou ao login, para quem não entrou).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('lancamentos', LancamentoController::class)
        ->except('show')
        ->parameters(['lancamentos' => 'lancamento']);

    // Sem exclusão: conta com histórico é arquivada.
    Route::resource('contas', ContaController::class)
        ->except(['show', 'destroy'])
        ->parameters(['contas' => 'conta']);

    Route::resource('categorias', CategoriaController::class)
        ->except('show')
        ->parameters(['categorias' => 'categoria']);

    // Excluir uma importação é desfazê-la (ou descartar a prévia).
    Route::resource('importacoes', ImportacaoController::class)
        ->except(['edit', 'update'])
        ->parameters(['importacoes' => 'importacao']);
    Route::post('importacoes/{importacao}/confirmar', [ImportacaoController::class, 'confirmar'])
        ->name('importacoes.confirmar');

    Route::resource('regras', RegraController::class)
        ->except('show')
        ->parameters(['regras' => 'regra']);
    Route::post('regras/aplicar', [RegraController::class, 'aplicar'])->name('regras.aplicar');

    Route::get('relatorios', RelatorioController::class)->name('relatorios');

    Route::get('assistente', [AssistenteController::class, 'index'])->name('assistente.index');
    Route::get('assistente/{conversa}', [AssistenteController::class, 'show'])->name('assistente.show');
    Route::delete('assistente/{conversa}', [AssistenteController::class, 'destroy'])->name('assistente.destroy');
    // Cada pergunta custa chamadas pagas à API: um teto por minuto protege a conta de um
    // reenvio em loop.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('assistente', [AssistenteController::class, 'store'])->name('assistente.store');
        Route::post('assistente/{conversa}', [AssistenteController::class, 'perguntar'])->name('assistente.perguntar');
    });
});

require __DIR__.'/settings.php';
