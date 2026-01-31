<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Mail,
    Lock,
    CheckCircle2,
    ShieldCheck,
    ShieldAlert,
    ArrowUpRight,
    Loader2,
    Building2
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
    
    // Clear previous errors at the start
    if (typeof (form as any).clearErrors === 'function') {
        (form as any).clearErrors();
    } else {
        (form as any).errors = {};
    }

    try {
        await axios.get('/sanctum/csrf-cookie')
    } catch (e) {
        console.warn('Failed to fetch /sanctum/csrf-cookie via axios', e)
    }

    const payload: Record<string, any> = {
        email: form.email,
        password: form.password,
        remember: form.remember,
    }
    if (csrfToken.value) payload._token = csrfToken.value

    try {
        const doLogin = async () => {
            return axios.post('/login', payload, { withCredentials: true })
        }

        let res
        try {
            res = await doLogin()
        } catch (err: any) {
            if (err?.response?.status === 419) {
                await axios.get('/sanctum/csrf-cookie')
                res = await doLogin()
            } else if (err?.response?.status === 422) {
                const validationErrors: Record<string, string> = {};
                // Flatten the errors array for a cleaner experience
                Object.keys(err.response?.data?.errors || {}).forEach(key => {
                    validationErrors[key] = Array.isArray(err.response.data.errors[key]) 
                        ? err.response.data.errors[key][0] 
                        : err.response.data.errors[key];
                });

                if (typeof (form as any).setErrors === 'function') {
                    (form as any).setErrors(validationErrors);
                } else {
                    (form as any).errors = validationErrors;
                }
                return
            } else {
                throw err
            }
        }

        if (res?.status >= 200 && res?.status < 300) {
            window.location.href = res.headers?.location || '/dashboard'
        }
    } catch (err: any) {
        console.error('Login request failed:', err)
        // Only alert if it's not a validation error (already handled above)
        if (err?.response?.status !== 422) {
            alert('Critical Login Error: ' + (err?.response?.data?.message || err.message || 'Unknown error'))
        }
    }
};
</script>

<template>
    <Head title="Login - Modern POS">
        <!-- CSRF Token meta tag -->
        <meta name="csrf-token" :content="csrfToken" v-if="csrfToken">
    </Head>

    <div class="min-h-screen bg-slate-50 font-sans flex text-slate-900 selection:bg-indigo-500 selection:text-white">
        <!-- Left Side - Visual Powerhouse -->
        <div class="hidden lg:flex lg:w-3/5 relative overflow-hidden bg-[#0A0C1B]">
            <div class="absolute inset-0 z-10 bg-gradient-to-br from-[#0A0C1B] via-transparent to-indigo-900/30"></div>
            
            <!-- Animated Background Grid -->
            <div class="absolute inset-0 grid-background opacity-20"></div>
            
            <img
                src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=2070&auto=format&fit=crop"
                alt="Retail Experience"
                class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay scale-105"
            />

            <!-- Architectural Elements -->
            <div class="absolute top-1/4 left-1/4 size-96 bg-indigo-500/10 rounded-full blur-[120px] animate-pulse"></div>
            <div class="absolute bottom-1/4 right-1/4 size-96 bg-blue-500/10 rounded-full blur-[120px] animate-pulse delay-700"></div>

            <!-- Content Area -->
            <div class="relative z-20 w-full h-full flex flex-col justify-between p-20">
                <!-- Branding -->
                <Link href="/" class="flex items-center gap-4 group">
                    <div class="size-12 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 group-hover:rotate-6 transition-transform duration-500 shadow-2xl">
                        <ShoppingCart :size="24" class="text-white" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-white tracking-tighter leading-none italic uppercase">
                            Modern<span class="text-indigo-400">POS</span>
                        </span>
                        <span class="text-[9px] font-black text-white/40 uppercase tracking-[0.3em] leading-none mt-1">Enterprise Suite</span>
                    </div>
                </Link>

                <!-- Hero Message -->
                <div class="max-w-xl space-y-10">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-white/5 border border-white/10 backdrop-blur-md rounded-full">
                            <ShieldCheck :size="14" class="text-emerald-400" />
                            <span class="text-[10px] font-black text-white/90 uppercase tracking-widest">Gated Cloud Infrastructure</span>
                        </div>
                        <h2 class="text-6xl font-black text-white leading-[0.9] tracking-tighter">
                            Manage with <br>
                            <span class="bg-gradient-to-r from-indigo-400 to-blue-300 bg-clip-text text-transparent">Total Control.</span>
                        </h2>
                    </div>

                    <p class="text-white/60 text-lg font-medium leading-relaxed">
                        Precision engineering for the modern retailer. Access your global workspace, sync inventory levels, and monitor revenue in real-time.
                    </p>

                    <div class="flex items-center gap-8 border-t border-white/10 pt-10">
                        <div>
                            <div class="text-2xl font-black text-white leading-none">500+</div>
                            <div class="text-[10px] font-bold text-white/40 uppercase tracking-widest mt-1">Global Entities</div>
                        </div>
                        <div class="size-1 bg-white/20 rounded-full"></div>
                        <div>
                            <div class="text-2xl font-black text-white leading-none">99.9%</div>
                            <div class="text-[10px] font-bold text-white/40 uppercase tracking-widest mt-1">Uptime SLA</div>
                        </div>
                    </div>
                </div>

                <!-- Footer Signage -->
                <div class="flex items-center justify-between text-white/30 text-[10px] font-black uppercase tracking-[0.2em]">
                    <span>© 2026 ModernPOS Systems</span>
                    <div class="flex gap-6">
                        <a href="#" class="hover:text-white transition-colors">Privacy</a>
                        <a href="#" class="hover:text-white transition-colors">Terms</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Authentication Core -->
        <div class="w-full lg:w-2/5 flex items-center justify-center p-8 bg-white overflow-y-auto">
            <div class="w-full max-w-sm space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-1000">
                <!-- Mobile Header -->
                <div class="lg:hidden flex items-center gap-4 mb-4">
                     <div class="size-10 bg-slate-900 rounded-xl flex items-center justify-center">
                        <ShoppingCart :size="20" class="text-white" />
                    </div>
                    <span class="text-xl font-black italic uppercase italic tracking-tighter">Modern<span class="text-indigo-600">POS</span></span>
                </div>

                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-slate-100 rounded-lg text-slate-500">
                        <Lock :size="12" class="font-black" />
                        <span class="text-[10px] font-black uppercase tracking-widest">Secure Gateway</span>
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight leading-none">
                        Welcome Back.
                    </h1>
                    <p class="text-slate-500 font-medium">
                        Identity verification required to access your workspace.
                    </p>
                </div>

                <!-- Status Feedback -->
                <transition name="fade">
                    <div v-if="status" class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4">
                        <div class="size-8 bg-emerald-500 rounded-full flex items-center justify-center shadow-lg shadow-emerald-200">
                            <CheckCircle2 :size="16" class="text-white" />
                        </div>
                        <span class="text-emerald-800 text-sm font-bold">{{ status }}</span>
                    </div>
                </transition>

                <!-- Error Feedback -->
                <transition name="fade">
                    <div v-if="Object.keys(form.errors).length > 0" class="p-5 bg-red-50 border border-red-100 rounded-2xl flex items-start gap-4 animate-in fade-in slide-in-from-top-4">
                        <div class="size-10 bg-red-500 rounded-xl flex items-center justify-center shadow-lg shadow-red-200 shrink-0 mt-0.5">
                            <ShieldAlert :size="20" class="text-white" />
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-black text-red-900 uppercase tracking-tighter italic">Access Denied</h4>
                            <ul class="list-none p-0 m-0">
                                <li v-for="error in form.errors" :key="error" class="text-red-700 text-xs font-bold leading-relaxed">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </transition>

                <!-- Forms Core -->
                <form @submit.prevent="submit" class="space-y-8">
                    <input type="hidden" name="_token" :value="csrfToken" v-if="csrfToken">

                    <!-- Field: Email -->
                    <div class="space-y-3 group">
                        <Label for="email" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">
                            Auth Identity (Email)
                        </Label>
                        <div class="relative">
                            <Mail class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                placeholder="name@company.com"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Field: Password -->
                    <div class="space-y-3 group">
                        <div class="flex items-center justify-between ml-1">
                            <Label for="password" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors">
                                Access Key
                            </Label>
                            <Link v-if="canResetPassword" href="/forgot-password" class="text-[10px] font-black uppercase tracking-widest text-indigo-500 hover:text-indigo-700 transition-colors">
                                Reset Key?
                            </Link>
                        </div>
                        <div class="relative">
                            <Lock class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                placeholder="••••••••"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold"
                            />
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                         <div class="flex items-center space-x-3 group cursor-pointer">
                            <Checkbox id="remember" v-model:checked="form.remember" class="size-5 border-2 rounded-lg border-slate-200 data-[state=checked]:bg-indigo-600 data-[state=checked]:border-indigo-600" />
                            <Label for="remember" class="text-xs font-bold text-slate-500 group-hover:text-slate-900 cursor-pointer transition-colors uppercase tracking-widest">Persistent Session</Label>
                        </div>
                    </div>

                    <!-- Action -->
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-16 bg-slate-900 hover:bg-slate-800 text-white rounded-[1.25rem] shadow-2xl shadow-slate-200 transition-all hover:scale-[1.02] active:scale-95 group overflow-hidden relative"
                    >
                         <div v-if="form.processing" class="flex items-center gap-3">
                            <Loader2 class="size-5 animate-spin" />
                            <span class="text-xs font-black uppercase tracking-widest">Authorizing...</span>
                        </div>
                        <div v-else class="flex items-center justify-center gap-3 w-full">
                            <span class="text-xs font-black uppercase tracking-widest">Sign In to Dashboard</span>
                            <ArrowUpRight class="size-4 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" />
                        </div>
                    </Button>
                </form>

                <!-- Navigation -->
                <div class="pt-10 border-t border-slate-100 space-y-6">
                    <div class="text-center space-y-4">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">New to the platform?</p>
                        <Link 
                            href="/register-business" 
                            class="inline-flex items-center justify-center gap-3 w-full h-14 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/30 text-slate-900 font-bold text-xs transition-all uppercase tracking-widest"
                        >
                            <Building2 class="size-4 text-indigo-500" />
                            Launch Your Business
                        </Link>
                    </div>
                </div>

                <div v-if="!csrfToken" class="p-3 bg-red-50 rounded-xl border border-red-100 text-[10px] font-bold text-red-600 uppercase tracking-widest text-center">
                    Critical Error: Sec Token Missing. <br> Reload Immediately.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.grid-background {
    background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 40px 40px;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
