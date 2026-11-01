<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import RegraController from '@/actions/App/Http/Controllers/RegraController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/regras';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Regras',
                href: index(),
            },
        ],
    },
});

defineProps<{
    regras: {
        id: number;
        texto: string;
        categoria: string;
        tipo: 'despesa' | 'receita';
    }[];
    semCategoria: number;
}>();
</script>

<template>
    <Head title="Regras" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Regras de categoria"
                description="Na importação, a descrição que contém o texto recebe a categoria."
            />
            <Button as-child>
                <Link :href="create()"><Plus />Nova</Link>
            </Button>
        </div>

        <div
            v-if="regras.length && semCategoria"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4"
        >
            <p class="text-sm">
                {{ semCategoria }}
                {{
                    semCategoria === 1
                        ? 'lançamento está sem categoria.'
                        : 'lançamentos estão sem categoria.'
                }}
                <span class="text-muted-foreground">
                    Os que já têm categoria não mudam.
                </span>
            </p>
            <Form
                v-bind="RegraController.aplicar.form()"
                #default="{ processing }"
            >
                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="processing"
                >
                    Aplicar as regras
                </Button>
            </Form>
        </div>

        <div
            v-if="regras.length"
            class="divide-y overflow-hidden rounded-xl border"
        >
            <Link
                v-for="regra in regras"
                :key="regra.id"
                :href="edit(regra.id)"
                class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
            >
                <span class="min-w-0 truncate font-mono text-sm">
                    {{ regra.texto }}
                </span>
                <span class="flex shrink-0 items-center gap-2 text-sm">
                    {{ regra.categoria }}
                    <Badge variant="outline">
                        {{ regra.tipo === 'receita' ? 'Receita' : 'Despesa' }}
                    </Badge>
                </span>
            </Link>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nenhuma regra ainda. Crie uma para cada descrição que se repete no
            extrato — "IFOOD" em Alimentação fora, "UBER" em Transporte.
        </p>
    </div>
</template>
