<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    ShoppingCart,
    Package,
    DollarSign,
    TrendingUp,
    AlertTriangle,
    BarChart3,
    Sparkles,
    ArrowUpRight,
    ArrowDownRight,
    Clock
} from 'lucide-vue-next'

const stats = {
    todaySales: 1250.00,
    totalProducts: 150,
    lowStockItems: 8,
    todayOrders: 23,
    yesterdaySales: 1115.00,
    monthlyGrowth: 12.5
}

const recentSales = [
    { id: 'DS000023', items: 2, time: '5 mins ago', amount: 45.00, customer: 'Walk-in' },
    { id: 'DS000022', items: 1, time: '15 mins ago', amount: 750.00, customer: 'John Doe' },
    { id: 'DS000021', items: 3, time: '32 mins ago', amount: 125.50, customer: 'Walk-in' },
    { id: 'DS000020', items: 5, time: '1 hour ago', amount: 280.00, customer: 'Jane Smith' },
]

const lowStockProducts = [
    { name: 'Wireless Mouse', sku: 'ELEC-002', current: 8, min: 10 },
    { name: 'T-Shirt Blue', sku: 'CLOTH-001', current: 15, min: 20 },
    { name: 'Laptop HP ProBook', sku: 'ELEC-001', current: 4, min: 5 },
]
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-purple-50 via-pink-50 to-orange-50 p-6">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Hero Header -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500 p-8 text-white shadow-2xl">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
                    <div class="absolute bottom-0 left-0 w-96 h-96 bg-white/5 rounded-full -ml-48 -mb-48"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-2">
                            <Sparkles class="h-6 w-6" />
                            <span class="text-sm font-semibold uppercase tracking-wider">Welcome Back!</span>
                        </div>
                        <h1 class="text-5xl font-bold mb-3">Dashboard Overview</h1>
                        <p class="text-purple-100 text-lg mb-6">Here's what's happening with your business today</p>
                        <div class="flex gap-3">
                            <Button @click="router.visit('/sales/create')" class="bg-white text-purple-600 hover:bg-purple-50 gap-2">
                                <ShoppingCart class="h-4 w-4" />
                                New Sale
                            </Button>
                            <Button @click="router.visit('/reports')" variant="outline" class="border-white text-white hover:bg-white/20 gap-2">
                                <BarChart3 class="h-4 w-4" />
                                View Reports
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Today's Sales -->
                    <Card class="border-0 shadow-xl bg-white overflow-hidden relative group hover:shadow-2xl transition-all">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-100 to-emerald-200 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-slate-600">Today's Sales</CardTitle>
                                <div class="rounded-lg bg-emerald-100 p-2">
                                    <DollarSign class="h-5 w-5 text-emerald-600" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="relative z-10">
                            <div class="text-3xl font-bold text-slate-900">${{ stats.todaySales.toFixed(2) }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="flex items-center text-emerald-600 text-sm font-medium">
                                    <ArrowUpRight class="h-4 w-4" />
                                    <span>{{ ((stats.todaySales - stats.yesterdaySales) / stats.yesterdaySales * 100).toFixed(1) }}%</span>
                                </div>
                                <span class="text-slate-500 text-sm">vs yesterday</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Orders -->
                    <Card class="border-0 shadow-xl bg-white overflow-hidden relative group hover:shadow-2xl transition-all">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-slate-600">Orders Today</CardTitle>
                                <div class="rounded-lg bg-blue-100 p-2">
                                    <ShoppingCart class="h-5 w-5 text-blue-600" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="relative z-10">
                            <div class="text-3xl font-bold text-slate-900">{{ stats.todayOrders }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="flex items-center text-blue-600 text-sm font-medium">
                                    <ArrowUpRight class="h-4 w-4" />
                                    <span>8.3%</span>
                                </div>
                                <span class="text-slate-500 text-sm">from last week</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Products -->
                    <Card class="border-0 shadow-xl bg-white overflow-hidden relative group hover:shadow-2xl transition-all">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-slate-600">Products</CardTitle>
                                <div class="rounded-lg bg-purple-100 p-2">
                                    <Package class="h-5 w-5 text-purple-600" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="relative z-10">
                            <div class="text-3xl font-bold text-slate-900">{{ stats.totalProducts }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-purple-600 text-sm font-medium">Active</span>
                                <span class="text-slate-500 text-sm">in inventory</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Low Stock -->
                    <Card class="border-0 shadow-xl bg-white overflow-hidden relative group hover:shadow-2xl transition-all">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-red-100 to-red-200 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-slate-600">Low Stock</CardTitle>
                                <div class="rounded-lg bg-red-100 p-2">
                                    <AlertTriangle class="h-5 w-5 text-red-600" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="relative z-10">
                            <div class="text-3xl font-bold text-red-600">{{ stats.lowStockItems }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-red-600 text-sm font-medium">⚠️ Attention</span>
                                <span class="text-slate-500 text-sm">needed</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main Content Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Quick Actions -->
                    <Card class="border-0 shadow-xl bg-white lg:col-span-1">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Sparkles class="h-5 w-5 text-purple-600" />
                                Quick Actions
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button
                                @click="router.visit('/sales/create')"
                                class="w-full justify-start gap-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 h-auto py-4"
                            >
                                <ShoppingCart class="h-5 w-5" />
                                <div class="text-left">
                                    <div class="font-semibold">New Sale</div>
                                    <div class="text-xs opacity-80">Open POS terminal</div>
                                </div>
                            </Button>
                            <Button
                                @click="router.visit('/products/create')"
                                variant="outline"
                                class="w-full justify-start gap-3 h-auto py-4 border-2 hover:bg-slate-50"
                            >
                                <Package class="h-5 w-5" />
                                <div class="text-left">
                                    <div class="font-semibold">Add Product</div>
                                    <div class="text-xs text-slate-600">New inventory item</div>
                                </div>
                            </Button>
                            <Button
                                @click="router.visit('/reports')"
                                variant="outline"
                                class="w-full justify-start gap-3 h-auto py-4 border-2 hover:bg-slate-50"
                            >
                                <BarChart3 class="h-5 w-5" />
                                <div class="text-left">
                                    <div class="font-semibold">Analytics</div>
                                    <div class="text-xs text-slate-600">View detailed reports</div>
                                </div>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Recent Sales -->
                    <Card class="border-0 shadow-xl bg-white lg:col-span-2">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <Clock class="h-5 w-5 text-blue-600" />
                                    Recent Transactions
                                </CardTitle>
                                <Button variant="ghost" size="sm" @click="router.visit('/sales')">
                                    View All
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="sale in recentSales"
                                    :key="sale.id"
                                    class="flex items-center justify-between p-4 rounded-xl hover:bg-slate-50 transition-colors group"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 p-3 text-white font-bold group-hover:scale-110 transition-transform">
                                            {{ sale.items }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ sale.id }}</div>
                                            <div class="text-sm text-slate-600">{{ sale.customer }} • {{ sale.time }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-slate-900">${{ sale.amount.toFixed(2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Low Stock Alert -->
                <Card class="border-0 shadow-xl bg-gradient-to-r from-red-500 via-orange-500 to-yellow-500 text-white">
                    <CardHeader>
                        <CardTitle class="text-white flex items-center gap-2 text-xl">
                            <AlertTriangle class="h-6 w-6 animate-pulse" />
                            🚨 Low Stock Alert
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div
                                v-for="product in lowStockProducts"
                                :key="product.sku"
                                class="bg-white/20 backdrop-blur rounded-xl p-4 hover:bg-white/30 transition-colors"
                            >
                                <div class="font-bold text-lg">{{ product.name }}</div>
                                <div class="text-sm text-white/80 mt-1">{{ product.sku }}</div>
                                <div class="mt-3 flex items-center justify-between">
                                    <div>
                                        <div class="text-3xl font-bold">{{ product.current }}</div>
                                        <div class="text-xs text-white/70">Current</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-semibold">{{ product.min }}</div>
                                        <div class="text-xs text-white/70">Minimum</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Button
                            @click="router.visit('/products?low_stock=1')"
                            class="mt-6 bg-white text-red-600 hover:bg-red-50 w-full"
                        >
                            View All Low Stock Items
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>

