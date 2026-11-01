<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RegraController from '@/actions/App/Http/Controllers/RegraController';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/regras';
import type { Opcao } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Regras',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    regra: { id: number; texto: string; categoria_id: number } | null;
    categorias: (Opcao & { tipo: 'despesa' | 'receita' })[];
}>();

const titulo = props.regra ? 'Editar regra' : 'Nova regra';

const acao = props.regra
    ? RegraController.update.form(props.regra.id)
    : RegraController.store.form();

const categoriaId = ref(String(props.regra?.categoria_id ?? ''));

const grupos = computed(() => [
    {
        rotulo: 'Despesas',
        categorias: props.categorias.filter((c) => c.tipo === 'despesa'),
    },
    {
        rotulo: 'Receitas',
        categorias: props.categorias.filter((c) => c.tipo === 'receita'),
    },
]);
</script>

<template>
    <Head :title="titulo" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="titulo"
            description="Maiúsculas e acentos não importam. Regra de despesa vale só para saídas; de receita, só para entradas. Se mais de uma combinar, vale a de texto mais longo."
        />

        <Form v-bind="acao" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="texto">Se a descrição contém</Label>
                <Input
                    id="texto"
                    name="texto"
                    maxlength="60"
                    placeholder="Ex.: IFOOD"
                    autocomplete="off"
                    required
                    :default-value="regra?.texto"
                />
                <InputError :message="errors.texto" />
            </div>

            <div class="grid gap-2">
                <Label for="categoria_id">A categoria é</Label>
                <NativeSelect
                    id="categoria_id"
                    v-model="categoriaId"
                    name="categoria_id"
                    required
                >
                    <option value="" disabled>Escolha a categoria</option>
                    <optgroup
                        v-for="grupo in grupos"
                        :key="grupo.rotulo"
                        :label="grupo.rotulo"
                    >
                        <option
                            v-for="categoria in grupo.categorias"
                            :key="categoria.id"
                            :value="String(categoria.id)"
                        >
                            {{ categoria.nome }}
                        </option>
                    </optgroup>
                </NativeSelect>
                <InputError :message="errors.categoria_id" />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Salvar</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>

        <div v-if="regra" class="border-t pt-6">
            <ConfirmDelete
                :acao="RegraController.destroy.form(regra.id)"
                titulo="Excluir regra"
                :descricao="`A regra “${regra.texto}” deixa de valer. Os lançamentos que ela já categorizou continuam como estão.`"
            />
        </div>
    </div>
</template>
