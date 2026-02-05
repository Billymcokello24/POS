<script setup lang="ts">
import { Head, Link, Form } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Mail,
    Key,
    CheckCircle2,
    ShieldCheck,
    ArrowUpRight,
    Loader2
} from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-slate-50 font-sans flex text-slate-900 selection:bg-indigo-500 selection:text-white">
        <!-- Visual Side -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-[#0A0C1B]">
            <div class="absolute inset-0 z-10 bg-gradient-to-br from-[#0A0C1B] via-transparent to-indigo-900/30"></div>

            <!-- Animated Background Grid -->
            <div class="absolute inset-0 grid-background opacity-20"></div>

            <img
                src="https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=2070&auto=format&fit=crop"
                class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay scale-105"
            />
             <div class="absolute inset-0 bg-gradient-to-t from-[#0A0C1B] to-transparent"></div>

            <div class="relative z-10 w-full h-full flex flex-col justify-between p-16">
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

                <div class="max-w-md space-y-10">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-white/5 border border-white/10 backdrop-blur-md rounded-full">
                            <ShieldCheck :size="14" class="text-indigo-400" />
                            <span class="text-[10px] font-black text-white/90 uppercase tracking-widest">Account Recovery</span>
                        </div>
                        <h2 class="text-5xl font-black text-white leading-[0.9] tracking-tighter">
                            Security First. Always.
                        </h2>
                    </div>

                    <p class="text-white/60 text-lg font-medium leading-relaxed">
                        Lost your access key? No problem. Our encrypted recovery protocol ensures you get back to managing your business quickly and safely.
                    </p>
                </div>

                <!-- Footer Signage -->
                <div class="flex items-center justify-between text-white/30 text-[10px] font-black uppercase tracking-[0.2em]">
                    <span>© 2026 ModernPOS Systems</span>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white overflow-y-auto">
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
                        <Key :size="12" class="font-black" />
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-700">Credential Recovery</span>
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight leading-none">
                        Forgot Access Key?
                    </h1>
                    <p class="text-slate-500 font-medium">
                        Enter your identity (email) and we'll transmit a secure recovery link.
                    </p>
                </div>

                <!-- Status Feedback -->
                <transition name="fade">
                    <div v-if="status" class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4">
                        <div class="size-8 bg-emerald-500 rounded-full flex items-center justify-center shadow-lg shadow-emerald-200">
                            <CheckCircle2 :size="16" class="text-white" />
                        </div>
                        <span class="text-emerald-800 text-sm font-bold">{{ status }}</span>
                    </div>
                </transition>

                <Form v-bind="email.form()" v-slot="{ errors, processing }" class="space-y-8">
                    <div class="space-y-3 group">
                        <Label for="email" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 group-focus-within:text-slate-900 transition-colors ml-1">Identity (Email)</Label>
                        <div class="relative">
                            <Mail class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                placeholder="name@company.com"
                                class="h-14 pl-12 rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/5 transition-all font-bold text-slate-800"
                            />
                        </div>
                        <InputError :message="errors.email" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-full h-16 bg-slate-900 hover:bg-slate-800 text-white rounded-[1.25rem] shadow-2xl shadow-slate-200 transition-all hover:scale-[1.02] active:scale-95 group overflow-hidden relative"
                    >
                         <div v-if="processing" class="flex items-center gap-3">
                            <Loader2 class="size-5 animate-spin" />
                            <span class="text-xs font-black uppercase tracking-widest">Processing...</span>
                        </div>
                        <div v-else class="flex items-center justify-center gap-3 w-full">
                            <span class="text-xs font-black uppercase tracking-widest">Transmit Recovery Link</span>
                            <ArrowUpRight class="size-4 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" />
                        </div>
                    </Button>

                    <div class="pt-8 border-t border-slate-100 text-center">
                        <Link :href="login.url()" class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] hover:text-indigo-600 transition-colors">
                            Remember your access key? <span class="text-indigo-600 ml-1">Return to Login</span>
                        </Link>
                    </div>
                </Form>
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
