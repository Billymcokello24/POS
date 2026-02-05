<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import {
  TrendingUp,
  TrendingDown,
  DollarSign,
  Calendar,
  Download,
  Filter,
  ArrowUpRight,
  ArrowDownRight,
  ShoppingCart,
  FileText,
  BarChart3
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
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

interface SalesData {
  date: string
  total_orders: number
  subtotal: number | string
  tax: number | string
  discount: number | string
  total: number | string
  total_cost?: number | string
  profit?: number | string
}

interface TopProduct {
  product_name: string
  total_quantity: number
  total_revenue: number | string
}

interface PaymentMethod {
  payment_method: string
  count: number
  total: number | string
}

interface IndividualSale {
  id: number
  sale_number: string
  total: number | string
  profit: number | string
  created_at: string
  day_of_week: string
  day_index: number
}

const props = defineProps<{
  sales_data: SalesData[]
  individual_sales: IndividualSale[]
  top_products: TopProduct[]
  payment_methods: PaymentMethod[]
  filters: {
    start_date: string
    end_date: string
  }
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

// Computed stats
const totalRevenue = computed(() => {
  return props.sales_data.reduce((sum, day) => {
    const total = typeof day.total === 'string' ? parseFloat(day.total) : day.total
    return sum + total
  }, 0)
})

const totalOrders = computed(() => {
  return props.sales_data.reduce((sum, day) => sum + day.total_orders, 0)
})

const avgOrderValue = computed(() => {
  return totalOrders.value > 0 ? totalRevenue.value / totalOrders.value : 0
})

const totalTax = computed(() => {
  return props.sales_data.reduce((sum, day) => {
    const tax = typeof day.tax === 'string' ? parseFloat(day.tax) : day.tax
    return sum + tax
  }, 0)
})

const totalDiscount = computed(() => {
  return props.sales_data.reduce((sum, day) => {
    const discount = typeof day.discount === 'string' ? parseFloat(day.discount) : day.discount
    return sum + discount
  }, 0)
})

// Chart data for bar chart - Last 7 days of sales
const chartData = computed(() => {
  // Take last 7 days
  const last7Days = props.sales_data.slice(-7)

  return last7Days.map(d => {
    const date = new Date(d.date)
    const dayName = date.toLocaleDateString('en-US', { weekday: 'short' })
    const dayNum = date.getDate()
    const total = typeof d.total === 'string' ? parseFloat(d.total) : (d.total || 0)

    return {
      label: `${dayName} ${dayNum}`,
      dayName: dayName,
      value: total,
      orders: d.total_orders
    }
  })
})

// Get different color for each day of week (7 colors)
const getDayColor = (index: number) => {
  const colors = [
    '#3B82F6', // Blue
    '#10B981', // Green
    '#F59E0B', // Amber
    '#EF4444', // Red
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#06B6D4', // Cyan
  ]
  return colors[index % 7]
}

// Calculate max value for Y-axis
const maxSales = computed(() => {
  if (chartData.value.length === 0) return 100
  const values = chartData.value.map(d => d.value)
  return Math.max(...values, 100)
})

// Apply filters
const applyFilters = () => {
  router.get('/reports/sales', {
    start_date: startDate.value,
    end_date: endDate.value,
  }, {
    preserveState: true,
  })
}

// Export functions
const exportToPDF = () => {
  window.open(`/reports/sales/export?format=pdf&start_date=${startDate.value}&end_date=${endDate.value}`, '_blank')
}

const exportToCSV = () => {
  window.location.href = `/reports/sales/export?format=csv&start_date=${startDate.value}&end_date=${endDate.value}`
}

const exportToExcel = () => {
  window.location.href = `/reports/sales/export?format=excel&start_date=${startDate.value}&end_date=${endDate.value}`
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

  <AppLayout title="Sales Reports">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-48 h-48 sm:w-96 sm:h-96 bg-white/10 rounded-full -mr-24 sm:-mr-48 -mt-24 sm:-mt-48"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <BarChart3 class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Sales Analytics</h1>
                  <p class="text-blue-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">Comprehensive sales insights</p>
                </div>
              </div>
            </div>
            <div class="flex gap-2 flex-wrap">
              <Button @click="exportToPDF" variant="outline" class="border-white text-white hover:bg-white/20 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
                <FileText class="h-3 w-3 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">PDF</span>
              </Button>
              <Button @click="exportToCSV" variant="outline" class="border-white text-white hover:bg-white/20 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
                <Download class="h-3 w-3 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">CSV</span>
              </Button>
              <Button @click="exportToExcel" variant="outline" class="border-white text-white hover:bg-white/20 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
                <Download class="h-3 w-3 sm:h-5 sm:w-5" />
                <span class="hidden sm:inline">Excel</span>
                <span class="sm:hidden">XLS</span>
              </Button>
            </div>
          </div>
        </div>

        <!-- Date Filters - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-4 sm:pt-6 p-3 sm:p-6">
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-end">
              <div class="flex-1 min-w-0">
                <Label for="start-date" class="text-xs sm:text-sm">Start Date</Label>
                <Input
                  id="start-date"
                  v-model="startDate"
                  type="date"
                  class="mt-1 sm:mt-2 h-10 sm:h-11 text-sm sm:text-base"
                />
              </div>
              <div class="flex-1 min-w-0">
                <Label for="end-date" class="text-xs sm:text-sm">End Date</Label>
                <Input
                  id="end-date"
                  v-model="endDate"
                  type="date"
                  class="mt-1 sm:mt-2 h-10 sm:h-11 text-sm sm:text-base"
                />
              </div>
              <Button @click="applyFilters" class="h-10 sm:h-11 px-4 sm:px-6 gap-1 sm:gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-xs sm:text-sm">
                <Filter class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">Apply Filters</span>
                <span class="xs:hidden">Apply</span>
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Key Metrics - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-4 grid-cols-2 lg:grid-cols-4">
          <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full -mr-8 sm:-mr-12 -mt-8 sm:-mt-12"></div>
            <CardHeader class="relative z-10 pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-white/80 flex items-center gap-1 sm:gap-2">
                <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Revenue</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(totalRevenue) }}</div>
              <div class="flex items-center gap-1 mt-1 sm:mt-2 text-white/80">
                <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="text-xs sm:text-sm truncate">{{ totalOrders }} orders</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-indigo-500 to-indigo-600 text-white overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full -mr-8 sm:-mr-12 -mt-8 sm:-mt-12"></div>
            <CardHeader class="relative z-10 pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-white/80 flex items-center gap-1 sm:gap-2">
                <ShoppingCart class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Orders</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold">{{ totalOrders }}</div>
              <div class="flex items-center gap-1 mt-1 sm:mt-2 text-white/80">
                <Calendar class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="text-xs sm:text-sm truncate">{{ sales_data.length }} days</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full -mr-8 sm:-mr-12 -mt-8 sm:-mt-12"></div>
            <CardHeader class="relative z-10 pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-white/80 flex items-center gap-1 sm:gap-2">
                <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Avg Order</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(avgOrderValue) }}</div>
              <div class="flex items-center gap-1 mt-1 sm:mt-2 text-white/80">
                <ArrowUpRight class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="text-xs sm:text-sm truncate">Per transaction</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-gradient-to-br from-pink-500 to-pink-600 text-white overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full -mr-8 sm:-mr-12 -mt-8 sm:-mt-12"></div>
            <CardHeader class="relative z-10 pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-white/80 flex items-center gap-1 sm:gap-2">
                <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Tax Collected</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="relative z-10 p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold truncate">{{ formatCurrency(totalTax) }}</div>
              <div class="flex items-center gap-1 mt-1 sm:mt-2 text-white/80">
                <span class="text-xs sm:text-sm">VAT 16%</span>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Sales Trend Chart - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white">
          <CardHeader class="p-4 sm:p-6">
            <CardTitle class="text-base sm:text-xl lg:text-2xl flex items-center gap-2">
              <BarChart3 class="h-5 w-5 sm:h-6 sm:w-6" />
              <span class="truncate">Sales Chart - Last 7 Days</span>
            </CardTitle>
            <CardDescription class="text-xs sm:text-sm">Daily sales performance</CardDescription>
          </CardHeader>
          <CardContent class="p-3 sm:p-6 pt-0">
            <div v-if="sales_data && sales_data.length > 0" class="space-y-4">
              <!-- Bar Chart -->
              <div class="relative h-96 bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg p-8">
                <!-- Y-axis labels (Sales) -->
                <div class="absolute left-2 top-8 bottom-20 flex flex-col justify-between text-xs text-slate-500">
                  <span>{{ formatCurrency(maxSales) }}</span>
                  <span>{{ formatCurrency(maxSales * 0.75) }}</span>
                  <span>{{ formatCurrency(maxSales * 0.5) }}</span>
                  <span>{{ formatCurrency(maxSales * 0.25) }}</span>
                  <span>{{ formatCurrency(0) }}</span>
                </div>

                <!-- Y-axis label (vertical) -->
                <div class="absolute left-0 top-1/2 -translate-y-1/2 -rotate-90 text-sm font-semibold text-slate-600">
                  Sales Amount
                </div>

                <!-- Chart area -->
                <div class="ml-24 mr-4 h-full relative pb-16">
                  <!-- Grid lines -->
                  <div class="absolute inset-0 bottom-16 flex flex-col justify-between">
                    <div class="border-t border-slate-200"></div>
                    <div class="border-t border-slate-200"></div>
                    <div class="border-t border-slate-200"></div>
                    <div class="border-t border-slate-200"></div>
                    <div class="border-t border-slate-300"></div>
                  </div>

                  <!-- SVG for bar chart -->
                  <svg class="w-full h-full" viewBox="0 0 700 300" preserveAspectRatio="none">
                    <!-- Draw bars for each day -->
                    <g v-for="(day, index) in chartData" :key="index">
                      <!-- Bar -->
                      <rect
                        :x="(index / chartData.length) * 700 + 30"
                        :y="300 - (day.value / maxSales * 300)"
                        :width="(700 / chartData.length) - 60"
                        :height="(day.value / maxSales * 300)"
                        :fill="getDayColor(index)"
                        opacity="0.9"
                        rx="4"
                        class="cursor-pointer hover:opacity-100 transition-all"
                      >
                        <title>{{ day.label }}
Sales: {{ formatCurrency(day.value) }}
Orders: {{ day.orders }}</title>
                      </rect>

                      <!-- Value label on top of bar -->
                      <text
                        :x="(index / chartData.length) * 700 + (700 / chartData.length / 2)"
                        :y="300 - (day.value / maxSales * 300) - 10"
                        text-anchor="middle"
                        class="text-xs font-semibold fill-slate-700"
                      >
                        {{ formatCurrency(day.value) }}
                      </text>
                    </g>
                  </svg>

                  <!-- X-axis labels (Days) -->
                  <div class="absolute -bottom-12 left-0 right-0 flex justify-around text-sm text-slate-600 font-medium">
                    <div
                      v-for="(day, index) in chartData"
                      :key="index"
                      class="flex flex-col items-center gap-1"
                    >
                      <div
                        class="w-4 h-4 rounded"
                        :style="{ backgroundColor: getDayColor(index) }"
                      ></div>
                      <span class="text-xs">{{ day.label }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Chart Summary -->
              <div class="grid grid-cols-3 gap-4 p-4 bg-slate-50 rounded-lg">
                <div class="text-center">
                  <div class="text-sm text-slate-600 mb-1">Total Sales (7 Days)</div>
                  <div class="text-2xl font-bold text-blue-600">
                    {{ formatCurrency(chartData.reduce((sum, d) => sum + d.value, 0)) }}
                  </div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-slate-600 mb-1">Total Orders</div>
                  <div class="text-2xl font-bold text-green-600">
                    {{ chartData.reduce((sum, d) => sum + d.orders, 0) }}
                  </div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-slate-600 mb-1">Average per Day</div>
                  <div class="text-2xl font-bold text-purple-600">
                    {{ formatCurrency(chartData.reduce((sum, d) => sum + d.value, 0) / chartData.length) }}
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-12 text-slate-500">
              <BarChart3 class="h-12 w-12 text-slate-300 mb-4" />
              <p class="text-lg font-medium">No sales data available</p>
              <p class="text-sm">Create some sales to see the bar chart</p>
            </div>
          </CardContent>
        </Card>

        <!-- Daily Sales Table & Top Products -->
        <div class="grid gap-6 md:grid-cols-2">
          <!-- Daily Sales -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader>
              <CardTitle class="text-xl">Daily Sales Breakdown</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
              <div class="max-h-96 overflow-y-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead class="text-right">Orders</TableHead>
                      <TableHead class="text-right">Revenue</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-if="sales_data.length === 0">
                      <TableCell colspan="3" class="text-center py-8 text-slate-500">
                        No sales data available
                      </TableCell>
                    </TableRow>
                    <TableRow v-for="day in sales_data" :key="day.date" class="hover:bg-blue-50">
                      <TableCell class="font-medium">
                        {{ new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                      </TableCell>
                      <TableCell class="text-right">
                        <Badge variant="outline">{{ day.total_orders }}</Badge>
                      </TableCell>
                      <TableCell class="text-right font-semibold text-blue-600">
                        {{ formatCurrency(day.total) }}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>

          <!-- Top Products -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader>
              <CardTitle class="text-xl">Top Selling Products</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
              <div class="max-h-96 overflow-y-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Product</TableHead>
                      <TableHead class="text-right">Qty</TableHead>
                      <TableHead class="text-right">Revenue</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-if="top_products.length === 0">
                      <TableCell colspan="3" class="text-center py-8 text-slate-500">
                        No product data available
                      </TableCell>
                    </TableRow>
                    <TableRow v-for="(product, index) in top_products" :key="index" class="hover:bg-green-50">
                      <TableCell class="font-medium">
                        <div class="flex items-center gap-2">
                          <Badge class="bg-green-100 text-green-800">{{ index + 1 }}</Badge>
                          <span>{{ product.product_name }}</span>
                        </div>
                      </TableCell>
                      <TableCell class="text-right">
                        <span class="font-semibold">{{ product.total_quantity }}</span>
                      </TableCell>
                      <TableCell class="text-right font-semibold text-green-600">
                        {{ formatCurrency(product.total_revenue) }}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Payment Methods -->
        <Card class="border-0 shadow-xl bg-white">
          <CardHeader>
            <CardTitle class="text-xl">Payment Methods Breakdown</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="payment_methods.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <div
                v-for="method in payment_methods"
                :key="method.payment_method"
                class="p-4 rounded-lg border-2 border-slate-100 hover:border-blue-200 transition-colors"
              >
                <div class="flex items-center justify-between mb-2">
                  <Badge :class="getPaymentMethodColor(method.payment_method)">
                    {{ method.payment_method }}
                  </Badge>
                  <span class="text-sm text-slate-600">{{ method.count }} txns</span>
                </div>
                <div class="text-2xl font-bold text-slate-900">
                  {{ formatCurrency(method.total) }}
                </div>
                <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-blue-500 to-indigo-600"
                    :style="{ width: `${(Number(method.total) / totalRevenue) * 100}%` }"
                  ></div>
                </div>
                <div class="mt-1 text-xs text-slate-500">
                  {{ ((Number(method.total) / totalRevenue) * 100).toFixed(1) }}% of total
                </div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-12 text-slate-500">
              <DollarSign class="h-12 w-12 text-slate-300 mb-4" />
              <p class="text-lg font-medium">No payment data available</p>
            </div>
          </CardContent>
        </Card>

        <!-- Summary Card -->
        <Card class="border-0 shadow-xl bg-gradient-to-br from-slate-800 to-slate-900 text-white">
          <CardHeader>
            <CardTitle class="text-2xl">Period Summary</CardTitle>
            <CardDescription class="text-slate-300">
              {{ new Date(filters.start_date).toLocaleDateString() }} - {{ new Date(filters.end_date).toLocaleDateString() }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-6 md:grid-cols-3">
              <div>
                <div class="text-sm text-slate-400 mb-2">Total Revenue</div>
                <div class="text-3xl font-bold">{{ formatCurrency(totalRevenue) }}</div>
              </div>
              <div>
                <div class="text-sm text-slate-400 mb-2">Tax Collected (16%)</div>
                <div class="text-3xl font-bold text-green-400">{{ formatCurrency(totalTax) }}</div>
              </div>
              <div>
                <div class="text-sm text-slate-400 mb-2">Discounts Given</div>
                <div class="text-3xl font-bold text-orange-400">{{ formatCurrency(totalDiscount) }}</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

