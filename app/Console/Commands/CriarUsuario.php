<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * O app é de uma pessoa só e não tem cadastro público: o usuário nasce por aqui,
 * no terminal do servidor, e não por um formulário que qualquer um na internet alcança.
 */
class CriarUsuario extends Command
{
    protected $signature = 'usuario:criar';

    protected $description = 'Cria o usuário do app (não existe cadastro público)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Já existe um usuário. Este app é de uma pessoa só.');

            return self::FAILURE;
        }

        $nome = text('Nome', required: true);
        $email = text('E-mail', required: true, validate: fn (string $valor) => $this->erro('email', $valor, ['email']));
        $senha = password('Senha (mínimo de 12 caracteres, com letras e números)', required: true,
            validate: fn (string $valor) => $this->erro('senha', $valor, [Password::min(12)->letters()->numbers()]));

        User::query()->forceCreate([
            'name' => $nome,
            'email' => $email,
            'password' => $senha,
            // Não há fluxo de verificação de e-mail: quem cria o usuário é o dono do servidor.
            'email_verified_at' => now(),
        ]);

        $this->info("Usuário {$email} criado. Ative a autenticação em dois fatores no primeiro acesso.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $regras
     */
    private function erro(string $campo, string $valor, array $regras): ?string
    {
        return Validator::make([$campo => $valor], [$campo => $regras])->errors()->first($campo) ?: null;
    }
}
