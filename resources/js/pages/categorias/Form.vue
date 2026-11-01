<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CategoriaController from '@/actions/App/Http/Controllers/CategoriaController';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import EscolhaSegmentada from '@/components/EscolhaSegmentada.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/categorias';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categorias',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    categoria: {
        id: number;
        nome: string;
        tipo: 'despesa' | 'receita';
        lancamentos: number;
    } | null;
}>();

const titulo = props.categoria ? 'Editar categoria' : 'Nova categoria';

const acao = props.categoria
    ? CategoriaController.update.form(props.categoria.id)
    : CategoriaController.store.form();

const tipos = [
    { valor: 'despesa', rotulo: 'Despesa' },
    { valor: 'receita', rotulo: 'Receita' },
];

const tipo = ref<string>(props.categoria?.tipo ?? 'despesa');

// Com lançamentos, o tipo fica fixo (o servidor também recusa a troca).
const tipoTravado = (props.categoria?.lancamentos ?? 0) > 0;

const avisoDeExclusao = props.categoria?.lancamentos
    ? `Os lançamentos dela (${props.categoria.lancamentos}) continuam existindo, sem categoria.`
    : 'Nenhum lançamento usa esta categoria.';
</script>

<template>
    <Head :title="titulo" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <Heading :title="titulo" />

        <Form v-bind="acao" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="nome">Nome</Label>
                <Input
                    id="nome"
                    name="nome"
                    maxlength="60"
                    placeholder="Ex.: Farmácia"
                    required
                    :default-value="categoria?.nome"
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-2">
                <Label>Tipo</Label>
                <!-- Rádio desabilitado não vai no envio; o campo oculto leva o valor. -->
                <input
                    v-if="tipoTravado"
                    type="hidden"
                    name="tipo"
                    :value="tipo"
                />
                <EscolhaSegmentada
                    v-model="tipo"
                    :name="tipoTravado ? undefined : 'tipo'"
                    :opcoes="tipos"
                    :disabled="tipoTravado"
                />
                <p v-if="tipoTravado" class="text-sm text-muted-foreground">
                    Já tem lançamentos, então o tipo não muda.
                </p>
                <InputError :message="errors.tipo" />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Salvar</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>

        <div v-if="categoria" class="border-t pt-6">
            <ConfirmDelete
                :acao="CategoriaController.destroy.form(categoria.id)"
                titulo="Excluir categoria"
                :descricao="avisoDeExclusao"
            />
        </div>
    </div>
</template>
