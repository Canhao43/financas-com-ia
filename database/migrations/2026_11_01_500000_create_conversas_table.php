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
        Schema::create('conversas', function (Blueprint $table) {
            $table->id();
            // A primeira pergunta, encurtada: é como a conversa aparece na lista.
            $table->string('titulo', 120);
            $table->timestamps();
        });

        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained()->cascadeOnDelete();
            $table->string('papel');
            $table->text('texto');
            // O "hoje" da pergunta fica gravado: o histórico reenviado ao Claude precisa sair
            // igual, byte a byte, em todas as perguntas seguintes da conversa.
            $table->date('hoje')->nullable();
            // Da resposta: o que foi consultado, os valores que as consultas devolveram, os que
            // apareceram sem fonte, e quem respondeu a que custo.
            $table->string('situacao')->nullable();
            $table->json('consultas')->nullable();
            $table->json('valores_das_consultas')->nullable();
            $table->json('valores_sem_fonte')->nullable();
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
        Schema::dropIfExists('mensagens');
        Schema::dropIfExists('conversas');
    }
};
