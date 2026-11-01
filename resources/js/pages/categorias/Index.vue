<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/categorias';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categorias',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    categorias: {
        id: number;
        nome: string;
        tipo: 'despesa' | 'receita';
        lancamentos: number;
    }[];
}>();

const grupos = computed(() => [
    {
        titulo: 'Despesas',
        itens: props.categorias.filter((c) => c.tipo === 'despesa'),
    },
    {
        titulo: 'Receitas',
        itens: props.categorias.filter((c) => c.tipo === 'receita'),
    },
]);

function quantidade(total: number): string {
    return total === 1 ? '1 lançamento' : `${total} lançamentos`;
}
</script>

<template>
    <Head title="Categorias" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Categorias"
                description="Despesa vale para saídas; receita, para entradas."
            />
            <Button as-child>
                <Link :href="create()"><Plus />Nova</Link>
            </Button>
        </div>

        <section v-for="grupo in grupos" :key="grupo.titulo" class="space-y-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ grupo.titulo }}
            </h2>
            <div
                v-if="grupo.itens.length"
                class="divide-y overflow-hidden rounded-xl border"
            >
                <Link
                    v-for="categoria in grupo.itens"
                    :key="categoria.id"
                    :href="edit(categoria.id)"
                    class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
                >
                    <span class="truncate font-medium">{{
                        categoria.nome
                    }}</span>
                    <span class="shrink-0 text-sm text-muted-foreground">
                        {{ quantidade(categoria.lancamentos) }}
                    </span>
                </Link>
            </div>
            <p
                v-else
                class="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
            >
                Nenhuma categoria de {{ grupo.titulo.toLowerCase() }}.
            </p>
        </section>
    </div>
</template>
