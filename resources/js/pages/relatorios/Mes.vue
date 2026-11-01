<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import CartaoComparacao from '@/components/CartaoComparacao.vue';
import { Button } from '@/components/ui/button';
import Variacao from '@/components/Variacao.vue';
import { mesPorExtenso, nomeDoMes } from '@/lib/datas';
import { formatarReais } from '@/lib/dinheiro';
import { formatarPorcentagem } from '@/lib/porcentagem';
import { relatorios } from '@/routes';
import { index as lancamentos } from '@/routes/lancamentos';
import type { Comparacao, LinhaDoRelatorio } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Relatórios',
                href: relatorios(),
            },
        ],
    },
});

// Tudo chega calculado do servidor (pontos-base, centavos): aqui só se formata.
const props = defineProps<{
    mes: string;
    mesAnterior: string;
    mesSeguinte: string;
    receitas: Comparacao;
    despesas: Comparacao;
    resultado: { atual: number; anterior: number };
    transferencias: number;
    despesasPorCategoria: LinhaDoRelatorio[];
    receitasPorCategoria: LinhaDoRelatorio[];
}>();

const anterior = computed(() => mesPorExtenso(props.mesAnterior));

const secoes = computed(() => [
    {
        titulo: 'Despesas por categoria',
        linhas: props.despesasPorCategoria,
        subirEhBom: false,
    },
    {
        titulo: 'Receitas por categoria',
        linhas: props.receitasPorCategoria,
        subirEhBom: true,
    },
]);

const vazio = computed(
    () =>
        !props.despesasPorCategoria.length &&
        !props.receitasPorCategoria.length,
);

function doMes(mes: string) {
    return relatorios({ query: { mes } });
}

// Cada linha abre os lançamentos que formam o número: o total do relatório é conferível.
function lancamentosDa(linha: LinhaDoRelatorio) {
    return lancamentos({
        query: { mes: props.mes, categoria: linha.categoriaId ?? 'sem' },
    });
}
</script>

<template>
    <Head :title="`Relatório de ${nomeDoMes(mes).toLowerCase()}`" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="doMes(mesAnterior)" aria-label="Mês anterior">
                    <ChevronLeft />
                </Link>
            </Button>
            <h1 class="min-w-44 text-center text-lg font-semibold">
                {{ nomeDoMes(mes) }}
            </h1>
            <Button variant="ghost" size="icon" as-child>
                <Link :href="doMes(mesSeguinte)" aria-label="Mês seguinte">
                    <ChevronRight />
                </Link>
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <CartaoComparacao
                titulo="Receitas"
                :centavos="receitas.atual"
                :anterior="receitas.anterior"
                :variacao="receitas.variacao"
                :mes-anterior="anterior"
                subir-eh-bom
            />
            <CartaoComparacao
                titulo="Despesas"
                :centavos="despesas.atual"
                :anterior="despesas.anterior"
                :variacao="despesas.variacao"
                :mes-anterior="anterior"
                :subir-eh-bom="false"
            />
            <CartaoComparacao
                class="col-span-2 sm:col-span-1"
                titulo="Resultado"
                :centavos="resultado.atual"
                :anterior="resultado.anterior"
                :mes-anterior="anterior"
            />
        </div>

        <p v-if="transferencias" class="text-sm text-muted-foreground">
            {{ formatarReais(transferencias) }} passaram de uma conta sua para
            outra (como o pagamento da fatura do cartão) e ficam fora dos
            totais.
        </p>

        <p
            v-if="vazio"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nada lançado em {{ nomeDoMes(mes).toLowerCase() }} nem em
            {{ anterior }}.
        </p>

        <template v-for="secao in secoes" :key="secao.titulo">
            <section v-if="secao.linhas.length" class="space-y-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    {{ secao.titulo }}
                </h2>
                <div class="divide-y overflow-hidden rounded-xl border">
                    <Link
                        v-for="linha in secao.linhas"
                        :key="linha.categoriaId ?? 'sem'"
                        :href="lancamentosDa(linha)"
                        class="block space-y-1.5 px-4 py-3 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="truncate font-medium">{{
                                linha.nome
                            }}</span>
                            <span class="shrink-0 font-medium tabular-nums">{{
                                formatarReais(linha.total)
                            }}</span>
                        </div>
                        <!-- Uma cor só, do maior para o menor: a barra compara, os números contam. -->
                        <div class="flex items-center gap-3">
                            <div
                                class="h-2 flex-1 rounded-r-sm bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-r-sm bg-primary"
                                    :style="{
                                        width: `${linha.participacao / 100}%`,
                                    }"
                                />
                            </div>
                            <span
                                class="w-12 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                                >{{
                                    formatarPorcentagem(linha.participacao)
                                }}</span
                            >
                        </div>
                        <p
                            class="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                        >
                            <Variacao
                                v-if="linha.variacao !== null"
                                :pontos-base="linha.variacao"
                                :subir-eh-bom="secao.subirEhBom"
                            />
                            <span v-if="linha.anterior === 0"
                                >nada em {{ anterior }}</span
                            >
                            <span v-else
                                >em {{ anterior }}:
                                <span class="whitespace-nowrap tabular-nums">{{
                                    formatarReais(linha.anterior)
                                }}</span></span
                            >
                        </p>
                    </Link>
                </div>
            </section>
        </template>
    </div>
</template>
