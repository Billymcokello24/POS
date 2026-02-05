f<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
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
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/AppLayout.vue'

// ... existing imports

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
    cashier_id?: string
  }
  stats?: {
    today_revenue: number
    total_sales: number
    avg_sale_value: number
  }
  cashiers?: Array<{ id: number, name: string }>
}>()

const search = ref(props.filters?.search || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')
const selectedCashier = ref(props.filters?.cashier_id || 'ALL')

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
    cashier_id: selectedCashier.value !== 'ALL' ? selectedCashier.value : null,
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

  <AppLayout title="Sales">
     <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-blue-600 via-cyan-600 to-teal-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 sm:gap-3 mb-2">
                  <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                    <TrendingUp class="h-5 w-5 sm:h-8 sm:w-8" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Sales History</h1>
                    <p class="text-blue-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">All transactions and orders</p>
                  </div>
                </div>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <Button @click="router.visit('/sales/create')" class="bg-white text-blue-600 hover:bg-blue-50 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial">
                  <ShoppingCart class="h-4 w-4 sm:h-5 sm:w-5" />
                  <span class="hidden xs:inline">New Sale</span>
                  <span class="xs:hidden">New</span>
                </Button>
                <Button @click="exportSales" variant="outline" class="border-white text-white hover:bg-white/20 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm">
                  <Download class="h-4 w-4 sm:h-5 sm:w-5" />
                  <span class="hidden sm:inline">Export</span>
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-4 sm:pt-6 p-3 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-4">
              <div class="flex-1 min-w-0 sm:min-w-[300px]">
                <div class="relative">
                  <Search class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-4 w-4 sm:h-5 sm:w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search sales..."
                    class="pl-10 sm:pl-12 h-10 sm:h-12 border-2 text-sm sm:text-base"
                  />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-4">
                <Input
                  v-model="dateFrom"
                  type="date"
                  class="h-10 sm:h-12 border-2 text-sm sm:text-base"
                  placeholder="From"
                />
                <Input
                  v-model="dateTo"
                  type="date"
                  class="h-10 sm:h-12 border-2 text-sm sm:text-base"
                  placeholder="To"
                />
              </div>

                <!-- Cashier Filter (Admin Only) -->
                <div v-if="cashiers && cashiers.length > 0" class="min-w-0 sm:min-w-[150px]">
                  <Select v-model="selectedCashier">
                    <SelectTrigger class="h-10 sm:h-12 border-2 bg-white text-sm sm:text-base">
                      <SelectValue placeholder="All Cashiers" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="ALL">All Cashiers</SelectItem>
                      <SelectItem v-for="c in cashiers" :key="c.id" :value="String(c.id)">
                        {{ c.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <Button @click="applyFilters" class="h-10 sm:h-12 px-4 sm:px-6 bg-gradient-to-r from-blue-600 to-cyan-600 gap-1 sm:gap-2 text-xs sm:text-sm">
                  <Filter class="h-4 w-4 sm:h-5 sm:w-5" />
                  Filter
                </Button>
              </div>
          </CardContent>
        </Card>

        <!-- Quick Stats - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-4 grid-cols-1 xs:grid-cols-2 md:grid-cols-3">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                Today's Revenue
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-blue-600 truncate">{{ formatCurrency(todayRevenue) }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <ShoppingCart class="h-3 w-3 sm:h-4 sm:w-4" />
                Total Sales
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-cyan-600">{{ totalSales }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4" />
                Avg Sale Value
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-teal-600 truncate">{{ formatCurrency(avgSaleValue) }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Sales Table - Mobile Optimized -->
        <Card class="border-0 shadow-2xl bg-white">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100 p-3 sm:p-6">
            <CardTitle class="text-base sm:text-xl lg:text-2xl truncate">All Sales Transactions</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <div class="overflow-x-auto">
            <Table class="min-w-full">
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Sale #</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Date & Time</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden md:table-cell">Cashier</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden lg:table-cell">Customer</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Total</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden sm:table-cell">Status</TableHead>
                  <TableHead class="text-right font-semibold text-xs sm:text-sm px-2 sm:px-4">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="props.sales.data.length === 0">
                  <TableCell colspan="7" class="text-center py-8 sm:py-12 px-3">
                    <div class="flex flex-col items-center gap-2 sm:gap-3 text-slate-500">
                      <ShoppingCart class="h-8 w-8 sm:h-12 sm:w-12 text-slate-300" />
                      <p class="text-base sm:text-lg font-medium">No sales found</p>
                      <p class="text-xs sm:text-sm">Sales transactions will appear here</p>
                      <Button @click="router.visit('/sales/create')" class="mt-2 sm:mt-4 h-9 sm:h-10 text-sm">
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
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="font-mono font-semibold text-blue-600 text-xs sm:text-sm">{{ sale.sale_number }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Calendar class="h-3 w-3 sm:h-4 sm:w-4 text-slate-400 flex-shrink-0" />
                      <span class="text-xs sm:text-sm">{{ new Date(sale.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                    <div class="text-xs sm:text-sm truncate max-w-[100px]">{{ sale.cashier.name }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                    <div class="text-xs sm:text-sm truncate max-w-[100px]">{{ sale.customer?.name || 'Walk-in' }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="text-sm sm:text-base lg:text-lg font-bold text-slate-900">{{ formatCurrency(sale.total) }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">
                    <Badge :class="getStatusColor(sale.status)" class="text-xs">
                      {{ sale.status }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right px-2 sm:px-4 py-2 sm:py-3">
                    <div class="flex justify-end gap-1">
                      <Button
                        @click="viewSale(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-blue-100 h-8 w-8 p-0"
                        title="View Details"
                      >
                        <Eye class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                      </Button>
                      <Button
                        @click="downloadReceipt(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-green-100 h-8 w-8 p-0 hidden sm:inline-flex"
                        title="Download Receipt"
                      >
                        <Download class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                      </Button>
                      <Button
                        v-if="sale.status === 'completed'"
                        @click="refundSale(sale.id)"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-red-100 h-8 w-8 p-0 hidden md:inline-flex"
                        title="Refund Sale"
                      >
                        <RefreshCw class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

