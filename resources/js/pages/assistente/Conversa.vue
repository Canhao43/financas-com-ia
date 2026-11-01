<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { nextTick, onMounted, watch } from 'vue';
import AssistenteController from '@/actions/App/Http/Controllers/AssistenteController';
import CaixaDePergunta from '@/components/CaixaDePergunta.vue';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import RespostaDoAssistente from '@/components/RespostaDoAssistente.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/assistente';
import type { MensagemDoAssistente } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Assistente',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    configurado: boolean;
    conversa: { id: number; titulo: string };
    mensagens: MensagemDoAssistente[];
}>();

// A resposta nova aparece no fim: a tela desce até ela.
function irParaOFim() {
    nextTick(() => window.scrollTo({ top: document.body.scrollHeight }));
}

onMounted(irParaOFim);
watch(() => props.mensagens.length, irParaOFim);
</script>

<template>
    <Head :title="conversa.titulo" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <h1 class="text-base font-semibold">{{ conversa.titulo }}</h1>
            <Button variant="outline" size="sm" as-child>
                <Link :href="index()"><Plus />Nova</Link>
            </Button>
        </div>

        <div class="flex flex-1 flex-col gap-4">
            <template v-for="mensagem in mensagens" :key="mensagem.id">
                <p
                    v-if="mensagem.papel === 'usuario'"
                    class="max-w-[85%] self-end rounded-2xl rounded-br-sm bg-muted px-4 py-2 text-sm whitespace-pre-line"
                >
                    {{ mensagem.texto }}
                </p>
                <div v-else class="max-w-[95%] self-start">
                    <RespostaDoAssistente :mensagem="mensagem" />
                </div>
            </template>
        </div>

        <div
            class="sticky bottom-0 -mx-4 border-t bg-background px-4 pt-3 pb-4"
        >
            <CaixaDePergunta
                :acao="AssistenteController.perguntar.form(conversa.id)"
                :desligada="!configurado"
            />
        </div>

        <div class="border-t pt-4">
            <ConfirmDelete
                :acao="AssistenteController.destroy.form(conversa.id)"
                titulo="Apagar conversa"
                descricao="A conversa e as respostas somem. Os seus lançamentos não mudam."
                rotulo="Apagar conversa"
            />
        </div>
    </div>
</template>
