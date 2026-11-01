<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { corDoValor, formatarReais } from '@/lib/dinheiro';
import { create, edit, index } from '@/routes/contas';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contas',
                href: index(),
            },
        ],
    },
});

defineProps<{
    contas: {
        id: number;
        nome: string;
        tipo: string;
        arquivada: boolean;
        saldo: number;
    }[];
}>();
</script>

<template>
    <Head title="Contas" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Contas"
                description="Saldo atual: o saldo inicial mais todos os lançamentos."
            />
            <Button as-child>
                <Link :href="create()"><Plus />Nova</Link>
            </Button>
        </div>

        <div
            v-if="contas.length"
            class="divide-y overflow-hidden rounded-xl border"
        >
            <Link
                v-for="conta in contas"
                :key="conta.id"
                :href="edit(conta.id)"
                class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
                :class="{ 'opacity-60': conta.arquivada }"
            >
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-medium">
                        <span class="truncate">{{ conta.nome }}</span>
                        <Badge v-if="conta.arquivada" variant="outline">
                            Arquivada
                        </Badge>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ conta.tipo }}
                    </p>
                </div>
                <span
                    class="shrink-0 font-medium tabular-nums"
                    :class="corDoValor(conta.saldo)"
                >
                    {{ formatarReais(conta.saldo) }}
                </span>
            </Link>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nenhuma conta ainda. Cadastre a conta corrente, o cartão ou a
            carteira de onde o dinheiro sai.
        </p>
    </div>
</template>
