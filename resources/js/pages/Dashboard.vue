<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    ShoppingCart,
    Package,
    DollarSign,
    AlertTriangle,
    BarChart3,
    TrendingUp,
    TrendingDown,
    ArrowUpRight,
    Clock,
    Users,
    Activity,
    ShoppingBag,
    Zap
} from 'lucide-vue-next'

// Get currency from page props
const page = usePage()
const currency = computed(() => {
  const curr = page.props.currency
  return typeof curr === 'function' ? curr() : curr || 'KES'
})

// Currency formatting function
const formatCurrency = (amount: number | string): string => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'JPY': '¥',
    'CNY': '¥',
    'INR': '₹',
    'KES': 'KSh',
    'TZS': 'TSh',
    'UGX': 'USh',
    'ZAR': 'R',
    'NGN': '₦',
  }

  const symbol = symbols[currencyCode] || currencyCode + ' '
  return `${symbol}${num.toFixed(2)}`
}

// Accept props from controller
const props = defineProps<{
    stats: {
        todaySales: number
        totalProducts: number
        lowStockItems: number
        todayOrders: number
        yesterdaySales: number
        monthlyGrowth: number
    }
    recentSales: Array<{
        id: string
        items: number
        time: string
        amount: number
        customer: string
    }>
    lowStockProducts: Array<{
        name: string
        sku: string
        current: number
        min: number
    }>
    systemNotifications: Array<{
        id: number
        title: string
        message: string
        type: 'info' | 'warning' | 'danger'
        created_at: string
    }>
}>()

// Calculate percentage change
const salesChangePercent = props.stats.yesterdaySales > 0
    ? ((props.stats.todaySales - props.stats.yesterdaySales) / props.stats.yesterdaySales * 100).toFixed(1)
    : '0.0'

const salesTrend = props.stats.todaySales >= props.stats.yesterdaySales ? 'up' : 'down'

// Get current time greeting
const getGreeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good Morning'
  if (hour < 18) return 'Good Afternoon'
  return 'Good Evening'
}

// Feature access helper
const hasFeature = (feature: string) => {
  const enabledFeatures = (page.props.auth as any).features || [];
  return enabledFeatures.includes(feature);
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
            <div class="mx-auto w-[90%] px-6 py-8 space-y-8">
                <!-- System Notifications -->
                <div v-if="props.systemNotifications && props.systemNotifications.length > 0" class="space-y-4">
                    <div v-for="note in props.systemNotifications" :key="note.id" 
                        :class="{
                            'bg-blue-600': note.type === 'info',
                            'bg-amber-500': note.type === 'warning',
                            'bg-red-600': note.type === 'danger'
                        }"
                        class="p-4 rounded-lg shadow-lg text-white flex items-start gap-4"
                    >
                        <Zap class="shrink-0 h-6 w-6 mt-0.5" />
                        <div>
                            <h3 class="font-bold text-lg">{{ note.title }}</h3>
                            <p class="text-white/90">{{ note.message }}</p>
                            <p class="text-xs text-white/70 mt-2">{{ new Date(note.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                </div>
                <!-- Welcome Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">
                            {{ getGreeting() }}, {{ page.props.auth.user.name }}! 👋
                        </h1>
                        <p class="text-lg text-gray-600">
                            {{ (page.props.auth as any).role_level >= 75 ? "Here's your business diagnostic at a glance" : "Welcome to your operations terminal" }}
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <Button
                            v-if="(page.props.auth as any).permissions?.includes('create_sales') && hasFeature('pos')"
                            @click="router.visit('/sales/create')"
                            class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-lg hover:shadow-xl transition-all"
                            size="lg"
                        >
                            <ShoppingCart class="mr-2 h-5 w-5" />
                            New Sale
                        </Button>
                        <Button
                            v-if="(page.props.auth as any).role_level >= 75 && hasFeature('reports')"
                            @click="router.visit('/reports')"
                            variant="outline"
                            size="lg"
                            class="border-2"
                        >
                            <BarChart3 class="mr-2 h-5 w-5" />
                            Reports
                        </Button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div v-if="(page.props.auth as any).role_level >= 75" class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Today's Sales -->
                    <Card class="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-gray-600">Today's Revenue</CardTitle>
                                <div class="rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 p-3 group-hover:scale-110 transition-transform">
                                    <DollarSign class="h-5 w-5 text-white" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-gray-900 mb-2">
                                {{ formatCurrency(props.stats.todaySales) }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="salesTrend === 'up' ? 'default' : 'destructive'"
                                    class="flex items-center gap-1"
                                >
                                    <TrendingUp v-if="salesTrend === 'up'" class="h-3 w-3" />
                                    <TrendingDown v-else class="h-3 w-3" />
                                    {{ salesChangePercent }}%
                                </Badge>
                                <span class="text-sm text-gray-500">vs yesterday</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Orders Today -->
                    <Card class="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-gray-600">Orders Today</CardTitle>
                                <div class="rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 p-3 group-hover:scale-110 transition-transform">
                                    <ShoppingBag class="h-5 w-5 text-white" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-gray-900 mb-2">
                                {{ props.stats.todayOrders }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="props.stats.monthlyGrowth >= 0 ? 'default' : 'destructive'"
                                    class="flex items-center gap-1"
                                >
                                    <TrendingUp v-if="props.stats.monthlyGrowth >= 0" class="h-3 w-3" />
                                    <TrendingDown v-else class="h-3 w-3" />
                                    {{ Math.abs(props.stats.monthlyGrowth).toFixed(1) }}%
                                </Badge>
                                <span class="text-sm text-gray-500">monthly growth</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Total Products -->
                    <Card class="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-gray-600">Total Products</CardTitle>
                                <div class="rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 p-3 group-hover:scale-110 transition-transform">
                                    <Package class="h-5 w-5 text-white" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-gray-900 mb-2">
                                {{ props.stats.totalProducts }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary" class="flex items-center gap-1">
                                    <Activity class="h-3 w-3" />
                                    Active
                                </Badge>
                                <span class="text-sm text-gray-500">in inventory</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Low Stock Alert -->
                    <Card class="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-gray-600">Low Stock Items</CardTitle>
                                <div class="rounded-xl bg-gradient-to-br from-orange-500 to-red-600 p-3 group-hover:scale-110 transition-transform">
                                    <AlertTriangle class="h-5 w-5 text-white" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-red-600 mb-2">
                                {{ props.stats.lowStockItems }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="destructive" class="flex items-center gap-1">
                                    <Zap class="h-3 w-3" />
                                    Action Required
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main Content Grid -->
                <div class="grid gap-6" :class="(page.props.auth as any).role_level >= 50 ? 'lg:grid-cols-3' : 'grid-cols-1'">
                    <!-- Recent Transactions -->
                    <Card v-if="(page.props.auth as any).role_level >= 50" class="border-0 shadow-lg bg-white lg:col-span-2">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-xl font-bold flex items-center gap-2">
                                        <Clock class="h-5 w-5 text-blue-600" />
                                        Recent Transactions
                                    </CardTitle>
                                    <CardDescription>Latest sales activity</CardDescription>
                                </div>
                                <Button
                                    variant="ghost"
                                    @click="router.visit('/sales')"
                                    class="text-blue-600 hover:text-blue-700"
                                >
                                    View All
                                    <ArrowUpRight class="ml-1 h-4 w-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="props.recentSales.length === 0" class="py-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <ShoppingCart class="h-8 w-8 text-gray-400" />
                                </div>
                                <p class="text-gray-600 font-medium mb-1">No sales yet today</p>
                                <p class="text-sm text-gray-500">Start selling to see transactions here</p>
                                <Button
                                    @click="router.visit('/sales/create')"
                                    class="mt-4 bg-gradient-to-r from-blue-600 to-indigo-600"
                                >
                                    <ShoppingCart class="mr-2 h-4 w-4" />
                                    Create First Sale
                                </Button>
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="sale in props.recentSales"
                                    :key="sale.id"
                                    class="flex items-center justify-between p-4 rounded-xl border-2 border-gray-100 hover:border-blue-200 hover:bg-blue-50/50 transition-all cursor-pointer group"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 p-3 text-white group-hover:scale-110 transition-transform">
                                            <span class="text-lg font-bold">{{ sale.items }}</span>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900">{{ sale.id }}</div>
                                            <div class="text-sm text-gray-600 flex items-center gap-2">
                                                <Users class="h-3 w-3" />
                                                {{ sale.customer }}
                                                <span class="text-gray-400">•</span>
                                                {{ sale.time }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-gray-900">
                                            {{ formatCurrency(sale.amount) }}
                                        </div>
                                        <Badge variant="secondary" class="mt-1">
                                            Completed
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card class="border-0 shadow-lg bg-white">
                        <CardHeader>
                            <CardTitle class="text-xl font-bold flex items-center gap-2">
                                <Zap class="h-5 w-5 text-purple-600" />
                                Quick Actions
                            </CardTitle>
                            <CardDescription>Common tasks</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button
                                v-if="hasFeature('pos')"
                                @click="router.visit('/sales/create')"
                                class="w-full justify-start gap-3 h-auto py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md hover:shadow-lg transition-all"
                            >
                                <ShoppingCart class="h-5 w-5" />
                                <div class="text-left flex-1">
                                    <div class="font-semibold">New Sale</div>
                                    <div class="text-xs opacity-90">Open POS terminal</div>
                                </div>
                                <ArrowUpRight class="h-4 w-4" />
                            </Button>

                            <Button
                                v-if="hasFeature('products')"
                                @click="router.visit('/products')"
                                variant="outline"
                                class="w-full justify-start gap-3 h-auto py-4 border-2 hover:bg-gray-50 hover:border-gray-300 transition-all"
                            >
                                <Package class="h-5 w-5 text-purple-600" />
                                <div class="text-left flex-1">
                                    <div class="font-semibold text-gray-900">Manage Products</div>
                                    <div class="text-xs text-gray-600">View inventory</div>
                                </div>
                                <ArrowUpRight class="h-4 w-4" />
                            </Button>

                             <Button
                                v-if="(page.props.auth as any).permissions?.includes('view_reports') && hasFeature('reports')"
                                @click="router.visit('/reports')"
                                variant="outline"
                                class="w-full justify-start gap-3 h-auto py-4 border-2 hover:bg-gray-50 hover:border-gray-300 transition-all"
                            >
                                <BarChart3 class="h-5 w-5 text-blue-600" />
                                <div class="text-left flex-1">
                                    <div class="font-semibold text-gray-900">View Reports</div>
                                    <div class="text-xs text-gray-600">Analytics & insights</div>
                                </div>
                                <ArrowUpRight class="h-4 w-4" />
                            </Button>

                            <Button
                                v-if="(page.props.auth as any).permissions?.includes('view_inventory') && hasFeature('inventory')"
                                @click="router.visit('/inventory')"
                                variant="outline"
                                class="w-full justify-start gap-3 h-auto py-4 border-2 hover:bg-gray-50 hover:border-gray-300 transition-all"
                            >
                                <Activity class="h-5 w-5 text-green-600" />
                                <div class="text-left flex-1">
                                    <div class="font-semibold text-gray-900">Inventory</div>
                                    <div class="text-xs text-gray-600">Stock management</div>
                                </div>
                                <ArrowUpRight class="h-4 w-4" />
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <!-- Low Stock Alert -->
                <Card
                    v-if="props.lowStockProducts.length > 0"
                    class="border-0 shadow-lg overflow-hidden bg-gradient-to-r from-orange-500 via-red-500 to-pink-500"
                >
                    <CardHeader>
                        <CardTitle class="text-white flex items-center gap-3 text-2xl">
                            <AlertTriangle class="h-7 w-7 animate-pulse" />
                            Low Stock Alert
                        </CardTitle>
                        <CardDescription class="text-white/90 text-base">
                            These items need restocking soon
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div
                                v-for="product in props.lowStockProducts"
                                :key="product.sku"
                                class="bg-white/20 backdrop-blur-sm rounded-xl p-5 border-2 border-white/30 hover:bg-white/30 transition-all"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <div class="font-bold text-lg text-white">{{ product.name }}</div>
                                        <div class="text-sm text-white/80 mt-1">SKU: {{ product.sku }}</div>
                                    </div>
                                    <Badge variant="destructive" class="bg-white text-red-600">
                                        ⚠️ Low
                                    </Badge>
                                </div>
                                <div class="flex items-center justify-between mt-4 pt-4 border-t border-white/30">
                                    <div>
                                        <div class="text-3xl font-bold text-white">{{ product.current }}</div>
                                        <div class="text-xs text-white/70">In Stock</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-semibold text-white/90">{{ product.min }}</div>
                                        <div class="text-xs text-white/70">Min Required</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Button
                            @click="router.visit('/products?low_stock=1')"
                            class="mt-6 w-full bg-white text-red-600 hover:bg-gray-100 font-semibold text-lg py-6"
                            size="lg"
                        >
                            View All Low Stock Items
                            <ArrowUpRight class="ml-2 h-5 w-5" />
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

