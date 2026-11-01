<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Só o que é seguro rodar em produção. O usuário nasce por `php artisan usuario:criar`
     * — um seeder com usuário de senha conhecida seria uma porta aberta no servidor.
     */
    public function run(): void
    {
        $this->call(CategoriaPadraoSeeder::class);
    }
}
