<?php

namespace App\Assistente;

use Anthropic\Beta\Messages\BetaMessage;

/**
 * Quem respondeu e quanto custou, somado por todas as chamadas de uma resposta. Fica gravado
 * junto dela, para decidir o modelo pelo uso medido, e não por estimativa.
 */
final class Consumo
{
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
