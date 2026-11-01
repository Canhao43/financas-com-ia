<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import AssistenteController from '@/actions/App/Http/Controllers/AssistenteController';
import CaixaDePergunta from '@/components/CaixaDePergunta.vue';
import Heading from '@/components/Heading.vue';
import { formatarData } from '@/lib/datas';
import { index, show } from '@/routes/assistente';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Assistente',
                href: index(),
            },
        ],
    },
});

defineProps<{
    configurado: boolean;
    conversas: { id: number; titulo: string; atualizadaEm: string | null }[];
}>();

const sugestoes = [
    'Quanto gastei este mês?',
    'Em que categoria mais gastei no mês passado?',
    'Gastei mais com mercado este mês ou no anterior?',
    'Qual foi o meu maior gasto nos últimos 3 meses?',
];
</script>

<template>
    <Head title="Assistente" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Assistente"
            description="O Claude responde consultando os seus dados. Todo valor da resposta vem do banco, e o app confere."
        />

        <div
            v-if="!configurado"
            role="alert"
            class="flex gap-3 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-sm"
        >
            <TriangleAlert class="size-5 shrink-0 text-amber-600" />
            <p>
                O assistente está desligado. Para ligar, coloque a chave da API
                da Anthropic em <code>ANTHROPIC_API_KEY</code> no
                <code>.env</code>.
            </p>
        </div>

        <CaixaDePergunta
            :acao="AssistenteController.store.form()"
            :desligada="!configurado"
            :sugestoes="sugestoes"
        />

        <section v-if="conversas.length" class="space-y-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Conversas anteriores
            </h2>
            <div class="divide-y overflow-hidden rounded-xl border">
                <Link
                    v-for="conversa in conversas"
                    :key="conversa.id"
                    :href="show(conversa.id)"
                    class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
                >
                    <span class="truncate text-sm">{{ conversa.titulo }}</span>
                    <span
                        v-if="conversa.atualizadaEm"
                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >{{ formatarData(conversa.atualizadaEm) }}</span
                    >
                </Link>
            </div>
        </section>
    </div>
</template>
