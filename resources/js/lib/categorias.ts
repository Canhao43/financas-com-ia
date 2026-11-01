import type { TipoCategoria } from '@/types';

export const tiposDeCategoria: {
    valor: TipoCategoria;
    rotulo: string;
    plural: string;
}[] = [
    { valor: 'despesa', rotulo: 'Despesa', plural: 'Despesas' },
    { valor: 'receita', rotulo: 'Receita', plural: 'Receitas' },
    {
        valor: 'transferencia',
        rotulo: 'Transferência',
        plural: 'Transferências',
    },
];

export function rotuloDoTipo(tipo: TipoCategoria): string {
    return tiposDeCategoria.find((t) => t.valor === tipo)?.rotulo ?? tipo;
}
