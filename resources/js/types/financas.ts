export type Direcao = 'entrada' | 'saida';

export type TipoCategoria = 'despesa' | 'receita' | 'transferencia';

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

export type LancamentoResumido = {
    id: number;
    data: string;
    descricao: string;
    valor: number;
    categoria: string | null;
};

export type Importacao = {
    id: number;
    status: 'pendente' | 'concluida';
    arquivo: string;
    conta: string;
    contaId: number;
    inicio: string | null;
    fim: string | null;
    criadaEm: string | null;
    importados: number;
    vinculados: number;
    jaExistiam: number;
    ignorados: number;
};

export type SituacaoDaLinha = 'nova' | 'ja_existe' | 'parecida';

export type LinhaDaPrevia = {
    indice: number;
    data: string;
    descricao: string;
    valor: number;
    situacao: SituacaoDaLinha;
    /** A categoria que as regras dão, se a linha for importada como nova. */
    categoria: string | null;
    parecidoCom: LancamentoResumido | null;
};
