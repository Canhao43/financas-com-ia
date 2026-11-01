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
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);
            $table->string('arquivo', 255);
            // Só os 4 últimos dígitos: bastam para avisar de arquivo trocado.
            $table->string('final_da_conta', 4)->nullable();
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            // O saldo que o banco informou, em centavos, para conferir com o do app.
            $table->bigInteger('saldo_banco')->nullable();
            $table->date('saldo_em')->nullable();
            // As linhas lidas do arquivo, só enquanto a prévia não é confirmada.
            $table->json('linhas')->nullable();
            $table->unsignedInteger('importados')->default(0);
            $table->unsignedInteger('vinculados')->default(0);
            $table->unsignedInteger('ja_existiam')->default(0);
            $table->unsignedInteger('ignorados')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};
