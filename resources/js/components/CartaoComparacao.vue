<script setup lang="ts">
import Variacao from '@/components/Variacao.vue';
import { corDoValor, formatarReais } from '@/lib/dinheiro';

defineProps<{
    titulo: string;
    centavos: number;
    anterior: number;
    /** "setembro", para "em setembro: R$ ...". */
    mesAnterior: string;
    /** Sem variação (o resultado pode trocar de sinal): mostra só o valor anterior. */
    variacao?: number | null;
    subirEhBom?: boolean;
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
        <p
            class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
        >
            <Variacao
                v-if="variacao !== null && variacao !== undefined"
                :pontos-base="variacao"
                :subir-eh-bom="subirEhBom ?? true"
            />
            <!-- O valor não quebra: o sinal de menos sozinho no fim da linha engana. -->
            <span
                >em {{ mesAnterior }}:
                <span class="whitespace-nowrap tabular-nums">{{
                    formatarReais(anterior)
                }}</span></span
            >
        </p>
    </div>
</template>
