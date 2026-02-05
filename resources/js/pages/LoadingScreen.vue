<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import LoadingPreloader from '@/components/LoadingPreloader.vue'

defineProps<{
    redirectTo?: string
}>()
</script>

<template>
    <Head title="Loading..." />

    <LoadingPreloader :redirect-to="redirectTo" />
</template>
