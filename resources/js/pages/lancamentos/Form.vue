<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import LancamentoController from '@/actions/App/Http/Controllers/LancamentoController';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import EscolhaSegmentada from '@/components/EscolhaSegmentada.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as novaConta } from '@/routes/contas';
import { index } from '@/routes/lancamentos';
import type { Direcao, Opcao } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lançamentos',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    contas: Opcao[];
    categorias: (Opcao & { direcao: Direcao })[];
    lancamento: {
        id: number;
        direcao: Direcao;
        valor: string;
        data: string;
        descricao: string;
        conta_id: number;
        categoria_id: number | null;
    } | null;
    padrao: { data: string } | null;
}>();

const titulo = props.lancamento ? 'Editar lançamento' : 'Novo lançamento';

const acao = props.lancamento
    ? LancamentoController.update.form(props.lancamento.id)
    : LancamentoController.store.form();

// Cancelar volta para o mês do lançamento, não para o mês atual.
const voltar = props.lancamento
    ? index({ query: { mes: props.lancamento.data.slice(0, 7) } })
    : index();

const direcoes = [
    { valor: 'saida', rotulo: 'Saída' },
    { valor: 'entrada', rotulo: 'Entrada' },
];

const direcao = ref<string>(props.lancamento?.direcao ?? 'saida');
const contaId = ref(
    String(props.lancamento?.conta_id ?? props.contas[0]?.id ?? ''),
);
const categoriaId = ref(String(props.lancamento?.categoria_id ?? ''));

const categoriasDaDirecao = computed(() =>
    props.categorias.filter((categoria) => categoria.direcao === direcao.value),
);

// Ao trocar entre saída e entrada, a categoria escolhida pode não servir mais.
watch(direcao, () => {
    const continuaValida = categoriasDaDirecao.value.some(
        (categoria) => String(categoria.id) === categoriaId.value,
    );

    if (!continuaValida) {
        categoriaId.value = '';
    }
});
</script>

<template>
    <Head :title="titulo" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <Heading :title="titulo" />

        <div
            v-if="!contas.length"
            class="rounded-xl border border-dashed p-6 text-center text-sm"
        >
            <p class="text-muted-foreground">
                Antes do primeiro lançamento, cadastre a conta de onde o
                dinheiro sai ou para onde ele entra.
            </p>
            <Button class="mt-4" as-child>
                <Link :href="novaConta()">Cadastrar conta</Link>
            </Button>
        </div>

        <Form
            v-else
            v-bind="acao"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <EscolhaSegmentada
                    v-model="direcao"
                    name="direcao"
                    :opcoes="direcoes"
                />
                <InputError :message="errors.direcao" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="valor">Valor (R$)</Label>
                    <Input
                        id="valor"
                        name="valor"
                        inputmode="decimal"
                        placeholder="0,00"
                        autocomplete="off"
                        required
                        :default-value="lancamento?.valor"
                    />
                    <InputError :message="errors.valor" />
                </div>

                <div class="grid gap-2">
                    <Label for="data">Data</Label>
                    <Input
                        id="data"
                        name="data"
                        type="date"
                        required
                        :default-value="lancamento?.data ?? padrao?.data"
                    />
                    <InputError :message="errors.data" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="descricao">Descrição</Label>
                <Input
                    id="descricao"
                    name="descricao"
                    maxlength="255"
                    placeholder="Ex.: mercado da semana"
                    required
                    :default-value="lancamento?.descricao"
                />
                <InputError :message="errors.descricao" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="conta_id">Conta</Label>
                    <NativeSelect
                        id="conta_id"
                        v-model="contaId"
                        name="conta_id"
                    >
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
                    <Label for="categoria_id">Categoria</Label>
                    <NativeSelect
                        id="categoria_id"
                        v-model="categoriaId"
                        name="categoria_id"
                    >
                        <option value="">Sem categoria</option>
                        <option
                            v-for="categoria in categoriasDaDirecao"
                            :key="categoria.id"
                            :value="String(categoria.id)"
                        >
                            {{ categoria.nome }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.categoria_id" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Salvar</Button>
                <Button variant="ghost" as-child>
                    <Link :href="voltar">Cancelar</Link>
                </Button>
            </div>
        </Form>

        <div v-if="lancamento" class="border-t pt-6">
            <ConfirmDelete
                :acao="LancamentoController.destroy.form(lancamento.id)"
                titulo="Excluir lançamento"
                :descricao="`“${lancamento.descricao}” será excluído e os totais do mês, recalculados.`"
            />
        </div>
    </div>
</template>
