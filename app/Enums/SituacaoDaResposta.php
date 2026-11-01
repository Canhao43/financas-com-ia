<?php

namespace App\Enums;

enum SituacaoDaResposta: string
{
    case Respondida = 'respondida';
    // O Claude declinou por política (stop_reason "refusal"), mesmo depois da troca de modelo.
    case Recusada = 'recusada';
    // Consultas demais para uma pergunta: parou antes de gastar sem fim.
    case Limite = 'limite';
    // A API não respondeu: chave, limite de uso, rede ou servidor.
    case Erro = 'erro';
}
