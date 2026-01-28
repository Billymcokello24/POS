<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import {
  BarChart3,
  TrendingUp,
  Package,
  DollarSign,
  Download,
  ShoppingCart,
  AlertTriangle,
  Zap,
  FileText,
  PieChart
} from 'lucide-vue-next'
import { computed } from 'vue'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'


// Get currency from page props
const page = usePage()
const currency = computed(() => {
  const curr = page.props.currency
  return typeof curr === 'function' ? curr() : curr || 'USD'
})

// Currency formatting function
const formatCurrency = (amount: number | string): string => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  if (isNaN(num)) return '$0.00'
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'INR': '₹', 'KES': 'KSh', 'TZS': 'TSh', 'UGX': 'USh', 'ZAR': 'R', 'NGN': '₦',
  }

  const symbol = symbols[currencyCode] || currencyCode + ' '
  return `${symbol}${num.toFixed(2)}`
}

const props = defineProps<{
  stats: {
    today_sales: number
    today_orders: number
    total_products: number
    low_stock_items: number
  }
}>()
</script>

<template>
  <Head title="Reports" />

  <AppLayout title="Reports">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto w-[90%] space-y-8">
        <!-- Header with Gradient -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-10 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-48 -mt-48"></div>
          <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/5 rounded-full -ml-32 -mb-32"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
              <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                <BarChart3 class="h-8 w-8" />
              </div>
              <div>
                <h1 class="text-4xl font-bold">Reports & Analytics</h1>
                <p class="text-purple-100 text-lg mt-1">Powerful insights at your fingertips</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Real-time Stats -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <Card class="border-0 shadow-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white overflow-hidden group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-12 -mt-12 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center gap-3">
                <DollarSign class="h-8 w-8" />
                <CardTitle class="text-white text-lg">Today's Revenue</CardTitle>
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">{{ formatCurrency(props.stats.today_sales) }}</div>
              <p class="text-emerald-100 text-sm mt-2">💰 Total sales today</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white overflow-hidden group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-12 -mt-12 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center gap-3">
                <ShoppingCart class="h-8 w-8" />
                <CardTitle class="text-white text-lg">Transactions</CardTitle>
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">{{ props.stats.today_orders }}</div>
              <p class="text-blue-100 text-sm mt-2">🛒 Orders completed</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-pink-600 text-white overflow-hidden group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-12 -mt-12 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center gap-3">
                <Package class="h-8 w-8" />
                <CardTitle class="text-white text-lg">Products</CardTitle>
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">{{ props.stats.total_products }}</div>
              <p class="text-purple-100 text-sm mt-2">📦 In stock</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-red-500 to-orange-600 text-white overflow-hidden group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-12 -mt-12 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center gap-3">
                <AlertTriangle class="h-8 w-8 animate-pulse" />
                <CardTitle class="text-white text-lg">Alerts</CardTitle>
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">{{ props.stats.low_stock_items }}</div>
              <p class="text-red-100 text-sm mt-2">⚠️ Low stock items</p>
            </CardContent>
          </Card>
        </div>

        <!-- Report Modules -->
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
          <!-- Sales Reports -->
          <Card class="border-0 shadow-2xl bg-white hover:shadow-3xl transition-all group overflow-hidden relative">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-purple-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between mb-4">
                <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                  <TrendingUp class="h-10 w-10 text-white" />
                </div>
                <Button variant="ghost" size="sm" class="gap-2">
                  <Download class="h-4 w-4" />
                </Button>
              </div>
              <CardTitle class="text-2xl">Sales Analytics</CardTitle>
              <CardDescription class="text-base">
                Deep insights into your revenue streams and performance
              </CardDescription>
            </CardHeader>
            <CardContent class="relative z-10 space-y-3">
              <div class="flex items-center gap-3 text-sm">
                <Zap class="h-4 w-4 text-blue-600" />
                <span>Real-time sales tracking</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <PieChart class="h-4 w-4 text-purple-600" />
                <span>Product performance metrics</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <FileText class="h-4 w-4 text-pink-600" />
                <span>Payment methods breakdown</span>
              </div>
              <Button
                class="w-full mt-6 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 gap-2"
                @click="$inertia.visit('/reports/sales')"
              >
                <BarChart3 class="h-4 w-4" />
                View Sales Reports
              </Button>
            </CardContent>
          </Card>

          <!-- Inventory Reports -->
          <Card class="border-0 shadow-2xl bg-white hover:shadow-3xl transition-all group overflow-hidden relative">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-teal-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between mb-4">
                <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                  <Package class="h-10 w-10 text-white" />
                </div>
                <Button variant="ghost" size="sm" class="gap-2">
                  <Download class="h-4 w-4" />
                </Button>
              </div>
              <CardTitle class="text-2xl">Inventory Intelligence</CardTitle>
              <CardDescription class="text-base">
                Monitor stock levels and optimize your inventory
              </CardDescription>
            </CardHeader>
            <CardContent class="relative z-10 space-y-3">
              <div class="flex items-center gap-3 text-sm">
                <Zap class="h-4 w-4 text-emerald-600" />
                <span>Live stock monitoring</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <AlertTriangle class="h-4 w-4 text-orange-600" />
                <span>Smart reorder alerts</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <TrendingUp class="h-4 w-4 text-teal-600" />
                <span>Movement analytics</span>
              </div>
              <Button
                class="w-full mt-6 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 gap-2"
                @click="$inertia.visit('/reports/inventory')"
              >
                <Package class="h-4 w-4" />
                View Inventory Reports
              </Button>
            </CardContent>
          </Card>

          <!-- Financial Reports -->
          <Card class="border-0 shadow-2xl bg-white hover:shadow-3xl transition-all group overflow-hidden relative">
            <div class="absolute inset-0 bg-gradient-to-br from-purple-50 to-pink-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between mb-4">
                <div class="rounded-2xl bg-gradient-to-br from-purple-500 to-pink-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                  <DollarSign class="h-10 w-10 text-white" />
                </div>
                <Button variant="ghost" size="sm" class="gap-2">
                  <Download class="h-4 w-4" />
                </Button>
              </div>
              <CardTitle class="text-2xl">Financial Overview</CardTitle>
              <CardDescription class="text-base">
                Track profitability and financial health
              </CardDescription>
            </CardHeader>
            <CardContent class="relative z-10 space-y-3">
              <div class="flex items-center gap-3 text-sm">
                <DollarSign class="h-4 w-4 text-purple-600" />
                <span>Revenue analytics</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <TrendingUp class="h-4 w-4 text-pink-600" />
                <span>Profit margin tracking</span>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <FileText class="h-4 w-4 text-indigo-600" />
                <span>Tax summaries</span>
              </div>
              <Button
                class="w-full mt-6 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 gap-2"
                disabled
              >
                <BarChart3 class="h-4 w-4" />
                Coming Soon
              </Button>
            </CardContent>
          </Card>
        </div>

        <!-- Export Center -->
        <Card class="border-0 shadow-2xl bg-gradient-to-r from-slate-800 to-slate-900 text-white">
          <CardHeader>
            <CardTitle class="text-white text-2xl flex items-center gap-3">
              <Download class="h-6 w-6" />
              Export Center
            </CardTitle>
            <CardDescription class="text-slate-300 text-base">
              Download comprehensive reports in your preferred format
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-4 md:grid-cols-3">
              <Button variant="outline" class="border-white/20 hover:bg-white/10 text-white h-16 text-base gap-3">
                <FileText class="h-5 w-5" />
                Export to PDF
              </Button>
              <Button variant="outline" class="border-white/20 hover:bg-white/10 text-white h-16 text-base gap-3">
                <FileText class="h-5 w-5" />
                Export to Excel
              </Button>
              <Button variant="outline" class="border-white/20 hover:bg-white/10 text-white h-16 text-base gap-3">
                <FileText class="h-5 w-5" />
                Export to CSV
              </Button>
            </div>
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
