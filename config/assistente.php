<?php

return [

    // A chave é da conta da Anthropic do dono do app: sem ela, o assistente fica desligado.
    'chave' => env('ANTHROPIC_API_KEY'),

    // Trocar por um modelo mais barato é uma linha do .env (ex.: claude-haiku-5-5).
    'modelo' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),

    // Quanto o modelo pensa antes de responder. "medium" é o padrão do Opus 5.5, escrito aqui
    // para não mudar sozinho se o padrão da API mudar.
    'esforco' => env('ANTHROPIC_EFFORT', 'medium'),

    'max_tokens' => 16000,

    // Chamadas ao Claude por pergunta (cada rodada pode pedir várias consultas). Um teto para
    // uma pergunta estranha não virar uma conta grande.
    'max_rodadas' => 8,

    // Quantas perguntas anteriores da conversa vão junto, para "e em setembro?" funcionar.
    'perguntas_anteriores' => 6,

    // Na análise do mês, quantas vezes o Claude reescreve se citar valor que não está nos
    // dados. Uma chance basta para corrigir um deslize; se persistir, a tela mostra o aviso.
    'correcoes_da_analise' => 1,

    // Segundos por chamada à API, contando as novas tentativas automáticas do SDK.
    'timeout' => 60,

];
