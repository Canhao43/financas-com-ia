<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\LancamentoController;
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
});

require __DIR__.'/settings.php';
