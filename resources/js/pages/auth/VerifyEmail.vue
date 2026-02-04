<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { send } from '@/routes/verification/index';

defineProps<{
    status?: string;
}>();

// Submit the server-rendered hidden logout form or create a fallback POST form with CSRF token
const submitLogout = (e?: Event) => {
    if (e && e.preventDefault) e.preventDefault();

    const serverForm = document.getElementById('logout-form') as HTMLFormElement | null;

    const redirectTo = window.location.pathname.startsWith('/business') ? '/login' : '/';

    if (serverForm) {
        const redirectInput = serverForm.querySelector('input[name="redirect_to"]') as HTMLInputElement | null;
        if (redirectInput) redirectInput.value = redirectTo;
        serverForm.submit();
        return;
    }

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
};
</script>

<template>
    <AuthLayout
        title="Verify email"
        description="Please verify your email address by clicking on the link we just emailed to you."
    >
        <Head title="Email verification" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                Resend verification email
            </Button>

            <!-- Use a button that submits the server-rendered logout form to ensure CSRF and cookies are sent -->
            <button
                type="button"
                class="mx-auto block text-sm text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                @click="submitLogout"
            >
                Log out
            </button>
        </Form>
    </AuthLayout>
</template>
