<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { computed } from 'vue';
import { formatarPorcentagem } from '@/lib/porcentagem';

// Seta, número e cor dizem a mesma coisa: quem não distingue verde de vermelho lê a seta.
const props = defineProps<{
    pontosBase: number;
    /** Receita subindo é bom; despesa subindo, não. */
    subirEhBom: boolean;
}>();

const cor = computed(() => {
    if (props.pontosBase === 0) {
        return 'text-muted-foreground';
    }

    return props.pontosBase > 0 === props.subirEhBom
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';
});
</script>

<template>
    <span
        class="inline-flex items-center gap-0.5 font-medium tabular-nums"
        :class="cor"
    >
        <template v-if="pontosBase === 0">igual</template>
        <template v-else>
            <ArrowUp v-if="pontosBase > 0" class="size-3" aria-hidden="true" />
            <ArrowDown v-else class="size-3" aria-hidden="true" />
            {{ formatarPorcentagem(Math.abs(pontosBase)) }}
            <span class="sr-only">{{
                pontosBase > 0 ? 'a mais' : 'a menos'
            }}</span>
        </template>
    </span>
</template>
