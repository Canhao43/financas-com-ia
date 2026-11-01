<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatarData, formatarPeriodo } from '@/lib/datas';
import { create, index, show } from '@/routes/importacoes';
import type { Importacao } from '@/types';

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

defineProps<{
    importacoes: Importacao[];
}>();
</script>

<template>
    <Head title="Importações" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Importações"
                description="Cada extrato importado, com o que entrou. Dá para desfazer."
            />
            <Button as-child>
                <Link :href="create()"><Upload />Importar</Link>
            </Button>
        </div>

        <div
            v-if="importacoes.length"
            class="divide-y overflow-hidden rounded-xl border"
        >
            <Link
                v-for="importacao in importacoes"
                :key="importacao.id"
                :href="show(importacao.id)"
                class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
            >
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-medium">
                        <span class="truncate">{{ importacao.arquivo }}</span>
                        <Badge
                            v-if="importacao.status === 'pendente'"
                            variant="outline"
                        >
                            Prévia não confirmada
                        </Badge>
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ importacao.conta }} ·
                        {{ formatarPeriodo(importacao.inicio, importacao.fim) }}
                    </p>
                </div>
                <div
                    v-if="importacao.status === 'concluida'"
                    class="shrink-0 text-right text-sm"
                >
                    <p class="font-medium tabular-nums">
                        {{ importacao.importados }} novos
                    </p>
                    <p class="text-muted-foreground">
                        {{
                            importacao.criadaEm
                                ? formatarData(importacao.criadaEm)
                                : ''
                        }}
                    </p>
                </div>
            </Link>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nenhuma importação ainda. Exporte o extrato do banco em OFX e
            importe aqui.
        </p>
    </div>
</template>
