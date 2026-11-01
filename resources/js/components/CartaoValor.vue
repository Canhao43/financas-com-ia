<script setup lang="ts">
import { corDoValor, formatarReais } from '@/lib/dinheiro';

defineProps<{
    titulo: string;
    centavos: number;
}>();
</script>

<template>
    <div class="rounded-xl border bg-card p-4">
        <p class="text-sm text-muted-foreground">{{ titulo }}</p>
        <p
            class="mt-1 text-lg font-semibold tabular-nums sm:text-xl"
            :class="corDoValor(centavos)"
        >
            {{ formatarReais(centavos) }}
        </p>
    </div>
</template>
