<?php

use Illuminate\Support\Facades\Route;

// App pessoal: a raiz leva direto ao painel (ou ao login, para quem não entrou).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
