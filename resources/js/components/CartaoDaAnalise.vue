<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    CircleCheck,
    LoaderCircle,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { formatarData } from '@/lib/datas';
import { analise as escreverAnalise } from '@/routes/relatorios';
import type { AnaliseDoMes } from '@/types';

defineProps<{
    mes: string;
    analise: AnaliseDoMes | null;
    ligada: boolean;
    temLancamentos: boolean;
}>();

// "R$" e o número na mesma linha: no celular, a quebra entre os dois deixava "R$" sozinho no
// fim da linha. Só na tela; o texto gravado e conferido continua o mesmo.
function semQuebrarValores(texto: string): string {
    return texto.replace(/R\$ /g, 'R$ ');
}
</script>

<template>
    <section class="space-y-3 rounded-xl border p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
            <h2 class="flex items-center gap-2 text-sm font-medium">
                <Sparkles class="size-4 text-primary" />
                Análise do mês
            </h2>
            <span
                v-if="analise?.escritaEm"
                class="text-xs text-muted-foreground"
                >escrita pelo Claude em
                {{ formatarData(analise.escritaEm) }}</span
            >
        </div>

        <template v-if="analise">
            <!-- Texto simples: o que vem do Claude nunca vira HTML. -->
            <p class="text-sm leading-relaxed">
                {{ semQuebrarValores(analise.resumo) }}
            </p>
            <ul
                v-if="analise.destaques.length"
                class="list-disc space-y-1 pl-5 text-sm leading-relaxed"
            >
                <li
                    v-for="(destaque, indice) in analise.destaques"
                    :key="indice"
                >
                    {{ semQuebrarValores(destaque) }}
                </li>
            </ul>

            <p
                v-if="analise.desatualizada"
                role="status"
                class="flex gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 p-2 text-xs"
            >
                <TriangleAlert class="size-4 shrink-0 text-amber-600" />
                <span>
                    Os números do mês mudaram depois desta análise (lançamento
                    novo, categoria trocada ou o mês acabou). O texto pode não
                    bater mais com a página.
                </span>
            </p>
            <p
                v-if="analise.valoresSemFonte.length"
                role="alert"
                class="flex gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 p-2 text-xs"
            >
                <TriangleAlert class="size-4 shrink-0 text-amber-600" />
                <span>
                    Estes valores não estão nos números do mês:
                    <strong>{{ analise.valoresSemFonte.join(', ') }}</strong
                    >. Confira antes de confiar.
                </span>
            </p>
            <p
                v-else-if="!analise.desatualizada"
                class="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400"
            >
                <CircleCheck class="size-3.5 shrink-0" />
                Todos os valores do texto conferem com os desta página.
            </p>
        </template>
        <p v-else class="text-sm text-muted-foreground">
            O Claude resume o mês em poucas frases, só com os números desta
            página, e o app confere cada valor que ele citar.
        </p>

        <Form
            v-if="temLancamentos"
            v-bind="escreverAnalise.form()"
            :options="{ preserveScroll: true }"
            class="space-y-2"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="mes" :value="mes" />
            <Button
                size="sm"
                :variant="
                    analise && !analise.desatualizada ? 'outline' : 'default'
                "
                :disabled="!ligada || processing"
            >
                <LoaderCircle v-if="processing" class="animate-spin" />
                <Sparkles v-else />
                {{
                    processing
                        ? 'Escrevendo…'
                        : analise
                          ? 'Escrever de novo'
                          : 'Escrever análise'
                }}
            </Button>
            <p v-if="processing" class="text-xs text-muted-foreground">
                O Claude está lendo os números do mês. Pode levar até um minuto.
            </p>
            <p v-else-if="!ligada" class="text-xs text-muted-foreground">
                Desligada: falta a chave da API da Anthropic (ANTHROPIC_API_KEY)
                no .env.
            </p>
            <InputError :message="errors.analise ?? errors.mes" />
        </Form>
    </section>
</template>
