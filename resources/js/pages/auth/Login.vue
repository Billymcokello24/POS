<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3'
import { ShoppingCart, Mail, Lock, ArrowRight, CheckCircle2, ShieldCheck, ArrowUpRight } from 'lucide-vue-next'

import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { store } from '@/routes/login'
import { request } from '@/routes/password'

defineProps<{
    status?: string
    canResetPassword: boolean
    canRegister: boolean
}>()
</script>

<template>
    <Head title="Client Access - Modern POS">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-[#F8F9FA] font-inter flex selection:bg-[#3B82F6] selection:text-white">
        <!-- Left Side - Visual -->
        <div class="hidden lg:block lg:w-1/2 relative overflow-hidden bg-[#0E1129]">
             <img 
                src="https://images.unsplash.com/photo-1556742111-a301076d9d18?q=80&w=2070&auto=format&fit=crop" 
                alt="Secure Retail Operations" 
                class="absolute inset-0 w-full h-full object-cover opacity-100"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] via-[#0E1129]/40 to-transparent"></div>
            
            <!-- Animated Aura Glows -->
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-[#3B82F6]/20 rounded-full blur-[100px] -translate-y-1/2 translate-x-1/2 animate-blob mix-blend-screen"></div>
            <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-[#10B981]/10 rounded-full blur-[80px] translate-y-1/2 -translate-x-1/4 animate-blob animation-delay-2000 mix-blend-screen"></div>

            <div class="relative z-10 h-full flex flex-col justify-between p-16">
                 <Link href="/" class="flex items-center gap-4 text-white group">
                    <div class="w-10 h-10 bg-white text-[#0E1129] flex items-center justify-center rounded-lg group-hover:bg-[#3B82F6] group-hover:text-white transition-colors duration-300">
                        <ShoppingCart :size="20" stroke-width="2.5" />
                    </div>
                    <span class="text-xl font-bold tracking-tight">MODERN<span class="text-[#3B82F6] group-hover:text-white transition-colors">POS</span></span>
                 </Link>

                 <div class="space-y-8">
                     <h2 class="text-5xl font-extrabold text-white leading-tight tracking-tight">
                         Secure access to <br> your <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-white/40">store command center.</span>
                     </h2>
                     <div class="flex gap-4">
                         <div class="flex items-center gap-3 px-5 py-3 rounded-full bg-white/5 backdrop-blur-md border border-white/10 text-white/90 text-xs font-bold uppercase tracking-widest hover:bg-white/10 transition-colors cursor-default">
                             <ShieldCheck :size="16" class="text-[#3B82F6]" />
                             End-to-End Encrypted
                         </div>
                         <div class="flex items-center gap-3 px-5 py-3 rounded-full bg-white/5 backdrop-blur-md border border-white/10 text-white/90 text-xs font-bold uppercase tracking-widest hover:bg-white/10 transition-colors cursor-default">
                             <ArrowUpRight :size="16" class="text-[#3B82F6]" />
                             Real-time Sync
                         </div>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white/50">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-12">
                     <div class="flex items-center gap-4 text-[#0E1129]">
                        <div class="w-10 h-10 bg-[#0E1129] text-white flex items-center justify-center rounded-lg">
                            <ShoppingCart :size="20" stroke-width="2.5" />
                        </div>
                        <span class="text-xl font-bold tracking-tight">MODERN<span class="text-[#3B82F6]">POS</span></span>
                    </div>
                </div>

                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold text-[#0E1129] tracking-tight mb-3">Client Access</h1>
                    <p class="text-[#0E1129]/60 font-medium">Enter your credentials to access the workspace.</p>
                </div>

                <div
                    v-if="status"
                    class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl text-sm font-bold tracking-wide text-emerald-800 flex items-center gap-3"
                >
                    <CheckCircle2 :size="18" />
                    {{ status }}
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="space-y-2">
                        <Label for="email" class="text-[#0E1129] text-xs font-bold uppercase tracking-widest ml-1">Work Email</Label>
                        <div class="relative group">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-[#0E1129]/40 group-focus-within:text-[#3B82F6] transition-colors">
                                <Mail :size="18" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="name@company.com"
                                class="pl-12 h-14 bg-white border-[#0E1129]/10 focus:border-[#3B82F6] focus:ring-0 rounded-xl text-[#0E1129] font-medium placeholder:text-[#0E1129]/30 transition-all shadow-sm focus:shadow-md"
                            />
                        </div>
                        <InputError :message="errors.email" />
                    </div>

                    <div class="space-y-2">
                         <div class="flex items-center justify-between ml-1">
                            <Label for="password" class="text-[#0E1129] text-xs font-bold uppercase tracking-widest">Passkey</Label>
                            <Link
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs font-bold text-[#3B82F6] uppercase tracking-widest hover:text-[#0E1129] transition-colors"
                            >
                                Recovery
                            </Link>
                        </div>
                        <div class="relative group">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-[#0E1129]/40 group-focus-within:text-[#3B82F6] transition-colors">
                                <Lock :size="18" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="pl-12 h-14 bg-white border-[#0E1129]/10 focus:border-[#3B82F6] focus:ring-0 rounded-xl text-[#0E1129] font-medium placeholder:text-[#0E1129]/30 transition-all shadow-sm focus:shadow-md"
                            />
                        </div>
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center space-x-3 pt-2">
                         <div class="flex items-center h-5">
                            <Checkbox id="remember" name="remember" class="border-[#0E1129]/20 data-[state=checked]:bg-[#3B82F6] data-[state=checked]:border-[#3B82F6] rounded" />
                        </div>
                        <Label for="remember" class="text-sm font-medium text-[#0E1129]/70 cursor-pointer select-none">
                            Keep session active for 30 days
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        class="w-full h-14 bg-[#0E1129] hover:bg-[#3B82F6] text-white text-xs font-bold uppercase tracking-[0.2em] rounded-xl shadow-xl transition-all hover:scale-[1.02] hover:-translate-y-1 active:scale-95 duration-300 mt-6"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        <span v-if="!processing">Authenticate Access</span>
                    </Button>
                </Form>

                <div class="mt-12 pt-8 border-t border-[#0E1129]/5">
                    <p class="text-xs font-bold uppercase tracking-widest text-[#0E1129]/40 mb-4 text-center">Demo Environment Access</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-white border border-[#0E1129]/5 rounded-xl text-center group hover:border-[#3B82F6]/30 transition-colors cursor-pointer shadow-sm">
                            <div class="text-[#0E1129] font-bold text-sm mb-1 group-hover:text-[#3B82F6]">Admin</div>
                            <div class="text-[#0E1129]/50 text-xs font-mono">admin@pos.com</div>
                        </div>
                        <div class="p-4 bg-white border border-[#0E1129]/5 rounded-xl text-center group hover:border-[#3B82F6]/30 transition-colors cursor-pointer shadow-sm">
                            <div class="text-[#0E1129] font-bold text-sm mb-1 group-hover:text-[#3B82F6]">Cashier</div>
                            <div class="text-[#0E1129]/50 text-xs font-mono">cashier@demo.com</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

.font-inter {
    font-family: 'Inter', sans-serif;
}

@keyframes blob {
    0% { transform: translate(0px, 0px) scale(1); }
    33% { transform: translate(30px, -50px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
    100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}
</style>
