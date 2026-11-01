<script setup lang="ts">
import { CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { MensagemDoAssistente } from '@/types';

const props = defineProps<{ mensagem: MensagemDoAssistente }>();

const respondida = computed(() => props.mensagem.situacao === 'respondida');
const consultasFeitas = computed(() =>
    props.mensagem.consultas.filter((consulta) => !consulta.erro),
);
</script>

<template>
    <div class="space-y-2">
        <!-- Texto simples: a resposta nunca vira HTML, mesmo que traga algo parecido. -->
        <p
            class="text-sm leading-relaxed whitespace-pre-line"
            :class="{ 'text-muted-foreground': !respondida }"
        >
            {{ mensagem.texto }}
        </p>

        <template v-if="respondida">
            <p
                v-if="mensagem.valoresSemFonte.length"
                role="alert"
                class="flex gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 p-2 text-xs"
            >
                <TriangleAlert class="size-4 shrink-0 text-amber-600" />
                <span>
                    Estes valores não vieram de nenhuma consulta ao banco:
                    <strong>{{ mensagem.valoresSemFonte.join(', ') }}</strong
                    >. Confira antes de confiar.
                </span>
            </p>
            <p
                v-else-if="consultasFeitas.length"
                class="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400"
            >
                <CircleCheck class="size-3.5 shrink-0" />
                Todos os valores vieram das consultas ao banco.
            </p>

            <details
                v-if="mensagem.consultas.length"
                class="text-xs text-muted-foreground"
            >
                <summary class="cursor-pointer">
                    {{
                        mensagem.consultas.length === 1
                            ? '1 consulta'
                            : `${mensagem.consultas.length} consultas`
                    }}{{ mensagem.modelo ? ` · ${mensagem.modelo}` : '' }}
                </summary>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li
                        v-for="(consulta, indice) in mensagem.consultas"
                        :key="indice"
                    >
                        {{ consulta.descricao
                        }}<span v-if="consulta.erro"> (deu erro)</span>
                    </li>
                </ul>
            </details>
        </template>
    </div>
</template>
