<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppEmPortuguesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_raiz_leva_ao_painel_e_quem_nao_entrou_vai_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_errado_responde_em_portugues(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'senha-errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
    }

    public function test_validacao_responde_em_portugues_com_o_nome_do_campo_traduzido(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => '', 'email' => 'nao-e-email'])
            ->assertSessionHasErrors([
                'name' => 'O campo nome é obrigatório.',
                'email' => 'O campo e-mail deve ser um e-mail válido.',
            ]);
    }

    public function test_mensagem_de_sucesso_vem_traduzida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Caio', 'email' => $user->email])
            ->assertSessionHas('inertia.flash_data.toast.message', 'Perfil atualizado.');
    }
}
