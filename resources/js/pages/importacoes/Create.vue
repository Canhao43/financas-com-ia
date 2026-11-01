<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ImportacaoController from '@/actions/App/Http/Controllers/ImportacaoController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as novaConta } from '@/routes/contas';
import { index } from '@/routes/importacoes';
import type { Opcao } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Importações',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    contas: Opcao[];
}>();

const contaId = ref(String(props.contas[0]?.id ?? ''));
</script>

<template>
    <Head title="Importar extrato" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <Heading
            title="Importar extrato"
            description="O arquivo vira uma prévia: nada é gravado antes de você conferir."
        />

        <div
            v-if="!contas.length"
            class="rounded-xl border border-dashed p-6 text-center text-sm"
        >
            <p class="text-muted-foreground">
                Antes de importar, cadastre a conta ou o cartão de onde vem o
                extrato.
            </p>
            <Button class="mt-4" as-child>
                <Link :href="novaConta()">Cadastrar conta</Link>
            </Button>
        </div>

        <Form
            v-else
            v-bind="ImportacaoController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="conta_id">Conta do extrato</Label>
                <NativeSelect id="conta_id" v-model="contaId" name="conta_id">
                    <option
                        v-for="conta in contas"
                        :key="conta.id"
                        :value="String(conta.id)"
                    >
                        {{ conta.nome }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.conta_id" />
            </div>

            <div class="grid gap-2">
                <Label for="arquivo">Arquivo OFX</Label>
                <!-- Sem "accept": no Android, o seletor deixa cinza a extensão que ele não conhece, e .ofx é uma delas. -->
                <Input id="arquivo" name="arquivo" type="file" required />
                <p class="text-sm text-muted-foreground">
                    No app ou no site do banco, exporte o extrato no formato OFX
                    (às vezes aparece como "Money").
                </p>
                <InputError :message="errors.arquivo" />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Ver prévia</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
