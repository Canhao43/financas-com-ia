<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContaController from '@/actions/App/Http/Controllers/ContaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/contas';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contas',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    conta: {
        id: number;
        nome: string;
        tipo: string;
        saldo_inicial: string;
        arquivada: boolean;
    } | null;
    tipos: { valor: string; rotulo: string }[];
}>();

const titulo = props.conta ? 'Editar conta' : 'Nova conta';

const acao = props.conta
    ? ContaController.update.form(props.conta.id)
    : ContaController.store.form();

const tipo = ref(props.conta?.tipo ?? 'corrente');
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
                    placeholder="Ex.: Conta do banco"
                    required
                    :default-value="conta?.nome"
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="tipo">Tipo</Label>
                    <NativeSelect id="tipo" v-model="tipo" name="tipo">
                        <option
                            v-for="opcao in tipos"
                            :key="opcao.valor"
                            :value="opcao.valor"
                        >
                            {{ opcao.rotulo }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.tipo" />
                </div>

                <div class="grid gap-2">
                    <Label for="saldo_inicial">Saldo inicial (R$)</Label>
                    <!-- Teclado comum, não o numérico: o do iPhone não tem sinal de menos. -->
                    <Input
                        id="saldo_inicial"
                        name="saldo_inicial"
                        placeholder="0,00"
                        autocomplete="off"
                        :default-value="conta?.saldo_inicial"
                    />
                    <InputError :message="errors.saldo_inicial" />
                </div>
            </div>
            <p class="-mt-4 text-sm text-muted-foreground">
                Quanto havia na conta antes do primeiro lançamento. Para fatura
                em aberto ou cheque especial, use o sinal de menos: -1.250,00.
            </p>

            <label v-if="conta" class="flex items-start gap-3 text-sm">
                <input type="hidden" name="arquivada" value="0" />
                <input
                    type="checkbox"
                    name="arquivada"
                    value="1"
                    class="mt-0.5 size-4 accent-primary"
                    :checked="conta.arquivada"
                />
                <span>
                    <span class="font-medium">Arquivar conta</span>
                    <span class="block text-muted-foreground">
                        Some do formulário de lançamento e do saldo total. O
                        histórico continua aqui.
                    </span>
                </span>
            </label>
            <InputError :message="errors.arquivada" />

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Salvar</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
