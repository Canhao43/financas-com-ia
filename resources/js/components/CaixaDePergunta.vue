<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { LoaderCircle, Send } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    acao: { action: string; method: 'post' };
    desligada?: boolean;
    sugestoes?: string[];
}>();

const pergunta = ref('');

// Ctrl+Enter (ou Cmd+Enter) envia; Enter sozinho quebra a linha, como no celular.
function enviarComAtalho(evento: KeyboardEvent) {
    if (evento.ctrlKey || evento.metaKey) {
        (evento.target as HTMLTextAreaElement).form?.requestSubmit();
    }
}
</script>

<template>
    <Form
        v-bind="acao"
        reset-on-success
        class="space-y-2"
        v-slot="{ errors, processing }"
        @success="pergunta = ''"
    >
        <div v-if="sugestoes?.length && !pergunta" class="flex flex-wrap gap-2">
            <button
                v-for="sugestao in sugestoes"
                :key="sugestao"
                type="button"
                class="rounded-full border px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted"
                :disabled="desligada"
                @click="pergunta = sugestao"
            >
                {{ sugestao }}
            </button>
        </div>

        <div class="flex items-end gap-2">
            <textarea
                v-model="pergunta"
                name="pergunta"
                rows="2"
                maxlength="500"
                required
                :disabled="desligada || processing"
                placeholder="Pergunte sobre os seus gastos"
                aria-label="Pergunta"
                class="min-h-16 flex-1 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 md:text-sm"
                @keydown.enter="enviarComAtalho"
            />
            <Button
                :disabled="desligada || processing || !pergunta.trim()"
                size="icon"
                class="size-10"
                aria-label="Perguntar"
            >
                <LoaderCircle v-if="processing" class="animate-spin" />
                <Send v-else />
            </Button>
        </div>

        <p v-if="processing" class="text-xs text-muted-foreground">
            Consultando os seus dados. Pode levar alguns segundos.
        </p>
        <InputError :message="errors.pergunta" />
    </Form>
</template>
