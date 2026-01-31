<script setup lang="ts">
import { router } from '@inertiajs/vue3';

const { class: className = '', label = 'Sign Out' } = defineProps({
  class: { type: String, default: '' },
  label: { type: String, default: 'Sign Out' }
});

const submitLogout = () => {
    const redirectValue = window.location.pathname.startsWith('/business') ? '/login' : '/';
    router.post('/logout', { redirect_to: redirectValue });
};
</script>

<template>
  <button @click="submitLogout" :class="className">
    <slot>{{ label }}</slot>
  </button>
</template>
