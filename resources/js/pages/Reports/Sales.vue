<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  TrendingUp,
  DollarSign,
  Calendar,
  Download,
  Filter,
  ArrowUpRight,
  ArrowDownRight
} from 'lucide-vue-next'

interface SalesData {
  date: string
  total_orders: number
  subtotal: number
  tax: number
  discount: number
  total: number
}

interface TopProduct {
  product_name: string
  total_quantity: number
  total_revenue: number
}

interface PaymentMethod {
  payment_method: string
  count: number
  total: number
}

const props = defineProps<{
  sales_data: SalesData[]
  top_products: TopProduct[]
  payment_methods: PaymentMethod[]
  filters: {
    start_date: string
    end_date: string
  }
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const totalRevenue = props.sales_data.reduce((sum, day) => sum + parseFloat(day.total.toString()), 0)
const totalOrders = props.sales_data.reduce((sum, day) => sum + day.total_orders, 0)
const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0

const applyFilters = () => {
  router.get('/reports/sales', {
    start_date: startDate.value,
    end_date: endDate.value,
  })
}

const getPaymentMethodColor = (method: string) => {
  const colors: Record<string, string> = {
    'CASH': 'bg-green-100 text-green-800',
    'CARD': 'bg-blue-100 text-blue-800',
    'MPESA': 'bg-purple-100 text-purple-800',
    'BANK_TRANSFER': 'bg-orange-100 text-orange-800',
  }
  return colors[method] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
  <Head title="Sales Reports" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-4xl font-bold text-slate-900 flex items-center gap-3">
              <div class="rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 p-3">
                <TrendingUp class="h-8 w-8 text-white" />
              </div>
              Sales Analytics
            </h1>
            <p class="mt-2 text-slate-600">Comprehensive sales insights and performance metrics</p>
          </div>
          <Button class="gap-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700">
            <Download class="h-4 w-4" />
            Export Report
          </Button>
        </div>

        <!-- Date Filters -->
        <Card class="border-0 shadow-xl bg-white/80 backdrop-blur">
          <CardContent class="pt-6">
            <div class="flex flex-wrap gap-4 items-end">
              <div class="flex-1 min-w-[200px]">
                <label class="text-sm font-medium text-slate-700 mb-2 block">Start Date</label>
                <Input v-model="startDate" type="date" class="border-slate-200" />
              </div>
              <div class="flex-1 min-w-[200px]">
                <label class="text-sm font-medium text-slate-700 mb-2 block">End Date</label>
                <Input v-model="endDate" type="date" class="border-slate-200" />
              </div>
              <Button @click="applyFilters" class="gap-2 bg-slate-900 hover:bg-slate-800">
                <Filter class="h-4 w-4" />
                Apply Filters
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Key Metrics -->
        <div class="grid gap-6 md:grid-cols-3">
          <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <CardHeader class="relative z-10">
              <CardTitle class="text-white/90 text-sm font-medium">Total Revenue</CardTitle>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">${{ totalRevenue.toFixed(2) }}</div>
              <div class="mt-2 flex items-center gap-2 text-blue-100">
                <ArrowUpRight class="h-4 w-4" />
                <span class="text-sm">+12.5% vs last period</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <CardHeader class="relative z-10">
              <CardTitle class="text-white/90 text-sm font-medium">Total Orders</CardTitle>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">{{ totalOrders }}</div>
              <div class="mt-2 flex items-center gap-2 text-purple-100">
                <ArrowUpRight class="h-4 w-4" />
                <span class="text-sm">+8.3% vs last period</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <CardHeader class="relative z-10">
              <CardTitle class="text-white/90 text-sm font-medium">Avg Order Value</CardTitle>
            </CardHeader>
            <CardContent class="relative z-10">
              <div class="text-4xl font-bold">${{ avgOrderValue.toFixed(2) }}</div>
              <div class="mt-2 flex items-center gap-2 text-emerald-100">
                <ArrowUpRight class="h-4 w-4" />
                <span class="text-sm">+4.2% vs last period</span>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Charts Row -->
        <div class="grid gap-6 lg:grid-cols-2">
          <!-- Daily Sales Chart -->
          <Card class="border-0 shadow-xl bg-white/80 backdrop-blur">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Calendar class="h-5 w-5 text-blue-600" />
                Daily Sales Trend
              </CardTitle>
              <CardDescription>Sales performance over time</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-for="day in sales_data.slice(0, 7)" :key="day.date" class="group">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ new Date(day.date).toLocaleDateString() }}</span>
                    <span class="text-sm font-bold text-slate-900">${{ parseFloat(day.total.toString()).toFixed(2) }}</span>
                  </div>
                  <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-gradient-to-r from-blue-500 to-purple-600 rounded-full transition-all group-hover:from-blue-600 group-hover:to-purple-700"
                      :style="{ width: `${(parseFloat(day.total.toString()) / totalRevenue) * 100}%` }"
                    ></div>
                  </div>
                  <div class="mt-1 text-xs text-slate-500">{{ day.total_orders }} orders</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Payment Methods -->
          <Card class="border-0 shadow-xl bg-white/80 backdrop-blur">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <DollarSign class="h-5 w-5 text-emerald-600" />
                Payment Methods
              </CardTitle>
              <CardDescription>Revenue breakdown by payment type</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div v-for="method in payment_methods" :key="method.payment_method">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <Badge :class="getPaymentMethodColor(method.payment_method)">
                        {{ method.payment_method }}
                      </Badge>
                      <span class="text-sm text-slate-600">{{ method.count }} transactions</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900">${{ parseFloat(method.total.toString()).toFixed(2) }}</span>
                  </div>
                  <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full"
                      :style="{ width: `${(parseFloat(method.total.toString()) / totalRevenue) * 100}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Top Products -->
        <Card class="border-0 shadow-xl bg-white/80 backdrop-blur">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <TrendingUp class="h-5 w-5 text-purple-600" />
              Top Selling Products
            </CardTitle>
            <CardDescription>Best performing items by revenue</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div
                v-for="(product, index) in top_products"
                :key="product.product_name"
                class="group flex items-center gap-4 p-4 rounded-xl hover:bg-slate-50 transition-colors"
              >
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-600 text-xl font-bold text-white">
                  {{ index + 1 }}
                </div>
                <div class="flex-1">
                  <div class="font-semibold text-slate-900">{{ product.product_name }}</div>
                  <div class="text-sm text-slate-600">{{ product.total_quantity }} units sold</div>
                </div>
                <div class="text-right">
                  <div class="text-2xl font-bold text-slate-900">${{ parseFloat(product.total_revenue.toString()).toFixed(2) }}</div>
                  <div class="text-sm text-slate-500">Revenue</div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.backdrop-blur {
  backdrop-filter: blur(10px);
}
</style>

