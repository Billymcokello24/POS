<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import {
  Package,
  AlertTriangle,
  TrendingDown,
  TrendingUp,
  DollarSign,
  Search,
  Download,
  ArrowUpDown,
  FileText
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
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

interface Product {
  id: number
  name: string
  sku: string
  category: { name: string } | null
  quantity: number
  reorder_level: number
  cost_price: number
  selling_price: number
  inventory_value: number
}

interface CategoryValue {
  category: string
  value: number
  product_count: number
}

const props = defineProps<{
  inventory_status: Product[]
  low_stock_items: Product[]
  movements: any
  value_by_category: CategoryValue[]
}>()

const searchQuery = ref('')

const totalInventoryValue = props.inventory_status.reduce((sum, p) => sum + parseFloat(p.inventory_value.toString()), 0)
const totalProducts = props.inventory_status.length
const lowStockCount = props.low_stock_items.length

const filteredProducts = ref(props.inventory_status)

const searchProducts = () => {
  if (!searchQuery.value) {
    filteredProducts.value = props.inventory_status
  } else {
    filteredProducts.value = props.inventory_status.filter(p =>
      p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      p.sku.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }
}

// Export functions
const exportToPDF = () => {
  window.open('/reports/inventory/export?format=pdf', '_blank')
}

const exportToCSV = () => {
  window.location.href = '/reports/inventory/export?format=csv'
}

const exportToExcel = () => {
  window.location.href = '/reports/inventory/export?format=excel'
}
</script>

<template>
  <Head title="Inventory Reports" />

  <AppLayout title="Inventory Reports">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 flex items-center gap-2 sm:gap-3">
              <div class="rounded-lg sm:rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-2 sm:p-3 flex-shrink-0">
                <Package class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
              </div>
              <span class="truncate">Inventory Analytics</span>
            </h1>
            <p class="mt-1 sm:mt-2 text-slate-600 text-xs sm:text-sm truncate">Real-time stock monitoring</p>
          </div>
          <div class="flex gap-2 flex-wrap">
            <Button @click="exportToPDF" variant="outline" class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
              <FileText class="h-3 w-3 sm:h-4 sm:w-4" />
              <span class="hidden xs:inline">PDF</span>
            </Button>
            <Button @click="exportToCSV" variant="outline" class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
              <Download class="h-3 w-3 sm:h-4 sm:w-4" />
              <span class="hidden xs:inline">CSV</span>
            </Button>
            <Button @click="exportToExcel" variant="outline" class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
              <Download class="h-3 w-3 sm:h-4 sm:w-4" />
              <span class="hidden sm:inline">Excel</span>
              <span class="sm:hidden">XLS</span>
            </Button>
          </div>
        </div>

        <!-- Key Metrics - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-6 grid-cols-2 md:grid-cols-4">
          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm font-medium text-slate-600 flex items-center gap-1 sm:gap-2">
                <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Value</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-emerald-600 truncate">{{ formatCurrency(totalInventoryValue) }}</div>
              <p class="text-[10px] sm:text-xs text-slate-500 mt-1 truncate">Current stock worth</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm font-medium text-slate-600 flex items-center gap-1 sm:gap-2">
                <Package class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Products</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-blue-600">{{ totalProducts }}</div>
              <p class="text-[10px] sm:text-xs text-slate-500 mt-1 truncate">Active items</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm font-medium text-slate-600 flex items-center gap-1 sm:gap-2">
                <AlertTriangle class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Low Stock</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-red-600">{{ lowStockCount }}</div>
              <p class="text-[10px] sm:text-xs text-slate-500 mt-1 truncate">Needs restocking</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm font-medium text-slate-600 flex items-center gap-1 sm:gap-2">
                <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Categories</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-purple-600">{{ value_by_category.length }}</div>
              <p class="text-[10px] sm:text-xs text-slate-500 mt-1 truncate">Product categories</p>
            </CardContent>
          </Card>
        </div>

        <!-- Low Stock Alerts - Mobile Optimized -->
        <Card v-if="low_stock_items.length > 0" class="border-0 shadow-xl bg-gradient-to-r from-red-500 to-orange-500 text-white">
          <CardHeader class="p-4 sm:p-6">
            <CardTitle class="text-white flex items-center gap-2 text-base sm:text-lg">
              <AlertTriangle class="h-5 w-5 sm:h-6 sm:w-6" />
              <span class="truncate">🚨 Low Stock Alerts</span>
            </CardTitle>
            <CardDescription class="text-white/80 text-xs sm:text-sm">{{ low_stock_items.length }} items need attention</CardDescription>
          </CardHeader>
          <CardContent class="p-3 sm:p-6 pt-0">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="product in low_stock_items.slice(0, 6)"
                :key="product.id"
                class="bg-white/20 backdrop-blur rounded-lg p-4"
              >
                <div class="font-semibold">{{ product.name }}</div>
                <div class="text-sm text-white/80 mt-1">{{ product.sku }}</div>
                <div class="mt-2 flex items-center justify-between">
                  <span class="text-2xl font-bold">{{ product.quantity }}</span>
                  <span class="text-sm">Min: {{ product.reorder_level }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Category Value Breakdown -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Package class="h-5 w-5 text-emerald-600" />
              Inventory Value by Category
            </CardTitle>
            <CardDescription>Stock distribution across categories</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div
                v-for="category in value_by_category"
                :key="category.category"
                class="group"
              >
                <div class="flex items-center justify-between mb-2">
                  <div>
                    <div class="font-semibold text-slate-900">{{ category.category }}</div>
                    <div class="text-sm text-slate-600">{{ category.product_count }} products</div>
                  </div>
                  <div class="text-right">
                    <div class="text-2xl font-bold text-emerald-600">{{ formatCurrency(category.value) }}</div>
                    <div class="text-xs text-slate-500">{{ ((parseFloat(category.value.toString()) / totalInventoryValue) * 100).toFixed(1) }}%</div>
                  </div>
                </div>
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-emerald-400 to-teal-600 rounded-full transition-all group-hover:from-emerald-500 group-hover:to-teal-700"
                    :style="{ width: `${(parseFloat(category.value.toString()) / totalInventoryValue) * 100}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Product List -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardHeader>
            <div class="flex items-center justify-between">
              <div>
                <CardTitle class="flex items-center gap-2">
                  <ArrowUpDown class="h-5 w-5 text-blue-600" />
                  Current Stock Levels
                </CardTitle>
                <CardDescription>All products sorted by quantity</CardDescription>
              </div>
              <div class="relative w-64">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <Input
                  v-model="searchQuery"
                  @input="searchProducts"
                  placeholder="Search products..."
                  class="pl-10"
                />
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div
                v-for="product in filteredProducts.slice(0, 15)"
                :key="product.id"
                class="group flex items-center gap-4 p-4 rounded-xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-200"
              >
                <div class="flex-1">
                  <div class="font-semibold text-slate-900">{{ product.name }}</div>
                  <div class="flex items-center gap-3 mt-1">
                    <Badge variant="outline">{{ product.sku }}</Badge>
                    <span class="text-sm text-slate-600">{{ product.category?.name || 'Uncategorized' }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <div class="flex items-center gap-2">
                    <TrendingDown v-if="product.quantity <= product.reorder_level" class="h-4 w-4 text-red-500" />
                    <TrendingUp v-else class="h-4 w-4 text-emerald-500" />
                    <span class="text-2xl font-bold" :class="product.quantity <= product.reorder_level ? 'text-red-600' : 'text-slate-900'">
                      {{ product.quantity }}
                    </span>
                  </div>
                  <div class="text-xs text-slate-500">Min: {{ product.reorder_level }}</div>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold text-emerald-600">{{ formatCurrency(product.inventory_value) }}</div>
                  <div class="text-xs text-slate-500">Value</div>
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

