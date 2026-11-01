<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import ImportacaoController from '@/actions/App/Http/Controllers/ImportacaoController';
import EscolhaSegmentada from '@/components/EscolhaSegmentada.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatarData, formatarPeriodo } from '@/lib/datas';
import { corDoValor, formatarReais } from '@/lib/dinheiro';
import { confirmar, index } from '@/routes/importacoes';
import type { Importacao, LinhaDaPrevia } from '@/types';

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
    contaDiferente: { arquivo: string; anterior: string } | null;
    linhas: LinhaDaPrevia[];
}>();

const novas = computed(() =>
    props.linhas.filter((linha) => linha.situacao === 'nova'),
);
const parecidas = computed(() =>
    props.linhas.filter((linha) => linha.situacao === 'parecida'),
);
const jaExistem = computed(() =>
    props.linhas.filter((linha) => linha.situacao === 'ja_existe'),
);

// Uma decisão por linha, no formato que o servidor espera. O padrão é o gasto entrar uma vez:
// a linha nova é importada e a parecida é vinculada ao lançamento que você já tinha.
const form = useForm({
    decisoes: Object.fromEntries([
        ...novas.value.map((linha) => [linha.indice, 'importar']),
        ...parecidas.value.map((linha) => [linha.indice, 'vincular']),
    ]) as Record<number, string>,
});

const escolhasDaParecida = [
    { valor: 'vincular', rotulo: 'É o mesmo' },
    { valor: 'importar', rotulo: 'Importar também' },
];

function quantas(decisao: string): number {
    return Object.values(form.decisoes).filter((valor) => valor === decisao)
        .length;
}

function alternar(indice: number, evento: Event) {
    form.decisoes[indice] = (evento.target as HTMLInputElement).checked
        ? 'importar'
        : 'ignorar';
}

function confirmarImportacao() {
    form.post(confirmar.url(props.importacao.id));
}
</script>

<template>
    <Head title="Prévia da importação" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 pb-0">
        <Heading
            :title="`Prévia de ${importacao.arquivo}`"
            :description="`${importacao.conta} · ${formatarPeriodo(importacao.inicio, importacao.fim)}`"
        />

        <div
            v-if="contaDiferente"
            role="alert"
            class="flex gap-3 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-sm"
        >
            <TriangleAlert class="size-5 shrink-0 text-amber-600" />
            <p>
                Este arquivo é da conta de final
                <strong>{{ contaDiferente.arquivo }}</strong
                >, mas as importações anteriores em “{{ importacao.conta }}”
                vieram da conta de final
                <strong>{{ contaDiferente.anterior }}</strong
                >. Confira se escolheu a conta certa antes de confirmar.
            </p>
        </div>

        <section v-if="novas.length" class="space-y-2">
            <h3 class="font-medium">Novos ({{ novas.length }})</h3>
            <div class="divide-y overflow-hidden rounded-xl border">
                <label
                    v-for="linha in novas"
                    :key="linha.indice"
                    class="flex cursor-pointer items-center gap-3 px-4 py-3 has-[:not(:checked)]:opacity-60"
                >
                    <input
                        type="checkbox"
                        class="size-5 shrink-0 accent-primary"
                        :checked="form.decisoes[linha.indice] === 'importar'"
                        @change="alternar(linha.indice, $event)"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">
                            {{ linha.descricao }}
                        </span>
                        <span class="block text-sm text-muted-foreground">
                            {{ formatarData(linha.data) }} ·
                            {{ linha.categoria ?? 'Sem categoria' }}
                        </span>
                    </span>
                    <span
                        class="shrink-0 font-medium tabular-nums"
                        :class="corDoValor(linha.valor)"
                    >
                        {{ formatarReais(linha.valor) }}
                    </span>
                </label>
            </div>
        </section>

        <section v-if="parecidas.length" class="space-y-2">
            <h3 class="font-medium">
                Parecem já lançados por você ({{ parecidas.length }})
            </h3>
            <p class="text-sm text-muted-foreground">
                Mesmo valor e data próxima de um lançamento que você fez à mão.
                Se for o mesmo, ele fica como você escreveu e só passa a ser
                reconhecido nas próximas importações.
            </p>
            <div
                v-for="linha in parecidas"
                :key="linha.indice"
                class="space-y-3 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{ linha.descricao }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            No banco · {{ formatarData(linha.data) }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 font-medium tabular-nums"
                        :class="corDoValor(linha.valor)"
                    >
                        {{ formatarReais(linha.valor) }}
                    </span>
                </div>
                <p
                    v-if="linha.parecidoCom"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm"
                >
                    Seu lançamento:
                    <strong>{{ linha.parecidoCom.descricao }}</strong>
                    · {{ formatarData(linha.parecidoCom.data) }} ·
                    {{ linha.parecidoCom.categoria ?? 'Sem categoria' }}
                </p>
                <EscolhaSegmentada
                    v-model="form.decisoes[linha.indice]"
                    :name="`parecida-${linha.indice}`"
                    :opcoes="escolhasDaParecida"
                />
            </div>
        </section>

        <details v-if="jaExistem.length" class="rounded-xl border p-4">
            <summary class="cursor-pointer font-medium">
                Já estão no app ({{ jaExistem.length }})
            </summary>
            <p class="mt-2 text-sm text-muted-foreground">
                Vieram em uma importação anterior e não entram de novo.
            </p>
            <ul class="mt-3 divide-y text-sm">
                <li
                    v-for="linha in jaExistem"
                    :key="linha.indice"
                    class="flex justify-between gap-4 py-2"
                >
                    <span class="min-w-0 truncate">
                        {{ formatarData(linha.data) }} · {{ linha.descricao }}
                    </span>
                    <span class="shrink-0 tabular-nums">
                        {{ formatarReais(linha.valor) }}
                    </span>
                </li>
            </ul>
        </details>

        <!-- Presa ao pé da tela enquanto a lista rola: o botão de confirmar fica sempre à mão no celular. -->
        <div
            class="sticky bottom-0 -mx-4 mt-auto border-t bg-background/95 p-4 backdrop-blur"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    {{ quantas('importar') }} para importar ·
                    {{ quantas('vincular') }} para vincular ·
                    {{ quantas('ignorar') }} ignorados
                </p>
                <div class="flex gap-2">
                    <Form
                        v-bind="
                            ImportacaoController.destroy.form(importacao.id)
                        "
                    >
                        <Button type="submit" variant="ghost">Descartar</Button>
                    </Form>
                    <Button
                        :disabled="form.processing"
                        @click="confirmarImportacao"
                    >
                        Confirmar importação
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
