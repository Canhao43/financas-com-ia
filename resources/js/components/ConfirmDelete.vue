<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RouteFormDefinition } from '@/wayfinder';

withDefaults(
    defineProps<{
        acao: RouteFormDefinition<'post'>;
        titulo: string;
        descricao: string;
        rotulo?: string;
    }>(),
    { rotulo: 'Excluir' },
);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button type="button" variant="ghost" class="text-destructive">
                <Trash2 />{{ rotulo }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogTitle>{{ titulo }}</DialogTitle>
            <DialogDescription>{{ descricao }}</DialogDescription>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Form v-bind="acao" #default="{ processing }">
                    <Button
                        type="submit"
                        variant="destructive"
                        class="w-full"
                        :disabled="processing"
                    >
                        {{ rotulo }}
                    </Button>
                </Form>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
