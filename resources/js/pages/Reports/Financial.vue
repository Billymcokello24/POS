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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50 p-6">
      <div class="mx-auto w-[90%] space-y-8">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <Button
              variant="outline"
              size="icon"
              @click="$inertia.visit('/reports')"
              class="rounded-full"
            >
              <ArrowLeft class="h-4 w-4" />
            </Button>
            <div>
              <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                Financial Overview
              </h1>
              <p class="text-slate-600 mt-1">Complete financial insights and profit analysis</p>
            </div>
          </div>
          <div class="flex gap-2">
            <Button variant="outline" class="gap-2">
              <Calendar class="h-4 w-4" />
              {{ new Date(filters.start_date).toLocaleDateString() }} - {{ new Date(filters.end_date).toLocaleDateString() }}
            </Button>
            <Button variant="outline" class="gap-2">
              <Download class="h-4 w-4" />
              Export
            </Button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <!-- Total Revenue -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-sm font-medium">Total Revenue</CardTitle>
                <DollarSign class="h-6 w-6" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-3xl font-bold">{{ formatCurrency(summary.total_revenue) }}</div>
              <p class="text-blue-100 text-sm mt-2">{{ summary.total_orders }} orders</p>
            </CardContent>
          </Card>

          <!-- Gross Profit -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-sm font-medium">Gross Profit</CardTitle>
                <TrendingUp class="h-6 w-6" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-3xl font-bold">{{ formatCurrency(summary.gross_profit) }}</div>
              <p class="text-emerald-100 text-sm mt-2">{{ summary.profit_margin.toFixed(2) }}% margin</p>
            </CardContent>
          </Card>

          <!-- Total Cost -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-orange-500 to-red-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-sm font-medium">Total Cost (COGS)</CardTitle>
                <Receipt class="h-6 w-6" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-3xl font-bold">{{ formatCurrency(summary.total_cost) }}</div>
              <p class="text-orange-100 text-sm mt-2">Cost of goods sold</p>
            </CardContent>
          </Card>

          <!-- Average Order Value -->
          <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-pink-600 text-white overflow-hidden relative group hover:scale-105 transition-transform">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform"></div>
            <CardHeader class="relative z-10">
              <div class="flex items-center justify-between">
                <CardTitle class="text-white text-sm font-medium">Avg Order Value</CardTitle>
                <ShoppingCart class="h-6 w-6" />
              </div>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-3xl font-bold">{{ formatCurrency(summary.average_order_value) }}</div>
              <p class="text-purple-100 text-sm mt-2">Per transaction</p>
            </CardContent>
          </Card>
        </div>

        <!-- Secondary Metrics -->
        <div class="grid gap-6 md:grid-cols-3">
          <Card class="shadow-lg">
            <CardHeader>
              <div class="flex items-center justify-between">
                <CardTitle class="text-lg">Tax Collected</CardTitle>
                <Percent class="h-5 w-5 text-slate-600" />
              </div>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-slate-900">{{ formatCurrency(summary.total_tax) }}</div>
              <p class="text-sm text-slate-600 mt-1">Total tax amount</p>
            </CardContent>
          </Card>

          <Card class="shadow-lg">
            <CardHeader>
              <div class="flex items-center justify-between">
                <CardTitle class="text-lg">Discounts Given</CardTitle>
                <Tag class="h-5 w-5 text-slate-600" />
              </div>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-slate-900">{{ formatCurrency(summary.total_discounts) }}</div>
              <p class="text-sm text-slate-600 mt-1">Total discounts</p>
            </CardContent>
          </Card>

          <Card class="shadow-lg">
            <CardHeader>
              <div class="flex items-center justify-between">
                <CardTitle class="text-lg">Monthly Growth</CardTitle>
                <component :is="growth.growth_percentage >= 0 ? TrendingUp : TrendingDown" class="h-5 w-5" :class="growth.growth_percentage >= 0 ? 'text-green-600' : 'text-red-600'" />
              </div>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold" :class="growth.growth_percentage >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatPercent(growth.growth_percentage) }}
              </div>
              <p class="text-sm text-slate-600 mt-1">vs previous month</p>
            </CardContent>
          </Card>
        </div>

        <!-- Revenue & Profit Chart -->
        <Card class="shadow-xl">
          <CardHeader>
            <CardTitle class="text-2xl flex items-center gap-2">
              <TrendingUp class="h-6 w-6 text-purple-600" />
              Revenue & Profit Trend
            </CardTitle>
            <CardDescription>Daily breakdown of revenue, costs, and profit</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div v-for="day in revenue_by_day" :key="day.date" class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="font-medium text-slate-700">{{ new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                  <div class="flex gap-6">
                    <span class="text-blue-600">Rev: {{ formatCurrency(day.revenue) }}</span>
                    <span class="text-green-600">Profit: {{ formatCurrency(day.profit) }}</span>
                    <span class="text-slate-500">{{ day.orders }} orders</span>
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

              <div v-if="revenue_by_day.length === 0" class="text-center py-12 text-slate-500">
                No data available for the selected period
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Payment Methods Breakdown -->
        <Card class="shadow-xl">
          <CardHeader>
            <CardTitle class="text-2xl flex items-center gap-2">
              <CreditCard class="h-6 w-6 text-purple-600" />
              Payment Methods
            </CardTitle>
            <CardDescription>Revenue breakdown by payment method</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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

