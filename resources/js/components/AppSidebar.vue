<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    FileUp,
    LayoutGrid,
    ListFilter,
    Tags,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as categorias } from '@/routes/categorias';
import { index as contas } from '@/routes/contas';
import { index as importacoes } from '@/routes/importacoes';
import { index as regras } from '@/routes/regras';
import { index as lancamentos } from '@/routes/lancamentos';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Painel',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Lançamentos',
        href: lancamentos(),
        icon: ArrowLeftRight,
    },
    {
        title: 'Importações',
        href: importacoes(),
        icon: FileUp,
    },
    {
        title: 'Contas',
        href: contas(),
        icon: Wallet,
    },
    {
        title: 'Categorias',
        href: categorias(),
        icon: Tags,
    },
    {
        title: 'Regras',
        href: regras(),
        icon: ListFilter,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
