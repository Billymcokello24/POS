<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ShoppingCart,
    CheckCircle2,
    Sparkles,
    Building2,
    ArrowUpRight,
    Store,
    User,
    Mail,
    Lock,
    Loader2
} from 'lucide-vue-next'
import { ref } from 'vue'

import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { login } from '@/routes'

// Preloader state
const showPreloader = ref(false)

const form = useForm({
    business_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/register-business', {
        onSuccess: () => {
            // Show preloader for 5 seconds
            showPreloader.value = true

            // Redirect to dashboard after 5 seconds
            setTimeout(() => {
                window.location.href = '/dashboard'
            }, 5000)
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Register Business - Modern POS">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <!-- Preloader Overlay -->
    <div v-if="showPreloader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 overflow-hidden">
        <!-- Animated background particles -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-indigo-500 rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
            <div class="absolute top-1/3 right-1/4 w-64 h-64 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
            <div class="absolute bottom-1/4 left-1/3 w-64 h-64 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
        </div>

        <!-- Main logo container with zoom animation -->
        <div class="relative z-10 flex flex-col items-center gap-8">
            <!-- Logo with zoom in/out animation -->
            <div class="animate-zoom-pulse">
                <svg
                    class="w-32 h-32 text-white drop-shadow-2xl"
                    viewBox="0 0 200 200"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <circle cx="100" cy="100" r="90" fill="currentColor" fill-opacity="0.1" />
                    <circle cx="100" cy="100" r="70" stroke="currentColor" stroke-width="3" />
                    <path d="M60 80 L60 120 L80 100 L100 120 L100 80" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M120 80 L120 120 M120 80 L140 80 Q150 80 150 95 Q150 110 140 110 L120 110" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>

            <!-- ModernPOS Text -->
            <div class="text-center space-y-2">
                <h1 class="text-5xl font-black text-white tracking-tight animate-fade-in">
                    Modern<span class="text-indigo-400">POS</span>
                </h1>
                <p class="text-indigo-200 text-sm font-medium tracking-wider uppercase animate-fade-in animation-delay-500">
                    Point of Sale System
                </p>
            </div>

            <!-- Loading dots -->
            <div class="flex gap-2 animate-fade-in animation-delay-1000">
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce"></div>
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce animation-delay-200"></div>
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce animation-delay-400"></div>
            </div>
        </div>
    </div>

    <div class="min-h-screen bg-slate-50 font-sans flex text-slate-900 selection:bg-indigo-500 selection:text-white">
        <!-- Visual Side -->
        <div class="hidden lg:flex lg:w-3/5 relative overflow-hidden bg-[#0A0C1B]">
            <div class="absolute inset-0 z-10 bg-gradient-to-br from-[#0A0C1B] via-transparent to-indigo-900/30"></div>

            <!-- Animated Background Grid -->
            <div class="absolute inset-0 grid-background opacity-20"></div>

            <img
                src="https://images.unsplash.com/photo-1497366216548-37526070297c?q=80&w=2069&auto=format&fit=crop"
                class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay scale-105"
            />
             <div class="absolute inset-0 bg-gradient-to-t from-[#0A0C1B] to-transparent"></div>

            <div class="relative z-10 w-full h-full flex flex-col justify-between p-20">
                <!-- Branding -->
                <Link href="/" class="flex items-center gap-4 group">
                    <div class="size-12 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 group-hover:rotate-6 transition-transform duration-500 shadow-2xl">
                        <ShoppingCart :size="24" class="text-white" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-white tracking-tighter leading-none italic uppercase leading-none">
                            Modern<span class="text-indigo-400">POS</span>
                        </span>
                        <span class="text-[9px] font-black text-white/40 uppercase tracking-[0.3em] leading-none mt-1">Enterprise Suite</span>
                    </div>
                </Link>

                <div class="max-w-xl space-y-10">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-white/5 border border-white/10 backdrop-blur-md rounded-full">
                            <Sparkles :size="14" class="text-indigo-400" />
                            <span class="text-[10px] font-black text-white/90 uppercase tracking-widest">Provisioning System</span>
                        </div>
                        <h2 class="text-6xl font-black text-white leading-[0.9] tracking-tighter">
                            Build Your <br>
                            <span class="bg-gradient-to-r from-indigo-400 to-blue-300 bg-clip-text text-transparent">Retail Empire.</span>
                        </h2>
                    </div>

                    <p class="text-white/60 text-lg font-medium leading-relaxed">
                        Create a dedicated workspace for your business. Manage multiple locations, thousands of products, and your entire staff from one central command center.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-center gap-4 text-white/80">
                            <div class="size-6 bg-emerald-500/20 rounded-full flex items-center justify-center border border-emerald-500/30">
                                <CheckCircle2 :size="14" class="text-emerald-400 font-bold" />
                            </div>
                            <span class="text-sm font-bold uppercase tracking-widest leading-none">Free 14-day Enterprise Trial</span>
                        </div>
                        <div class="flex items-center gap-4 text-white/80">
                            <div class="size-6 bg-emerald-500/20 rounded-full flex items-center justify-center border border-emerald-500/30">
                                <CheckCircle2 :size="14" class="text-emerald-400 font-bold" />
                            </div>
                            <span class="text-sm font-bold uppercase tracking-widest leading-none">No credit card required</span>
                        </div>
                         <div class="flex items-center gap-4 text-white/80">
                            <div class="size-6 bg-emerald-500/20 rounded-full flex items-center justify-center border border-emerald-500/30">
                                <CheckCircle2 :size="14" class="text-emerald-400 font-bold" />
                            </div>
                             <span class="text-sm font-bold uppercase tracking-widest leading-none">Instant workspace provisioning</span>
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

        <!-- Form Side -->
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
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 rounded-lg text-indigo-600">
                        <Building2 :size="12" class="font-black" />
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-700">New Business Registration</span>
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight leading-none">
                        Launch Workspace.
                    </h1>
                    <p class="text-slate-500 font-medium">
                        Enter your business details to get started.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-8">
                    <div class="space-y-3 group">
                        <Label for="business_name" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Company/Business Name</Label>
                        <div class="relative">
                            <Store class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="business_name"
                                v-model="form.business_name"
                                type="text"
                                required
                                autofocus
                                placeholder="Acme Retail Ltd"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                            />
                        </div>
                        <InputError :message="form.errors.business_name" />
                    </div>

                    <div class="space-y-3 group">
                        <Label for="name" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Administrator Full Name</Label>
                        <div class="relative">
                            <User class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Alex Mercer"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                            />
                        </div>
                        <InputError :message="form.errors.name" />
                    </div>

                     <div class="space-y-3 group">
                        <Label for="email" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Corporate Email Address</Label>
                        <div class="relative">
                            <Mail class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="name@company.com"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3 group">
                            <Label for="password" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Password</Label>
                            <div class="relative">
                                <Lock class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                                />
                            </div>
                        </div>
                        <div class="space-y-3 group">
                             <Label for="password_confirmation" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Verify</Label>
                            <div class="relative">
                                <Lock class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    required
                                    class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                                />
                            </div>
                        </div>
                    </div>
                    <InputError :message="form.errors.password" />

                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-16 bg-slate-900 hover:bg-slate-800 text-white rounded-[1.25rem] shadow-2xl shadow-slate-200 transition-all hover:scale-[1.02] active:scale-95 group overflow-hidden relative"
                    >
                         <div v-if="form.processing" class="flex items-center gap-3">
                            <Loader2 class="size-5 animate-spin" />
                            <span class="text-xs font-black uppercase tracking-widest">Provisioning...</span>
                        </div>
                        <div v-else class="flex items-center justify-center gap-3 w-full">
                            <span class="text-xs font-black uppercase tracking-widest">Create Workspace</span>
                            <ArrowUpRight class="size-4 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" />
                        </div>
                    </Button>

                    <div class="pt-8 border-t border-slate-100 text-center">
                        <Link :href="login.url()" class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] hover:text-indigo-600 transition-colors">
                            Already have an enterprise workspace? <span class="text-indigo-600 ml-1">Sign In</span>
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.grid-background {
    background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 40px 40px;
}

/* Preloader Animations */
@keyframes zoom-pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
}

@keyframes blob {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(30px, -50px) scale(1.1);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-zoom-pulse {
    animation: zoom-pulse 2s ease-in-out infinite;
}

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 0.2s;
}

.animation-delay-400 {
    animation-delay: 0.4s;
}

.animation-delay-500 {
    animation-delay: 0.5s;
}

.animation-delay-1000 {
    animation-delay: 1s;
}
</style>
