<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, TriangleAlert } from '@lucide/vue';
import CartaoValor from '@/components/CartaoValor.vue';
import LinhaLancamento from '@/components/LinhaLancamento.vue';
import { Button } from '@/components/ui/button';
import { nomeDoMes } from '@/lib/datas';
import { dashboard, relatorios } from '@/routes';
import { create as novaConta } from '@/routes/contas';
import { create, index } from '@/routes/lancamentos';
import type { LancamentoDaLista } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Painel',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    mes: string;
    saldoTotal: number;
    receitasDoMes: number;
    despesasDoMes: number;
    ultimos: LancamentoDaLista[];
    avisoDeBackup: string | null;
}>();
</script>

<template>
    <Head title="Painel" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div
            v-if="avisoDeBackup"
            role="alert"
            class="flex gap-3 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-sm"
        >
            <TriangleAlert class="size-5 shrink-0 text-amber-600" />
            <p>
                <strong>Backup atrasado.</strong> {{ avisoDeBackup }} O que foi
                lançado depois disso ainda não tem cópia fora do servidor.
            </p>
        </div>

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ nomeDoMes(mes) }}</h1>
            <Button as-child>
                <Link :href="create()"><Plus />Novo lançamento</Link>
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <CartaoValor
                class="col-span-2 sm:col-span-1"
                titulo="Saldo das contas"
                :centavos="saldoTotal"
            />
            <CartaoValor titulo="Receitas do mês" :centavos="receitasDoMes" />
            <CartaoValor titulo="Despesas do mês" :centavos="despesasDoMes" />
        </div>

        <Button variant="link" size="sm" class="self-start px-0" as-child>
            <Link :href="relatorios({ query: { mes } })"
                >Ver o relatório do mês, por categoria</Link
            >
        </Button>

        <section class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Últimos lançamentos
                </h2>
                <Button variant="link" size="sm" class="px-0" as-child>
                    <Link :href="index()">Ver todos</Link>
                </Button>
            </div>

            <div
                v-if="ultimos.length"
                class="divide-y overflow-hidden rounded-xl border"
            >
                <LinhaLancamento
                    v-for="lancamento in ultimos"
                    :key="lancamento.id"
                    :lancamento="lancamento"
                />
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                <p>Nada lançado ainda. Comece pelas contas.</p>
                <Button class="mt-4" variant="outline" as-child>
                    <Link :href="novaConta()">Cadastrar conta</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
