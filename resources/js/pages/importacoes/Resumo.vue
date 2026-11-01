<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import ImportacaoController from '@/actions/App/Http/Controllers/ImportacaoController';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import Heading from '@/components/Heading.vue';
import LinhaLancamento from '@/components/LinhaLancamento.vue';
import { Button } from '@/components/ui/button';
import { formatarData, formatarPeriodo } from '@/lib/datas';
import { formatarReais } from '@/lib/dinheiro';
import { index } from '@/routes/importacoes';
import type { Importacao, LancamentoResumido } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Importações',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    importacao: Importacao;
    lancamentos: (LancamentoResumido & { vinculado: boolean })[];
    conferencia: {
        em: string;
        banco: number;
        app: number;
        diferenca: number;
    } | null;
}>();

const criados = computed(() =>
    props.lancamentos.filter((lancamento) => !lancamento.vinculado),
);
const vinculados = computed(() =>
    props.lancamentos.filter((lancamento) => lancamento.vinculado),
);

const numeros = computed(() => [
    { rotulo: 'Importados', valor: props.importacao.importados },
    { rotulo: 'Vinculados', valor: props.importacao.vinculados },
    { rotulo: 'Já existiam', valor: props.importacao.jaExistiam },
    { rotulo: 'Ignorados', valor: props.importacao.ignorados },
]);

const avisoDoDesfazer = computed(
    () =>
        `Os ${criados.value.length} lançamentos criados por este arquivo serão excluídos, inclusive se você os editou depois. ` +
        `Os ${vinculados.value.length} que você já tinha lançado à mão continuam, só deixam de estar ligados a ele.`,
);
</script>

<template>
    <Head title="Importação" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="`Importação de ${importacao.arquivo}`"
            :description="`${importacao.conta} · ${formatarPeriodo(importacao.inicio, importacao.fim)}`"
        />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div
                v-for="numero in numeros"
                :key="numero.rotulo"
                class="rounded-xl border bg-card p-4"
            >
                <p class="text-sm text-muted-foreground">{{ numero.rotulo }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">
                    {{ numero.valor }}
                </p>
            </div>
        </div>

        <section v-if="conferencia" class="space-y-2 rounded-xl border p-4">
            <h3 class="font-medium">
                Conferência com o banco em {{ formatarData(conferencia.em) }}
            </h3>
            <dl class="grid grid-cols-2 gap-y-1 text-sm">
                <dt class="text-muted-foreground">Saldo no banco</dt>
                <dd class="text-right tabular-nums">
                    {{ formatarReais(conferencia.banco) }}
                </dd>
                <dt class="text-muted-foreground">Saldo no app</dt>
                <dd class="text-right tabular-nums">
                    {{ formatarReais(conferencia.app) }}
                </dd>
            </dl>
            <p
                v-if="conferencia.diferenca === 0"
                class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400"
            >
                <CircleCheck class="size-4" />Confere com o banco.
            </p>
            <p
                v-else
                class="flex gap-2 text-sm text-amber-700 dark:text-amber-400"
            >
                <TriangleAlert class="size-4 shrink-0" />
                Diferença de {{ formatarReais(conferencia.diferenca) }}. Pode
                ser o saldo inicial da conta, um lançamento que falta ou um que
                sobrou.
            </p>
        </section>

        <section v-if="criados.length" class="space-y-2">
            <h3 class="font-medium">Criados por esta importação</h3>
            <div class="divide-y overflow-hidden rounded-xl border">
                <LinhaLancamento
                    v-for="lancamento in criados"
                    :key="lancamento.id"
                    :lancamento="lancamento"
                />
            </div>
        </section>

        <section v-if="vinculados.length" class="space-y-2">
            <h3 class="font-medium">
                Vinculados a lançamentos que você já tinha
            </h3>
            <div class="divide-y overflow-hidden rounded-xl border">
                <LinhaLancamento
                    v-for="lancamento in vinculados"
                    :key="lancamento.id"
                    :lancamento="lancamento"
                />
            </div>
        </section>

        <div
            class="flex flex-wrap items-center justify-between gap-2 border-t pt-6"
        >
            <Button variant="ghost" as-child>
                <Link :href="index()">Voltar às importações</Link>
            </Button>
            <ConfirmDelete
                :acao="ImportacaoController.destroy.form(importacao.id)"
                titulo="Desfazer importação"
                rotulo="Desfazer importação"
                :descricao="avisoDoDesfazer"
            />
        </div>
    </div>
</template>
