<script setup lang="ts">
// <select> nativo de propósito: no celular abre o seletor do próprio sistema,
// mais fácil de usar com o dedo do que uma lista desenhada em JavaScript.
defineOptions({ inheritAttrs: false });

const valor = defineModel<string>();
</script>

<template>
    <select
        v-model="valor"
        v-bind="$attrs"
        class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive md:text-sm dark:bg-input/30 [&>option]:bg-background"
    >
        <slot />
    </select>
</template>
