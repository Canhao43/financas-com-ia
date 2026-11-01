<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import CartaoValor from '@/components/CartaoValor.vue';
import LinhaLancamento from '@/components/LinhaLancamento.vue';
import { Button } from '@/components/ui/button';
import { nomeDoMes } from '@/lib/datas';
import { dashboard } from '@/routes';
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
    entradasDoMes: number;
    saidasDoMes: number;
    ultimos: LancamentoDaLista[];
}>();
</script>

<template>
    <Head title="Painel" />

    <div class="flex flex-1 flex-col gap-4 p-4">
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
            <CartaoValor titulo="Entradas do mês" :centavos="entradasDoMes" />
            <CartaoValor titulo="Saídas do mês" :centavos="saidasDoMes" />
        </div>

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
