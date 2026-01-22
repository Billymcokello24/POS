<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { dashboard, login, register } from '@/routes'
import { ShoppingCart, BarChart3, Package, Users, TrendingUp, Shield, Zap, Clock } from 'lucide-vue-next'

withDefaults(
    defineProps<{
        canRegister: boolean
    }>(),
    {
        canRegister: true,
    },
)
</script>

<template>
    <Head title="Welcome to POS System">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <!-- Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-200/50">
            <div class="mx-auto w-[90%] px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                            <ShoppingCart :size="24" class="text-white" />
                        </div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                            Modern POS
                        </span>
                    </div>

                    <div class="flex items-center gap-4">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-medium hover:shadow-lg hover:scale-105 transition-all duration-200"
                        >
                            Go to Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="px-5 py-2.5 text-gray-700 hover:text-blue-600 font-medium transition-colors"
                            >
                                Sign In
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-medium hover:shadow-lg hover:scale-105 transition-all duration-200"
                            >
                                Get Started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <div class="pt-32 pb-20 px-6">
            <div class="mx-auto w-[90%]">
                <div class="text-center space-y-8">
                    <div class="inline-block px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm font-medium mb-4">
                        🚀 Transform Your Business Operations
                    </div>

                    <h1 class="text-6xl md:text-7xl font-bold text-gray-900 leading-tight">
                        The Complete
                        <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                            Point of Sale
                        </span>
                        <br />
                        System for Modern Businesses
                    </h1>

                    <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                        Streamline your sales, inventory, and customer management with our powerful,
                        easy-to-use POS system. Built for speed, designed for growth.
                    </p>

                    <div class="flex items-center justify-center gap-4 pt-6">
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold text-lg hover:shadow-2xl hover:scale-105 transition-all duration-200"
                        >
                            Start Free Trial
                        </Link>
                        <Link
                            :href="login()"
                            class="px-8 py-4 bg-white text-gray-900 rounded-xl font-semibold text-lg border-2 border-gray-200 hover:border-blue-600 hover:shadow-lg transition-all duration-200"
                        >
                            Watch Demo
                        </Link>
                    </div>

                    <div class="flex items-center justify-center gap-8 text-sm text-gray-500 pt-6">
                        <div class="flex items-center gap-2">
                            <Shield :size="16" class="text-green-500" />
                            <span>No credit card required</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Zap :size="16" class="text-yellow-500" />
                            <span>Setup in minutes</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock :size="16" class="text-blue-500" />
                            <span>24/7 support</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="py-20 px-6 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">
                        Everything You Need to Succeed
                    </h2>
                    <p class="text-xl text-gray-600">
                        Powerful features that help you sell more and work smarter
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="p-8 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center mb-6">
                            <ShoppingCart :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Fast Sales Processing</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Lightning-fast checkout with barcode scanning, multiple payment methods,
                            and instant receipt generation.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-8 bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-600 to-pink-600 rounded-xl flex items-center justify-center mb-6">
                            <Package :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Inventory Management</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Track stock levels in real-time, set low-stock alerts, and manage
                            products across multiple locations.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-8 bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-600 to-emerald-600 rounded-xl flex items-center justify-center mb-6">
                            <BarChart3 :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Advanced Reports</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Get insights with detailed sales reports, inventory analytics,
                            and profit tracking.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="p-8 bg-gradient-to-br from-orange-50 to-red-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-orange-600 to-red-600 rounded-xl flex items-center justify-center mb-6">
                            <Users :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Customer Management</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Build customer relationships with profiles, purchase history,
                            and loyalty programs.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="p-8 bg-gradient-to-br from-cyan-50 to-blue-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-cyan-600 to-blue-600 rounded-xl flex items-center justify-center mb-6">
                            <TrendingUp :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Business Growth</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Scale your business with multi-location support, user roles,
                            and advanced permissions.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="p-8 bg-gradient-to-br from-yellow-50 to-amber-50 rounded-2xl hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="w-14 h-14 bg-gradient-to-br from-yellow-600 to-amber-600 rounded-xl flex items-center justify-center mb-6">
                            <Shield :size="28" class="text-white" />
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Secure & Reliable</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Bank-level security, automatic backups, and 99.9% uptime
                            guarantee for your peace of mind.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="py-20 px-6 bg-gradient-to-r from-blue-600 to-indigo-600">
            <div class="mx-auto w-[90%] text-center">
                <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
                    Ready to Transform Your Business?
                </h2>
                <p class="text-xl text-blue-100 mb-10">
                    Join thousands of businesses already using our POS system to streamline operations and boost sales.
                </p>
                <div class="flex items-center justify-center gap-4">
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="px-8 py-4 bg-white text-blue-600 rounded-xl font-semibold text-lg hover:shadow-2xl hover:scale-105 transition-all duration-200"
                    >
                        Get Started Free
                    </Link>
                    <Link
                        :href="login()"
                        class="px-8 py-4 bg-blue-500 text-white rounded-xl font-semibold text-lg hover:bg-blue-400 transition-all duration-200"
                    >
                        Sign In
                    </Link>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="py-12 px-6 bg-gray-900 text-gray-400">
            <div class="mx-auto w-[90%]">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                            <ShoppingCart :size="24" class="text-white" />
                        </div>
                        <span class="text-xl font-bold text-white">Modern POS</span>
                    </div>
                    <p class="text-sm">
                        © 2026 Modern POS System. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
* {
    font-family: 'Inter', sans-serif;
}
</style>

