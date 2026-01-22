<script setup lang="ts">
import { computed } from 'vue';

import { SidebarInset } from '@/components/ui/sidebar';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="['mx-auto w-[90%]', className]">
        <slot />
    </SidebarInset>
    <main
        v-else
        class="mx-auto w-[90%] flex h-full flex-1 flex-col gap-4 rounded-xl"
        :class="className"
    >
        <slot />
    </main>
</template>
