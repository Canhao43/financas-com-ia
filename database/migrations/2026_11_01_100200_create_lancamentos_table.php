<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            // Conta com lançamento não se apaga: arquiva.
            $table->foreignId('conta_id')->constrained()->restrictOnDelete();
            // Categoria apagada deixa o lançamento sem categoria, não apaga o lançamento.
            $table->foreignId('categoria_id')->nullable()->constrained()->nullOnDelete();
            $table->date('data');
            $table->string('descricao', 255);
            // Centavos com sinal: saída negativa, entrada positiva — o mesmo sinal do extrato OFX.
            $table->bigInteger('valor');
            $table->timestamps();

            $table->index('data');
            $table->index(['conta_id', 'data']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
