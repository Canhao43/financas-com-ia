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

/** Valores em centavos, com sinal; variação em pontos-base (1234 = 12,34%), null sem base. */
export type Comparacao = {
    atual: number;
    anterior: number;
    variacao: number | null;
};

export type MensagemDoAssistente = {
    id: number;
    papel: 'usuario' | 'assistente';
    texto: string;
    situacao: 'respondida' | 'recusada' | 'limite' | 'erro' | null;
    /** O que o Claude consultou para responder, em palavras. */
    consultas: { ferramenta: string; descricao: string; erro: boolean }[];
    /** Valores da resposta que não saíram de nenhuma consulta. */
    valoresSemFonte: string[];
    modelo: string | null;
    chamadas: number | null;
};

export type LinhaDoRelatorio = {
    /** null: os lançamentos sem categoria. */
    categoriaId: number | null;
    nome: string;
    total: number;
    anterior: number;
    variacao: number | null;
    /** Quanto a linha é do total do grupo, em pontos-base. */
    participacao: number;
};
