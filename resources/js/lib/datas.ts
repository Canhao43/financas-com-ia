/**
 * "2026-10-31" para "31/10/2026", sem passar por Date (que aplicaria fuso horário).
 */
export function formatarData(iso: string): string {
    const [ano, mes, dia] = iso.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * "2026-10" para "Outubro de 2026".
 */
export function nomeDoMes(anoMes: string): string {
    const [ano, mes] = anoMes.split('-').map(Number);
    const nome = new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(ano, mes - 1, 1));

    return nome.charAt(0).toUpperCase() + nome.slice(1);
}

/**
 * "2026-09" para "setembro", para frases como "em setembro".
 */
export function mesPorExtenso(anoMes: string): string {
    const [ano, mes] = anoMes.split('-').map(Number);

    return new Intl.DateTimeFormat('pt-BR', { month: 'long' }).format(
        new Date(ano, mes - 1, 1),
    );
}

/**
 * O período do extrato, quando o banco informa: "01/10/2026 a 31/10/2026".
 */
export function formatarPeriodo(
    inicio: string | null,
    fim: string | null,
): string {
    if (!inicio || !fim) {
        return 'período não informado';
    }

    return `${formatarData(inicio)} a ${formatarData(fim)}`;
}
