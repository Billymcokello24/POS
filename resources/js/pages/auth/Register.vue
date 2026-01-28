<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ShoppingCart, User, Mail, Lock, ArrowRight, CheckCircle } from 'lucide-vue-next'

import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { login } from '@/routes'
</script>

<template>
    <Head title="Create Account - Modern POS" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 flex items-center justify-center p-6">
        <div class="w-full max-w-6xl grid lg:grid-cols-2 gap-8 items-center">
            <!-- Left Side - Branding -->
            <div class="hidden lg:block">
                <div class="bg-gradient-to-br from-indigo-600 to-purple-600 rounded-3xl p-12 text-white shadow-2xl">
                    <div class="flex items-center space-x-3 mb-8">
                        <div class="w-14 h-14 bg-white/20 backdrop-blur-lg rounded-xl flex items-center justify-center">
                            <ShoppingCart :size="32" class="text-white" />
                        </div>
                        <span class="text-3xl font-bold">Modern POS</span>
                    </div>

                    <h1 class="text-5xl font-bold mb-6 leading-tight">
                        Start Your Free Trial Today!
                    </h1>

                    <p class="text-xl text-indigo-100 leading-relaxed mb-8">
                        Join thousands of businesses using Modern POS to streamline operations and increase sales.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <CheckCircle :size="24" class="text-green-300 flex-shrink-0 mt-1" />
                            <div>
                                <p class="font-semibold text-white">No credit card required</p>
                                <p class="text-indigo-100 text-sm">Start using immediately, upgrade when ready</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <CheckCircle :size="24" class="text-green-300 flex-shrink-0 mt-1" />
                            <div>
                                <p class="font-semibold text-white">14-day free trial</p>
                                <p class="text-indigo-100 text-sm">Full access to all premium features</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <CheckCircle :size="24" class="text-green-300 flex-shrink-0 mt-1" />
                            <div>
                                <p class="font-semibold text-white">24/7 customer support</p>
                                <p class="text-indigo-100 text-sm">Our team is here to help you succeed</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <CheckCircle :size="24" class="text-green-300 flex-shrink-0 mt-1" />
                            <div>
                                <p class="font-semibold text-white">Easy setup in minutes</p>
                                <p class="text-indigo-100 text-sm">Get started without technical knowledge</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Registration Form -->
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
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Create your account</h2>
                    <p class="text-gray-600">Get started with your free trial today</p>
                </div>

                <Form
                    action="/register"
                    method="post"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <!-- Name Field -->
                    <div class="space-y-2">
                        <Label for="name" class="text-gray-900 font-medium">Full Name</Label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <User :size="20" />
                            </div>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="John Doe"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.name" />
                    </div>

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
                                autocomplete="email"
                                placeholder="you@example.com"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.email" />
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2">
                        <Label for="password" class="text-gray-900 font-medium">Password</Label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <Lock :size="20" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.password" />
                        <p class="text-xs text-gray-500">Must be at least 8 characters</p>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="space-y-2">
                        <Label for="password_confirmation" class="text-gray-900 font-medium">Confirm Password</Label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <Lock :size="20" />
                            </div>
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="pl-12 h-12 border-gray-200 focus:border-blue-600 focus:ring-blue-600"
                            />
                        </div>
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <!-- Terms -->
                    <p class="text-xs text-gray-500">
                        By creating an account, you agree to our
                        <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">Terms of Service</a>
                        and
                        <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">Privacy Policy</a>
                    </p>

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        class="w-full h-12 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        <span v-if="!processing" class="flex items-center justify-center">
                            Create Account
                            <ArrowRight :size="20" class="ml-2" />
                        </span>
                        <span v-else>Creating your account...</span>
                    </Button>
                </Form>

                <!-- Login Link -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Already have an account?
                        <Link
                            :href="login()"
                            class="font-semibold text-blue-600 hover:text-blue-700 ml-1"
                        >
                            Sign in instead
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

