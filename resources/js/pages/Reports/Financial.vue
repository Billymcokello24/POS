<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import {
  DollarSign,
  TrendingUp,
  TrendingDown,
  PieChart,
  Download,
  Calendar,
  ArrowLeft,
  CreditCard,
  Receipt,
  Percent,
  ShoppingCart,
  Tag
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'

const page = usePage()
const currency = computed(() => {
  const curr = page.props.currency
  return typeof curr === 'function' ? curr() : curr || 'USD'
})

const formatCurrency = (amount: number | string): string => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  if (isNaN(num)) return '$0.00'
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'INR': '₹', 'KES': 'KSh', 'TZS': 'TSh', 'UGX': 'USh', 'ZAR': 'R', 'NGN': '₦',
  }

  const symbol = symbols[currencyCode] || currencyCode + ' '
  return `${symbol}${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatPercent = (value: number): string => {
  return `${value >= 0 ? '+' : ''}${value.toFixed(2)}%`
}

const props = defineProps<{
  summary: {
    total_revenue: number
    total_cost: number
    gross_profit: number
    profit_margin: number
    total_tax: number
    total_discounts: number
    total_orders: number
    average_order_value: number
  }
  revenue_by_day: Array<{
    date: string
    revenue: number
    cost: number
    profit: number
    tax: number
    discount: number
    orders: number
  }>
  payment_breakdown: Array<{
    payment_method: string
    count: number
    total: number
  }>
  growth: {
    current_month: number
    previous_month: number
    growth_percentage: number
  }
  filters: {
    start_date: string
    end_date: string
  }
}>()

// Calculate max revenue for chart scaling
const maxRevenue = computed(() => {
  return Math.max(...props.revenue_by_day.map(d => d.revenue))
})
</script>

<template>
  <Head title="Financial Overview" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-8">
        <!-- Header - Mobile Optimized -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
          <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
            <Button
              variant="outline"
              size="icon"
              @click="$inertia.visit('/reports')"
              class="rounded-full h-9 w-9 sm:h-10 sm:w-10 flex-shrink-0"
            >
              <ArrowLeft class="h-4 w-4" />
            </Button>
            <div class="min-w-0 flex-1">
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent truncate">
                Financial Overview
              </h1>
              <p class="text-slate-600 mt-1 text-xs sm:text-sm truncate">Complete financial insights</p>
            </div>
          </div>
          <div class="flex gap-2 flex-wrap">
            <Button variant="outline" class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
              <Calendar class="h-3 w-3 sm:h-4 sm:w-4" />
              <span class="hidden md:inline">{{ new Date(filters.start_date).toLocaleDateString() }} - {{ new Date(filters.end_date).toLocaleDateString() }}</span>
              <span class="md:hidden">Date Range</span>
            </Button>
            <Button variant="outline" class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm">
              <Download class="h-3 w-3 sm:h-4 sm:w-4" />
              <span class="hidden xs:inline">Export</span>
            </Button>
          </div>
        </div>

        <!-- Key Metrics - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-6 grid-cols-2 lg:grid-cols-4">
          <!-- Total Revenue -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-32 sm:h-32 bg-white/10 rounded-full -mr-8 sm:-mr-16 -mt-8 sm:-mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10 p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-xs sm:text-sm font-medium truncate">Total Revenue</CardTitle>
                <DollarSign class="h-4 w-4 sm:h-6 sm:w-6 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(summary.total_revenue) }}</div>
              <p class="text-blue-100 text-xs sm:text-sm mt-1 sm:mt-2 truncate">{{ summary.total_orders }} orders</p>
            </CardContent>
          </Card>

          <!-- Gross Profit -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-32 sm:h-32 bg-white/10 rounded-full -mr-8 sm:-mr-16 -mt-8 sm:-mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10 p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-xs sm:text-sm font-medium truncate">Gross Profit</CardTitle>
                <TrendingUp class="h-4 w-4 sm:h-6 sm:w-6 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(summary.gross_profit) }}</div>
              <p class="text-emerald-100 text-xs sm:text-sm mt-1 sm:mt-2 truncate">{{ summary.profit_margin.toFixed(2) }}% margin</p>
            </CardContent>
          </Card>

          <!-- Total Cost -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-orange-500 to-red-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-32 sm:h-32 bg-white/10 rounded-full -mr-8 sm:-mr-16 -mt-8 sm:-mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10 p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-xs sm:text-sm font-medium truncate">Total Cost</CardTitle>
                <Receipt class="h-4 w-4 sm:h-6 sm:w-6 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(summary.total_cost) }}</div>
              <p class="text-orange-100 text-xs sm:text-sm mt-1 sm:mt-2 truncate">COGS</p>
            </CardContent>
          </Card>

          <!-- Average Order Value -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-pink-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-32 sm:h-32 bg-white/10 rounded-full -mr-8 sm:-mr-16 -mt-8 sm:-mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10 p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-xs sm:text-sm font-medium truncate">Avg Order</CardTitle>
                <ShoppingCart class="h-4 w-4 sm:h-6 sm:w-6 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(summary.average_order_value) }}</div>
              <p class="text-purple-100 text-xs sm:text-sm mt-1 sm:mt-2 truncate">Per transaction</p>
            </CardContent>
          </Card>
        </div>

        <!-- Secondary Metrics - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-6 grid-cols-1 sm:grid-cols-3">
          <Card class="shadow-lg">
            <CardHeader class="p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-base sm:text-lg truncate">Tax Collected</CardTitle>
                <Percent class="h-4 w-4 sm:h-5 sm:w-5 text-slate-600 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl font-bold text-slate-900 truncate">{{ formatCurrency(summary.total_tax) }}</div>
              <p class="text-xs sm:text-sm text-slate-600 mt-1 truncate">Total tax amount</p>
            </CardContent>
          </Card>

          <Card class="shadow-lg">
            <CardHeader class="p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-base sm:text-lg truncate">Discounts</CardTitle>
                <Tag class="h-4 w-4 sm:h-5 sm:w-5 text-slate-600 flex-shrink-0" />
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl font-bold text-slate-900 truncate">{{ formatCurrency(summary.total_discounts) }}</div>
              <p class="text-xs sm:text-sm text-slate-600 mt-1 truncate">Total discounts</p>
            </CardContent>
          </Card>

          <Card class="shadow-lg">
            <CardHeader class="p-3 sm:p-6">
              <div class="flex items-center justify-between">
                <CardTitle class="text-base sm:text-lg truncate">Growth</CardTitle>
                <component :is="growth.growth_percentage >= 0 ? TrendingUp : TrendingDown" class="h-4 w-4 sm:h-5 sm:w-5 flex-shrink-0" :class="growth.growth_percentage >= 0 ? 'text-green-600' : 'text-red-600'" />
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl font-bold truncate" :class="growth.growth_percentage >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatPercent(growth.growth_percentage) }}
              </div>
              <p class="text-xs sm:text-sm text-slate-600 mt-1 truncate">vs previous month</p>
            </CardContent>
          </Card>
        </div>

        <!-- Revenue & Profit Chart - Mobile Optimized -->
        <Card class="shadow-xl">
          <CardHeader class="p-3 sm:p-6">
            <CardTitle class="text-base sm:text-xl lg:text-2xl flex items-center gap-2">
              <TrendingUp class="h-5 w-5 sm:h-6 sm:w-6 text-purple-600 flex-shrink-0" />
              <span class="truncate">Revenue & Profit Trend</span>
            </CardTitle>
            <CardDescription class="text-xs sm:text-sm">Daily breakdown</CardDescription>
          </CardHeader>
          <CardContent class="p-3 sm:p-6 pt-0">
            <div class="space-y-3 sm:space-y-4">
              <div v-for="day in revenue_by_day" :key="day.date" class="space-y-1 sm:space-y-2">
                <div class="flex items-center justify-between text-xs sm:text-sm gap-2">
                  <span class="font-medium text-slate-700 flex-shrink-0">{{ new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                  <div class="flex flex-col xs:flex-row xs:gap-3 sm:gap-6 text-right xs:text-left min-w-0">
                    <span class="text-blue-600 truncate text-[10px] xs:text-xs sm:text-sm">Rev: {{ formatCurrency(day.revenue) }}</span>
                    <span class="text-green-600 truncate text-[10px] xs:text-xs sm:text-sm hidden xs:inline">Profit: {{ formatCurrency(day.profit) }}</span>
                    <span class="text-slate-500 text-[10px] xs:text-xs sm:text-sm hidden sm:inline">{{ day.orders }} orders</span>
                  </div>
                </div>
                <div class="flex gap-1 h-2">
                  <!-- Cost -->
                  <div
                    class="bg-red-400 rounded-l transition-all hover:bg-red-500"
                    :style="{ width: `${(day.cost / maxRevenue) * 100}%` }"
                    :title="`Cost: ${formatCurrency(day.cost)}`"
                  ></div>
                  <!-- Profit -->
                  <div
                    class="bg-green-500 rounded-r transition-all hover:bg-green-600"
                    :style="{ width: `${(day.profit / maxRevenue) * 100}%` }"
                    :title="`Profit: ${formatCurrency(day.profit)}`"
                  ></div>
                </div>
              </div>

              <div v-if="revenue_by_day.length === 0" class="text-center py-8 sm:py-12 text-slate-500 text-sm">
                No data available for the selected period
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Payment Methods Breakdown - Mobile Optimized -->
        <Card class="shadow-xl">
          <CardHeader class="p-3 sm:p-6">
            <CardTitle class="text-base sm:text-xl lg:text-2xl flex items-center gap-2">
              <CreditCard class="h-5 w-5 sm:h-6 sm:w-6 text-purple-600 flex-shrink-0" />
              <span class="truncate">Payment Methods</span>
            </CardTitle>
            <CardDescription class="text-xs sm:text-sm">Revenue by payment method</CardDescription>
          </CardHeader>
          <CardContent class="p-3 sm:p-6 pt-0">
            <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="method in payment_breakdown"
                :key="method.payment_method"
                class="p-4 rounded-xl border-2 border-slate-200 hover:border-purple-400 hover:shadow-md transition-all"
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-semibold text-slate-900 capitalize">{{ method.payment_method }}</span>
                  <span class="text-sm text-slate-600">{{ method.count }} txns</span>
                </div>
                <div class="text-2xl font-bold text-purple-600">{{ formatCurrency(method.total) }}</div>
                <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all"
                    :style="{ width: `${(method.total / summary.total_revenue) * 100}%` }"
                  ></div>
                </div>
                <div class="text-sm text-slate-600 mt-1">
                  {{ ((method.total / summary.total_revenue) * 100).toFixed(1) }}% of total revenue
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Profit Margin Analysis -->
        <Card class="shadow-xl bg-gradient-to-br from-purple-50 to-pink-50">
          <CardHeader>
            <CardTitle class="text-2xl flex items-center gap-2">
              <PieChart class="h-6 w-6 text-purple-600" />
              Profit Margin Analysis
            </CardTitle>
            <CardDescription>Understanding your profitability</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Visual breakdown -->
              <div class="space-y-4">
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-slate-700">Revenue</span>
                    <span class="text-sm font-bold text-blue-600">{{ formatCurrency(summary.total_revenue) }}</span>
                  </div>
                  <div class="h-3 bg-blue-500 rounded"></div>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-slate-700">Cost of Goods</span>
                    <span class="text-sm font-bold text-red-600">{{ formatCurrency(summary.total_cost) }}</span>
                  </div>
                  <div class="h-3 bg-red-400 rounded" :style="{ width: `${(summary.total_cost / summary.total_revenue) * 100}%` }"></div>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-slate-700">Gross Profit</span>
                    <span class="text-sm font-bold text-green-600">{{ formatCurrency(summary.gross_profit) }}</span>
                  </div>
                  <div class="h-3 bg-green-500 rounded" :style="{ width: `${summary.profit_margin}%` }"></div>
                </div>
              </div>

              <!-- Metrics -->
              <div class="bg-white rounded-xl p-6 space-y-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-purple-600 mb-2">{{ summary.profit_margin.toFixed(1) }}%</div>
                  <p class="text-slate-600">Profit Margin</p>
                </div>
                <div class="pt-4 border-t space-y-2">
                  <div class="flex justify-between">
                    <span class="text-sm text-slate-600">Cost Ratio:</span>
                    <span class="text-sm font-semibold">{{ ((summary.total_cost / summary.total_revenue) * 100).toFixed(1) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-sm text-slate-600">Profit per Order:</span>
                    <span class="text-sm font-semibold">{{ formatCurrency(summary.gross_profit / summary.total_orders) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

