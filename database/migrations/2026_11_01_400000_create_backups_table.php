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
        // Uma linha por tentativa, com sucesso ou não: é daqui que o painel sabe se o backup atrasou.
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('arquivo');
            $table->boolean('sucesso');
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
