<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ShoppingCart, LayoutGrid, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { login } from '@/routes'

const form = useForm({
    business_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/register-business', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Register Business - Modern POS">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-[#F8F9FA] font-inter flex flex-col md:flex-row selection:bg-[#3B82F6] selection:text-white">
        <!-- Visual Side -->
        <div class="hidden md:flex w-1/2 bg-[#0E1129] relative overflow-hidden items-center justify-center p-16 text-white">
            <div class="absolute inset-0 z-0">
                <img 
                    src="https://images.unsplash.com/photo-1497366216548-37526070297c?q=80&w=2069&auto=format&fit=crop" 
                    class="w-full h-full object-cover opacity-20"
                />
                 <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] to-transparent"></div>
            </div>
            
            <div class="relative z-10 max-w-lg">
                <div class="w-16 h-16 bg-[#3B82F6] rounded-2xl flex items-center justify-center mb-8 shadow-xl shadow-[#3B82F6]/20">
                    <LayoutGrid :size="32" class="text-white" />
                </div>
                <h2 class="text-5xl font-bold tracking-tight mb-6">Build your <br> retail empire.</h2>
                <p class="text-lg opacity-60 leading-relaxed mb-12">
                    Create a dedicated workspace for your business. Manage multiple locations, thousands of products, and your entire staff from one central command center.
                </p>
                
                <div class="space-y-4">
                    <div class="flex items-center gap-4 opacity-80">
                        <CheckCircle2 :size="20" class="text-[#10B981]" />
                        <span class="font-medium">Free 14-day Enterprise Trial</span>
                    </div>
                    <div class="flex items-center gap-4 opacity-80">
                        <CheckCircle2 :size="20" class="text-[#10B981]" />
                        <span class="font-medium">No credit card required</span>
                    </div>
                     <div class="flex items-center gap-4 opacity-80">
                        <CheckCircle2 :size="20" class="text-[#10B981]" />
                         <span class="font-medium">Instant workspace provisioning</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="w-full md:w-1/2 flex items-center justify-center p-8 bg-white">
            <div class="w-full max-w-md">
                 <Link href="/" class="flex items-center gap-4 mb-12">
                    <div class="w-10 h-10 bg-[#0E1129] text-white flex items-center justify-center rounded-lg">
                        <ShoppingCart :size="20" stroke-width="2.5" />
                    </div>
                    <span class="text-xl font-bold tracking-tight">MODERN<span class="text-[#3B82F6]">POS</span></span>
                </Link>

                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-[#0E1129] mb-2">Create Workspace</h1>
                    <p class="text-[#0E1129]/60">Enter your business details to get started.</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label for="business_name" class="text-xs font-bold uppercase tracking-widest text-[#0E1129]">Business Name</Label>
                        <Input
                            id="business_name"
                            v-model="form.business_name"
                            type="text"
                            required
                            autofocus
                            placeholder="e.g. Acme Retail Ltd"
                            class="h-12 bg-[#F8F9FA] border-transparent focus:bg-white focus:border-[#3B82F6] transition-all"
                        />
                        <InputError :message="form.errors.business_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="name" class="text-xs font-bold uppercase tracking-widest text-[#0E1129]">Admin Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Your full name"
                            class="h-12 bg-[#F8F9FA] border-transparent focus:bg-white focus:border-[#3B82F6] transition-all"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                     <div class="space-y-2">
                        <Label for="email" class="text-xs font-bold uppercase tracking-widest text-[#0E1129]">Work Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="name@company.com"
                            class="h-12 bg-[#F8F9FA] border-transparent focus:bg-white focus:border-[#3B82F6] transition-all"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="password" class="text-xs font-bold uppercase tracking-widest text-[#0E1129]">Password</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                class="h-12 bg-[#F8F9FA] border-transparent focus:bg-white focus:border-[#3B82F6] transition-all"
                            />
                        </div>
                        <div class="space-y-2">
                             <Label for="password_confirmation" class="text-xs font-bold uppercase tracking-widest text-[#0E1129]">Confirm</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                class="h-12 bg-[#F8F9FA] border-transparent focus:bg-white focus:border-[#3B82F6] transition-all"
                            />
                        </div>
                    </div>
                    <InputError :message="form.errors.password" />

                    <Button
                        type="submit"
                        class="w-full h-14 bg-[#0E1129] hover:bg-[#3B82F6] text-white text-xs font-bold uppercase tracking-[0.2em] rounded-xl shadow-xl transition-all hover:scale-[1.02] active:scale-95 duration-300 mt-4"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="mr-2" />
                        <span v-if="!form.processing">Launch Workspace</span>
                    </Button>

                    <div class="text-center pt-4">
                        <Link :href="login.url()" class="text-sm font-medium text-[#0E1129]/60 hover:text-[#3B82F6] transition-colors">
                            Already have a workspace? Login
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
