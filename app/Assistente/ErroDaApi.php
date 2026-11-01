<?php

namespace App\Assistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * O erro da API do jeito que a pessoa precisa ler: o que aconteceu e o que fazer. Só o erro
 * inesperado vai para o log; chave errada, limite de uso e rede caída se explicam sozinhos.
 */
final class ErroDaApi
{
    public static function mensagem(APIException $erro): string
    {
        return match (true) {
            $erro instanceof AuthenticationException => 'A chave da API da Anthropic foi recusada. Confira ANTHROPIC_API_KEY no .env.',
            $erro instanceof RateLimitException => 'O limite de uso da API foi atingido. Tente de novo em alguns minutos.',
            $erro instanceof APIConnectionException => 'Não foi possível falar com o Claude (sem conexão ou demorou demais). Tente de novo.',
            default => self::inesperado($erro),
        };
    }

    private static function inesperado(APIException $erro): string
    {
        report($erro);

        return 'O Claude não respondeu agora. Tente de novo em instantes.';
    }
}
