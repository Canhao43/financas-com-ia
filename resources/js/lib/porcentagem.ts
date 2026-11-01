const porcentagem = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

/**
 * Pontos-base (como vem do servidor: 2534 = 25,34%) para "25,3%". Só exibe: a conta é do
 * servidor.
 */
export function formatarPorcentagem(pontosBase: number): string {
    return `${porcentagem.format(pontosBase / 100)}%`;
}
