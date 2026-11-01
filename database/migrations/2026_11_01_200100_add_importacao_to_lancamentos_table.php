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
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->string('origem', 10)->default('manual');
            // Desfazer a importação apaga a importação; o lançamento nunca fica apontando para o nada.
            $table->foreignId('importacao_id')->nullable()->constrained('importacoes')->nullOnDelete();
            // FITID: o identificador que o banco dá à transação. Nem todo banco o mantém estável.
            $table->string('id_externo', 255)->nullable();
            // Impressão digital da linha do extrato como o banco mandou — editar o lançamento não a muda.
            $table->char('impressao', 64)->nullable();

            // A trava final contra duplicado: vale mesmo se a checagem do código falhar.
            $table->unique(['conta_id', 'impressao']);
            $table->index(['conta_id', 'id_externo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropUnique(['conta_id', 'impressao']);
            $table->dropIndex(['conta_id', 'id_externo']);
            $table->dropConstrainedForeignId('importacao_id');
            $table->dropColumn(['origem', 'id_externo', 'impressao']);
        });
    }
};
