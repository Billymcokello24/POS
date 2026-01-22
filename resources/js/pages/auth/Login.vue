<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { register } from '@/routes'
import { store } from '@/routes/login'
import { request } from '@/routes/password'
import { ShoppingCart, Mail, Lock, ArrowRight } from 'lucide-vue-next'

defineProps<{
    status?: string
    canResetPassword: boolean
    canRegister: boolean
}>()
</script>

<template>
    <Head title="Sign In - Modern POS" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 flex items-center justify-center p-6">
        <div class="w-full max-w-6xl grid lg:grid-cols-2 gap-8 items-center">
            <!-- Left Side - Branding -->
            <div class="hidden lg:block">
                <div class="bg-gradient-to-br from-blue-600 to-indigo-600 rounded-3xl p-12 text-white shadow-2xl">
                    <div class="flex items-center space-x-3 mb-8">
                        <div class="w-14 h-14 bg-white/20 backdrop-blur-lg rounded-xl flex items-center justify-center">
                            <ShoppingCart :size="32" class="text-white" />
                        </div>
                        <span class="text-3xl font-bold">Modern POS</span>
                    </div>

                    <h1 class="text-5xl font-bold mb-6 leading-tight">
                        Welcome Back!
                    </h1>

                    <p class="text-xl text-blue-100 leading-relaxed mb-8">
                        Manage your business with ease. Track sales, inventory, and customers all in one place.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-sm">✓</span>
                            </div>
                            <p class="text-blue-50">Lightning-fast sales processing</p>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-sm">✓</span>
                            </div>
                            <p class="text-blue-50">Real-time inventory tracking</p>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-sm">✓</span>
                            </div>
                            <p class="text-blue-50">Detailed business analytics</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12">
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center justify-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                        <ShoppingCart :size="24" class="text-white" />
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                        Modern POS
                    </span>
                </div>

                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Sign in to your account</h2>
                    <p class="text-gray-600">Enter your credentials to access your dashboard</p>
                </div>

                <div
                    v-if="status"
                    class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm font-medium text-green-700"
                >
                    {{ status }}
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <!-- Email Field -->
                    <div class="space-y-2">
                        <Label for="email" class="text-gray-900 font-medium">Email Address</Label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <Mail :size="20" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="you@example.com"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.email" />
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="password" class="text-gray-900 font-medium">Password</Label>
                            <Link
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <Lock :size="20" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.password" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" />
                        <Label for="remember" class="text-gray-700 font-normal cursor-pointer">
                            Keep me signed in
                        </Label>
                    </div>

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        class="w-full h-12 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        <span v-if="!processing" class="flex items-center justify-center">
                            Sign In
                            <ArrowRight :size="20" class="ml-2" />
                        </span>
                        <span v-else>Signing in...</span>
                    </Button>
                </Form>

                <!-- Register Link -->
                <div v-if="canRegister" class="mt-8 text-center">
                    <p class="text-gray-600">
                        Don't have an account?
                        <Link
                            :href="register()"
                            class="font-semibold text-blue-600 hover:text-blue-700 ml-1"
                        >
                            Create a free account
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
    font-family: 'Inter', sans-serif;
}
</style>

