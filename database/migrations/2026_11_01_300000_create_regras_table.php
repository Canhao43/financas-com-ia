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
        Schema::create('regras', function (Blueprint $table) {
            $table->id();
            // Guardado já normalizado (maiúsculas, sem acento): é assim que se compara com a descrição.
            $table->string('texto', 60)->unique();
            // Regra sem categoria não faz nada: excluir a categoria leva as regras dela junto.
            $table->foreignId('categoria_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regras');
    }
};
