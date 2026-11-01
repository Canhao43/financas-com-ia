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
        // Uma análise por mês: escrever de novo substitui a anterior. Só a que deu certo é
        // gravada, então uma falha da API nunca apaga a análise que já existia.
        Schema::create('analises', function (Blueprint $table) {
            $table->id();
            // "2026-10", como o mês aparece no endereço do relatório.
            $table->string('mes', 7)->unique();
            $table->text('resumo');
            $table->json('destaques');
            // Os números exatamente como foram ao Claude, e a impressão deles: se os lançamentos
            // do mês mudarem depois, a impressão de agora não bate e a tela avisa.
            $table->json('fatos');
            $table->string('impressao', 64);
            $table->json('valores_sem_fonte');
            // Quantas vezes o Claude reescreveu porque citou valor que não estava nos dados.
            $table->unsignedTinyInteger('correcoes')->default(0);
            $table->string('modelo')->nullable();
            $table->json('uso')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analises');
    }
};
