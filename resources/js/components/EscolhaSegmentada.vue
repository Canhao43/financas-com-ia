<script setup lang="ts">
// Rádios nativos com cara de botão: dois toques no celular, e o valor vai no submit do formulário.
defineProps<{
    name?: string;
    opcoes: { valor: string; rotulo: string }[];
    disabled?: boolean;
}>();

const valor = defineModel<string>({ required: true });
</script>

<template>
    <div
        class="grid gap-2"
        :class="opcoes.length === 3 ? 'grid-cols-3' : 'grid-cols-2'"
    >
        <label
            v-for="opcao in opcoes"
            :key="opcao.valor"
            class="flex h-9 min-w-0 cursor-pointer items-center justify-center truncate rounded-md border border-input px-1 text-sm font-medium transition-colors has-checked:border-primary has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50 has-disabled:cursor-not-allowed has-disabled:opacity-50"
        >
            <input
                v-model="valor"
                type="radio"
                class="sr-only"
                :name="name"
                :value="opcao.valor"
                :disabled="disabled"
            />
            {{ opcao.rotulo }}
        </label>
    </div>
</template>
