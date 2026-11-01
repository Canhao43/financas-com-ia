export type Direcao = 'entrada' | 'saida';

export type Opcao = {
    id: number;
    nome: string;
};

export type LancamentoDaLista = {
    id: number;
    data: string;
    descricao: string;
    valor: number;
    categoria: string | null;
    conta?: string;
};
