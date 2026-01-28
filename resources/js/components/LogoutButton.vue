<script setup lang="ts">
import { ref, onMounted } from 'vue';

import { ensureSanctum } from '@/lib/sanctum'
import { postJsonWithSanctum } from '@/lib/sanctum'

const { class: className = '', label = 'Sign Out' } = defineProps({
  class: { type: String, default: '' },
  label: { type: String, default: 'Sign Out' }
});

const csrfToken = ref('');
const redirectValue = ref('/');

onMounted(() => {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) csrfToken.value = (meta as HTMLMetaElement).getAttribute('content') || '';

  // set default redirect based on current path
  redirectValue.value = window.location.pathname.startsWith('/business') ? '/login' : '/';
});

const submitLogout = async (e?: Event) => {
  if (e && e.preventDefault) e.preventDefault();

  // Ensure Sanctum cookie is present first
  try {
    await ensureSanctum();
  } catch (err) {
    console.warn('Failed to ensure sanctum before logout', err);
  }

  // Try to POST via fetch helper which attaches XSRF header and sends credentials
  try {
    const response = await postJsonWithSanctum('/logout', { redirect_to: redirectValue.value });
    // If server responds with a redirect location, follow it; otherwise reload to redirect URL
    if (response instanceof Response && response.redirected) {
      window.location.href = response.url;
      return;
    }
    // If 204/200, navigate to redirect
    if (response instanceof Response && (response.status === 204 || response.status === 200)) {
      window.location.href = redirectValue.value;
      return;
    }
  } catch (err) {
    // fall back to form submission below
    console.warn('AJAX logout failed, falling back to form submit', err);
  }

  // Submit the server-rendered form if present, otherwise fall back to default behavior
  const serverForm = document.getElementById('logout-form') as HTMLFormElement | null;
  if (serverForm) {
    const redirectInput = serverForm.querySelector('input[name="redirect_to"]') as HTMLInputElement | null;
    if (redirectInput) redirectInput.value = redirectValue.value;
    serverForm.submit();
    return;
  }

  // If no server form, create one and submit (same as before)
  const token = csrfToken.value || '';
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
  redirect.value = redirectValue.value;
  form.appendChild(redirect);

  document.body.appendChild(form);
  form.submit();
};
</script>

<template>
  <form @submit.prevent="submitLogout" class="w-full">
    <input type="hidden" name="_token" :value="csrfToken" />
    <input type="hidden" name="redirect_to" :value="redirectValue" />
    <button type="submit" :class="className">
      <slot>{{ label }}</slot>
    </button>
  </form>
</template>
