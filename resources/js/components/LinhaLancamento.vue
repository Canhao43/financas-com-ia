<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { formatarData } from '@/lib/datas';
import { corDoValor, formatarReais } from '@/lib/dinheiro';
import { edit } from '@/routes/lancamentos';
import type { LancamentoDaLista } from '@/types';

defineProps<{
    lancamento: LancamentoDaLista;
}>();
</script>

<template>
    <Link
        :href="edit(lancamento.id)"
        class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
    >
        <div class="min-w-0">
            <p class="truncate font-medium">{{ lancamento.descricao }}</p>
            <p class="truncate text-sm text-muted-foreground">
                {{ formatarData(lancamento.data) }} ·
                {{ lancamento.categoria ?? 'Sem categoria' }}
                <template v-if="lancamento.conta">
                    · {{ lancamento.conta }}
                </template>
            </p>
        </div>
        <span
            class="shrink-0 font-medium tabular-nums"
            :class="corDoValor(lancamento.valor)"
        >
            {{ formatarReais(lancamento.valor) }}
        </span>
    </Link>
</template>
