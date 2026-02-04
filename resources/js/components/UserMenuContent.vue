<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { edit } from '@/routes/profile/index';
import type { User } from '@/types';

import axios from '@/axios';

import { ensureSanctum } from '@/lib/sanctum';


interface Props {
    user?: User | null;
}

// Use the server-rendered hidden logout form if available, otherwise create one.
const handleLogout = async (e?: Event) => {
    if (e && e.preventDefault) e.preventDefault();

    const serverForm = document.getElementById('logout-form') as HTMLFormElement | null;

    // If on a business route, redirect to business login after logout
    const redirectTo = window.location.pathname.startsWith('/business') ? '/login' : '/';

    if (serverForm) {
        const redirectInput = serverForm.querySelector('input[name="redirect_to"]') as HTMLInputElement | null;
        if (redirectInput) redirectInput.value = redirectTo;
        serverForm.submit();
        return;
    }

    // Fallback: use axios with Sanctum to perform logout
    try {
        await ensureSanctum();
        // axios is configured with withCredentials and XSRF in resources/js/axios.ts
        await axios.post('/logout', { redirect_to: redirectTo });

        // Redirect after logout
        window.location.href = redirectTo;
    } catch (e) {
        console.warn('Logout via axios failed, falling back to form submit', e);
        // Fallback to programmatic form if axios fails
        const tokenMeta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        const token = tokenMeta?.getAttribute('content') || '';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/logout';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token;
        form.appendChild(input);

        const redirect = document.createElement('input');
        redirect.type = 'hidden';
        redirect.name = 'redirect_to';
        redirect.value = redirectTo;
        form.appendChild(redirect);

        document.body.appendChild(form);
        form.submit();
    }
};

defineProps<Props>();
</script>

<template>
    <template v-if="user">
        <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <UserInfo v-if="user" :user="user" :show-email="true" />
            </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                    <Settings class="mr-2 h-4 w-4" />
                    Settings
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem :as-child="true">
            <button
                type="button"
                class="flex w-full cursor-pointer items-center"
                @click="handleLogout"
                data-test="logout-button"
            >
                <LogOut class="mr-2 h-4 w-4" />
                Log out
            </button>
        </DropdownMenuItem>
    </template>
</template>
