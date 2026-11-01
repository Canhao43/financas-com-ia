<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioUnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_existe_cadastro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Invasor',
            'email' => 'invasor@example.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_comando_cria_o_usuario_ja_verificado(): void
    {
        $this->artisan('usuario:criar')
            ->expectsQuestion('Nome', 'Caio')
            ->expectsQuestion('E-mail', 'caio@example.com')
            ->expectsQuestion('Senha (mínimo de 12 caracteres, com letras e números)', 'senha-forte-123')
            ->assertSuccessful();

        $usuario = User::sole();
        $this->assertSame('caio@example.com', $usuario->email);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertTrue(Hash::check('senha-forte-123', $usuario->password));
    }

    public function test_comando_recusa_criar_um_segundo_usuario(): void
    {
        User::factory()->create();

        $this->artisan('usuario:criar')->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
