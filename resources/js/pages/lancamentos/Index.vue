<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import CartaoValor from '@/components/CartaoValor.vue';
import LinhaLancamento from '@/components/LinhaLancamento.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { nomeDoMes } from '@/lib/datas';
import { create, index } from '@/routes/lancamentos';
import type { LancamentoDaLista, Opcao } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lançamentos',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    mes: string;
    mesAnterior: string;
    mesSeguinte: string;
    contaId: number | null;
    contas: Opcao[];
    lancamentos: LancamentoDaLista[];
    totais: { entradas: number; saidas: number; saldo: number };
}>();

const conta = ref(props.contaId ? String(props.contaId) : '');

// Trocar de mês mantém o filtro de conta.
function doMes(mes: string) {
    return index({ query: { mes, conta: props.contaId ?? undefined } });
}

watch(conta, (escolhida) => {
    router.get(
        index.url({ query: { mes: props.mes, conta: escolhida || undefined } }),
        {},
        { preserveScroll: true },
    );
});
</script>

<template>
    <Head title="Lançamentos" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
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

            <div class="flex w-full gap-2 sm:w-auto">
                <NativeSelect
                    v-model="conta"
                    aria-label="Filtrar por conta"
                    class="sm:w-48"
                >
                    <option value="">Todas as contas</option>
                    <option
                        v-for="opcao in contas"
                        :key="opcao.id"
                        :value="String(opcao.id)"
                    >
                        {{ opcao.nome }}
                    </option>
                </NativeSelect>
                <Button as-child>
                    <Link :href="create()"><Plus />Novo</Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <CartaoValor titulo="Entradas" :centavos="totais.entradas" />
            <CartaoValor titulo="Saídas" :centavos="totais.saidas" />
            <CartaoValor
                class="col-span-2 sm:col-span-1"
                titulo="Saldo do mês"
                :centavos="totais.saldo"
            />
        </div>

        <div
            v-if="lancamentos.length"
            class="divide-y overflow-hidden rounded-xl border"
        >
            <LinhaLancamento
                v-for="lancamento in lancamentos"
                :key="lancamento.id"
                :lancamento="lancamento"
            />
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nenhum lançamento em {{ nomeDoMes(mes).toLowerCase() }}.
        </p>
    </div>
</template>
