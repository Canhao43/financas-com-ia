const reais = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

/**
 * Centavos (inteiro, como vem do servidor) para "R$ 1.234,56".
 * Só exibe: soma e total são calculados no servidor.
 */
export function formatarReais(centavos: number): string {
    return reais.format(centavos / 100);
}

/**
 * Cor pelo sinal: entrada verde, saída vermelha, zero neutro.
 */
export function corDoValor(centavos: number): string {
    if (centavos > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (centavos < 0) {
        return 'text-rose-600 dark:text-rose-400';
    }

    return 'text-muted-foreground';
}
