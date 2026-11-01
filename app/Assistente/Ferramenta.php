<?php

namespace App\Assistente;

/**
 * Uma consulta que o Claude pode pedir. Toda ferramenta só lê: nenhuma grava, apaga ou envia
 * nada. É o que limita o estrago de um texto malicioso vindo de um extrato importado.
 */
interface Ferramenta
{
    public function nome(): string;

    /**
     * A definição no formato do SDK (chaves em camelCase).
     *
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function definicao(): array;

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     *
     * @throws EntradaInvalida
     */
    public function executar(array $entrada): array;

    /**
     * A consulta em palavras, para a tela mostrar de onde veio cada resposta. Recebe a entrada
     * crua do modelo, que pode estar errada: só descreve, não valida.
     *
     * @param  array<string, mixed>  $entrada
     */
    public function descrever(array $entrada): string;
}
