<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  TrendingUp,
  Search,
  Filter,
  Download,
  Eye,
  RefreshCw,
  Calendar,
  DollarSign,
  ShoppingCart
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

interface Sale {
  id: number
  sale_number: string
  cashier: { name: string }
  customer: { name: string } | null
  total: number
  status: string
  created_at: string
}

const props = defineProps<{
  sales: {
    data: Sale[]
    current_page: number
    last_page: number
    total: number
  }
  filters?: {
    search?: string
    status?: string
    date_from?: string
    date_to?: string
  }
  stats?: {
    today_revenue: number
    total_sales: number
    avg_sale_value: number
  }
}>()

const search = ref(props.filters?.search || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

// Calculate stats from actual sales data if not provided
const todayRevenue = computed(() => {
  if (props.stats?.today_revenue !== undefined) {
    return props.stats.today_revenue
  }
  const today = new Date().toISOString().split('T')[0]
  return props.sales.data
    .filter(sale => sale.created_at.startsWith(today) && sale.status === 'completed')
    .reduce((sum, sale) => sum + Number(sale.total), 0)
})

const totalSales = computed(() => {
  return props.stats?.total_sales || props.sales.total || props.sales.data.length
})

const avgSaleValue = computed(() => {
  if (props.stats?.avg_sale_value !== undefined) {
    return props.stats.avg_sale_value
  }
  const completedSales = props.sales.data.filter(sale => sale.status === 'completed')
  if (completedSales.length === 0) return 0
  const total = completedSales.reduce((sum, sale) => sum + Number(sale.total), 0)
  return total / completedSales.length
})

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    'completed': 'bg-emerald-100 text-emerald-800',
    'refunded': 'bg-red-100 text-red-800',
    'pending': 'bg-yellow-100 text-yellow-800',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

// Apply filters
const applyFilters = () => {
  router.get('/sales', {
    search: search.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Export to CSV
const exportSales = () => {
  const params = new URLSearchParams({
    export: 'csv',
    search: search.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  })
  window.location.href = `/sales/export?${params.toString()}`
}

// View sale details
const viewSale = (saleId: number) => {
  router.visit(`/sales/${saleId}`)
}

// Download receipt
const downloadReceipt = (saleId: number) => {
  window.open(`/sales/${saleId}/receipt`, '_blank')
}

// Refund sale
const refundSale = (saleId: number) => {
  if (confirm('Are you sure you want to refund this sale? This action cannot be undone.')) {
    router.post(`/sales/${saleId}/refund`, {
      reason: 'Customer request',
      items: [], // Will need to implement proper refund UI
    }, {
      onSuccess: () => {
        alert('Sale refunded successfully')
      },
      onError: () => {
        alert('Failed to refund sale')
      }
    })
  }
}
</script>

<template>
  <Head title="Sales History" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-cyan-600 to-teal-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10">
            <div class="flex items-center justify-between">
              <div>
                <div class="flex items-center gap-3 mb-2">
                  <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                    <TrendingUp class="h-8 w-8" />
                  </div>
                  <div>
                    <h1 class="text-4xl font-bold">Sales History</h1>
                    <p class="text-blue-100 text-lg mt-1">All transactions and orders</p>
                  </div>
                </div>
              </div>
              <div class="flex gap-3">
                <Button @click="router.visit('/sales/create')" class="bg-white text-blue-600 hover:bg-blue-50 gap-2">
                  <ShoppingCart class="h-5 w-5" />
                  New Sale
                </Button>
                <Button @click="exportSales" variant="outline" class="border-white text-white hover:bg-white/20 gap-2">
                  <Download class="h-5 w-5" />
                  Export
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-6">
            <div class="flex flex-wrap gap-4">
              <div class="flex-1 min-w-[300px]">
                <div class="relative">
                  <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search by sale number, customer..."
                    class="pl-12 h-12 border-2"
                  />
                </div>
              </div>
              <div class="flex gap-3">
                <Input
                  v-model="dateFrom"
                  type="date"
                  class="h-12 border-2"
                  placeholder="From"
                />
                <Input
                  v-model="dateTo"
                  type="date"
                  class="h-12 border-2"
                  placeholder="To"
                />
                <Button @click="applyFilters" class="h-12 px-6 bg-gradient-to-r from-blue-600 to-cyan-600 gap-2">
                  <Filter class="h-5 w-5" />
                  Filter
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Quick Stats -->
        <div class="grid gap-4 md:grid-cols-3">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <DollarSign class="h-4 w-4" />
                Today's Revenue
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-blue-600">{{ formatCurrency(todayRevenue) }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <ShoppingCart class="h-4 w-4" />
                Total Sales
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-cyan-600">{{ totalSales }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <TrendingUp class="h-4 w-4" />
                Avg Sale Value
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-teal-600">{{ formatCurrency(avgSaleValue) }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Sales Table -->
        <Card class="border-0 shadow-2xl bg-white">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <CardTitle class="text-2xl">All Sales Transactions</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold">Sale #</TableHead>
                  <TableHead class="font-semibold">Date & Time</TableHead>
                  <TableHead class="font-semibold">Cashier</TableHead>
                  <TableHead class="font-semibold">Customer</TableHead>
                  <TableHead class="font-semibold">Total</TableHead>
                  <TableHead class="font-semibold">Status</TableHead>
                  <TableHead class="text-right font-semibold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="props.sales.data.length === 0">
                  <TableCell colspan="7" class="text-center py-12">
                    <div class="flex flex-col items-center gap-3 text-slate-500">
                      <ShoppingCart class="h-12 w-12 text-slate-300" />
                      <p class="text-lg font-medium">No sales found</p>
                      <p class="text-sm">Sales transactions will appear here</p>
                      <Button @click="router.visit('/sales/create')" class="mt-4">
                        Create New Sale
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow
                  v-for="sale in props.sales.data"
                  :key="sale.id"
                  class="hover:bg-blue-50/50 transition-colors"
                >
                  <TableCell>
                    <div class="font-mono font-semibold text-blue-600">{{ sale.sale_number }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <Calendar class="h-4 w-4 text-slate-400" />
                      <span class="text-sm">{{ new Date(sale.created_at).toLocaleString() }}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-sm">{{ sale.cashier.name }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="text-sm">{{ sale.customer?.name || 'Walk-in' }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="text-lg font-bold text-slate-900">{{ formatCurrency(sale.total) }}</div>
                  </TableCell>
                  <TableCell>
                    <Badge :class="getStatusColor(sale.status)">
                      {{ sale.status }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button
                        @click="viewSale(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-blue-100"
                        title="View Details"
                      >
                        <Eye class="h-4 w-4" />
                      </Button>
                      <Button
                        @click="downloadReceipt(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-green-100"
                        title="Download Receipt"
                      >
                        <Download class="h-4 w-4" />
                      </Button>
                      <Button
                        v-if="sale.status === 'completed'"
                        @click="refundSale(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-red-100"
                        title="Refund Sale"
                      >
                        <RefreshCw class="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

