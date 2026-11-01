<?php

namespace App\Assistente;

/**
 * O que volta para o Claude como resultado de uma ferramenta.
 */
final readonly class ResultadoDaFerramenta
{
    private function __construct(
        public string $conteudo,
        public bool $erro,
    ) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function ok(array $dados): self
    {
        return new self((string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), false);
    }

    public static function erro(string $mensagem): self
    {
        return new self($mensagem, true);
    }
}
