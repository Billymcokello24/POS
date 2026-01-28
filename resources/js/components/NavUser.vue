<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, User } from 'lucide-vue-next';
import { ref } from 'vue';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';

import UserMenuContent from './UserMenuContent.vue';

const page = usePage();
const user = page.props.auth?.user;

// Use default values - component won't render without user anyway
const isMobile = ref(false);
const state = ref<'expanded' | 'collapsed'>('expanded');
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="group relative overflow-hidden rounded-xl border-2 border-slate-200 bg-white hover:border-purple-300 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 transition-all data-[state=open]:bg-gradient-to-r data-[state=open]:from-purple-100 data-[state=open]:to-pink-100 data-[state=open]:border-purple-400"
                        data-test="sidebar-menu-button"
                    >
                        <div class="flex items-center gap-3 flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-purple-600 to-pink-600 text-white font-bold shadow-lg">
                                <User class="h-5 w-5" />
                            </div>
                            <UserInfo :user="user" class="flex-1" />
                        </div>
                        <ChevronsUpDown class="ml-auto size-4 text-slate-600 group-hover:text-purple-600 transition-colors" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-xl border-2 border-slate-200 shadow-2xl"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
