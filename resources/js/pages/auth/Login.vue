<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Mail,
    Lock,
    CheckCircle2,
    ShieldCheck,
    ArrowUpRight
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

// UI Components
import axios from '@/axios'
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner'

interface Props {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    errors?: Record<string, string>;
    csrf_token?: string;
}

const props = defineProps<Props>();

// Initialize form with Inertia's useForm
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Compatibility: some versions of Inertia's useForm expose setErrors/clearErrors/hasErrors
// while others may not. Ensure the shape we expect exists so template logic and
// error assignments below won't throw runtime TypeErrors.
if (!(form as any).errors) {
    (form as any).errors = {};
}
if (typeof (form as any).hasErrors === 'undefined') {
    Object.defineProperty(form, 'hasErrors', {
        get() {
            try {
                return Object.keys((form as any).errors || {}).length > 0;
            } catch {
                return false;
            }
        },
        configurable: true,
        enumerable: true,
    });
}

const csrfToken = ref<string>(props.csrf_token || '');

onMounted(() => {
    // Ensure CSRF token is available
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        csrfToken.value = metaToken.getAttribute('content') || '';
    }

    // Add CSRF token to form data
    if (csrfToken.value) {
        // Inertia automatically adds _token, but we ensure it's set
        form.transform((data) => ({
            ...data,
            _token: csrfToken.value,
        }));
    }

    console.log('CSRF Token available:', csrfToken.value ? 'Yes' : 'No');
});

const submit = async () => {
    console.log('Submitting login form...');

    // Ensure Sanctum CSRF cookie is present before attempting form post
    try {
        // Use axios to fetch the csrf cookie (axios is configured with withCredentials)
        await axios.get('/sanctum/csrf-cookie')
    } catch (e) {
        console.warn('Failed to fetch /sanctum/csrf-cookie via axios', e)
    }

    // Build payload (include server _token if available)
    const payload: Record<string, any> = {
        email: form.email,
        password: form.password,
        remember: form.remember,
    }
    if (csrfToken.value) payload._token = csrfToken.value

    // Use axios to POST the login with credentials and XSRF header
    try {
        const doLogin = async () => {
            // axios is configured to send credentials and set X-XSRF-TOKEN automatically from cookie
            return axios.post('/login', payload, { withCredentials: true })
        }

        let res
        try {
            res = await doLogin()
        } catch (err: any) {
            if (err?.response?.status === 419) {
                // Refresh CSRF cookie and retry once
                await axios.get('/sanctum/csrf-cookie')
                res = await doLogin()
            } else if (err?.response?.status === 422) {
                // Validation errors returned as JSON
                const validationErrors = err.response?.data?.errors || {};
                if (typeof (form as any).setErrors === 'function') {
                    (form as any).setErrors(validationErrors);
                } else {
                    // Fallback for versions without setErrors
                    (form as any).errors = validationErrors;
                }
                return
            } else {
                throw err
            }
        }

        // On successful login, redirect to the intended location or dashboard
        if (res?.status >= 200 && res?.status < 300) {
            window.location.href = res.headers?.location || '/dashboard'
        }
    } catch (err: any) {
        console.error('Login request failed:', err)
        alert('Login failed: ' + (err?.response?.data?.message || err.message || 'Unknown error'))
    } finally {
        if (typeof (form as any).clearErrors === 'function') {
            (form as any).clearErrors();
        } else {
            (form as any).errors = {};
        }
    }
};

// Demo account helper
const useDemoAccount = (email: string, password: string = 'password') => {
    form.email = email;
    form.password = password;
    form.remember = true;

    // Submit after a brief delay
    setTimeout(() => {
        submit();
    }, 300);
};
</script>

<template>
    <Head title="Login - Modern POS">
        <!-- CSRF Token meta tag -->
        <meta name="csrf-token" :content="csrfToken" v-if="csrfToken">
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans flex">
        <!-- Left Side - Visual -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-gray-900 to-blue-900">
            <div class="absolute inset-0 bg-black/30 z-10"></div>
            <img
                src="https://images.unsplash.com/photo-1556742111-a301076d9d18?q=80&w=2070&auto=format&fit=crop"
                alt="POS System Dashboard"
                class="absolute inset-0 w-full h-full object-cover"
            />

            <!-- Overlay content -->
            <div class="relative z-20 w-full h-full flex flex-col justify-between p-12">
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-3 text-white hover:text-blue-200 transition-colors">
                    <div class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center">
                        <ShoppingCart :size="20" class="text-white" />
                    </div>
                    <span class="text-xl font-bold">
                        Modern<span class="text-blue-400">POS</span>
                    </span>
                </Link>

                <!-- Hero text -->
                <div class="space-y-6">
                    <h2 class="text-4xl font-bold text-white leading-tight">
                        Secure access to your<br>
                        <span class="text-blue-300">retail management</span> system
                    </h2>

                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
                            <ShieldCheck :size="14" class="text-green-400" />
                            <span class="text-sm text-white/90">Bank-grade security</span>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
                            <ArrowUpRight :size="14" class="text-blue-400" />
                            <span class="text-sm text-white/90">Real-time updates</span>
                        </div>
                    </div>
                </div>

                <!-- Footer note -->
                <div class="text-white/70 text-sm">
                    <p>Trusted by 500+ retail businesses</p>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-4 md:p-8">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 md:p-10">
                <!-- Logo for mobile -->
                <div class="lg:hidden mb-8">
                    <div class="flex items-center gap-3 text-gray-900">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center">
                            <ShoppingCart :size="20" />
                        </div>
                        <span class="text-xl font-bold">
                            Modern<span class="text-blue-600">POS</span>
                        </span>
                    </div>
                </div>

                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        Welcome Back
                    </h1>
                    <p class="text-gray-600">
                        Sign in to access your dashboard
                    </p>
                </div>

                <!-- Status message -->
                <div
                    v-if="status"
                    class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3"
                >
                    <CheckCircle2 :size="18" class="text-green-600" />
                    <span class="text-green-700 font-medium text-sm">{{ status }}</span>
                </div>

                <!-- Error messages -->
                <div
                    v-if="form.hasErrors"
                    class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl"
                >
                    <div class="text-red-700 text-sm font-medium mb-2">
                        Please fix the following errors:
                    </div>
                    <div v-for="(error, field) in form.errors" :key="field" class="text-red-600 text-sm">
                        {{ error }}
                    </div>
                </div>

                <!-- CSRF token warning -->
                <div
                    v-if="!csrfToken"
                    class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl"
                >
                    <div class="text-yellow-700 text-sm">
                        Security token missing. Please refresh the page.
                    </div>
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Hidden CSRF token field -->
                    <input type="hidden" name="_token" :value="csrfToken" v-if="csrfToken">

                    <!-- Email field -->
                    <div class="space-y-2">
                        <Label for="email" class="text-gray-700 font-medium text-sm">
                            Email Address
                        </Label>
                        <div class="relative">
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                <Mail :size="18" />
                            </div>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="admin@company.com"
                                :class="[
                                    'pl-10 h-11 w-full rounded-lg border-gray-300',
                                    form.errors.email ? 'border-red-300 focus:border-red-500' : 'focus:border-blue-500'
                                ]"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password field -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="password" class="text-gray-700 font-medium text-sm">
                                Password
                            </Label>
                            <Link
                                v-if="canResetPassword"
                                href="/forgot-password"
                                class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <div class="relative">
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                <Lock :size="18" />
                            </div>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                :class="[
                                    'pl-10 h-11 w-full rounded-lg border-gray-300',
                                    form.errors.password ? 'border-red-300 focus:border-red-500' : 'focus:border-blue-500'
                                ]"
                            />
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="remember"
                            v-model:checked="form.remember"
                            class="border-gray-300"
                        />
                        <Label for="remember" class="text-sm text-gray-700 cursor-pointer">
                            Remember me
                        </Label>
                    </div>

                    <!-- Submit button -->
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-11 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                    >
                        <Spinner v-if="form.processing" class="mr-2" />
                        <span>{{ form.processing ? 'Signing in...' : 'Sign In' }}</span>
                    </Button>
                </form>

                <!-- Demo accounts -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-500 text-center mb-4">
                        Try demo accounts
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            @click="useDemoAccount('admin@pos.com')"
                            class="p-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg text-center transition-colors"
                        >
                            <div class="font-medium text-gray-900 text-sm">Admin</div>
                            <div class="text-gray-500 text-xs mt-1">admin@pos.com</div>
                        </button>
                        <button
                            @click="useDemoAccount('cashier@demo.com')"
                            class="p-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg text-center transition-colors"
                        >
                            <div class="font-medium text-gray-900 text-sm">Cashier</div>
                            <div class="text-gray-500 text-xs mt-1">cashier@demo.com</div>
                        </button>
                    </div>
                </div>

                <!-- Register link -->
                <div v-if="canRegister" class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account?
                        <Link href="/register" class="text-blue-600 hover:text-blue-800 hover:underline font-medium ml-1">
                            Sign up
                        </Link>
                    </p>
                </div>

                <!-- CSRF debug (remove in production) -->
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <div class="text-xs text-gray-400">
                        <div>Session ID: {{ $page.props.session_id }}</div>
                        <div v-if="csrfToken">CSRF: {{ csrfToken.substring(0, 15) }}...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Smooth transitions */
* {
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
</style>
