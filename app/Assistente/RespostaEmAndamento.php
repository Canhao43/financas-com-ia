<?php

namespace App\Assistente;

use Anthropic\Beta\Messages\BetaMessage;

/**
 * O que vai sendo juntado enquanto o Claude consulta: as consultas, os valores que elas
 * devolveram e quanto as chamadas custaram.
 */
final class RespostaEmAndamento
{
    /** @var list<array{ferramenta: string, descricao: string, erro: bool}> */
    public array $consultas = [];

    /** @var list<string> formas normalizadas, como a ConferenciaDeValores compara */
    public array $valores = [];

    /** @var list<string> */
    public array $semFonte = [];

    public ?string $modelo = null;

    /** @var array{entrada: int, saida: int, cache_lida: int, cache_criada: int, chamadas: int} */
    public array $uso = ['entrada' => 0, 'saida' => 0, 'cache_lida' => 0, 'cache_criada' => 0, 'chamadas' => 0];

    public function contar(BetaMessage $mensagem): void
    {
        // O modelo que respondeu de fato: se houve troca por recusa, é o substituto.
        $this->modelo = $mensagem->model;
        $this->uso['entrada'] += $mensagem->usage->inputTokens;
        $this->uso['saida'] += $mensagem->usage->outputTokens;
        $this->uso['cache_lida'] += $mensagem->usage->cacheReadInputTokens ?? 0;
        $this->uso['cache_criada'] += $mensagem->usage->cacheCreationInputTokens ?? 0;
        $this->uso['chamadas']++;
    }
}
