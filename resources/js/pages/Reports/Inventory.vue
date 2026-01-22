<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-4xl font-bold text-slate-900 flex items-center gap-3">
              <div class="rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-3">
                <Package class="h-8 w-8 text-white" />
              </div>
              Inventory Analytics
            </h1>
            <p class="mt-2 text-slate-600">Real-time stock monitoring and valuation</p>
          </div>
          <div class="flex gap-3">
            <Button @click="exportToPDF" variant="outline" class="gap-2">
              <FileText class="h-4 w-4" />
              PDF
            </Button>
            <Button @click="exportToCSV" variant="outline" class="gap-2">
              <Download class="h-4 w-4" />
              CSV
            </Button>
            <Button @click="exportToExcel" variant="outline" class="gap-2">
              <Download class="h-4 w-4" />
              Excel
            </Button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid gap-6 md:grid-cols-4">
          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm font-medium text-slate-600 flex items-center gap-2">
                <DollarSign class="h-4 w-4" />
                Total Value
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-emerald-600">{{ formatCurrency(totalInventoryValue) }}</div>
              <p class="text-xs text-slate-500 mt-1">Current stock worth</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm font-medium text-slate-600 flex items-center gap-2">
                <Package class="h-4 w-4" />
                Total Products
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-blue-600">{{ totalProducts }}</div>
              <p class="text-xs text-slate-500 mt-1">Active items</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm font-medium text-slate-600 flex items-center gap-2">
                <AlertTriangle class="h-4 w-4" />
                Low Stock
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-red-600">{{ lowStockCount }}</div>
              <p class="text-xs text-slate-500 mt-1">Needs restocking</p>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm font-medium text-slate-600 flex items-center gap-2">
                <TrendingUp class="h-4 w-4" />
                Categories
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-purple-600">{{ value_by_category.length }}</div>
              <p class="text-xs text-slate-500 mt-1">Product categories</p>
            </CardContent>
          </Card>
        </div>

        <!-- Low Stock Alerts -->
        <Card v-if="low_stock_items.length > 0" class="border-0 shadow-xl bg-gradient-to-r from-red-500 to-orange-500 text-white">
          <CardHeader>
            <CardTitle class="text-white flex items-center gap-2">
              <AlertTriangle class="h-6 w-6" />
              🚨 Low Stock Alerts
            </CardTitle>
            <CardDescription class="text-white/80">{{ low_stock_items.length }} items need immediate attention</CardDescription>
          </CardHeader>
          <CardContent>
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

