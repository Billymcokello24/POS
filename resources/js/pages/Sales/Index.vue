<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
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
  sales?: {
    data: Sale[]
    current_page: number
    last_page: number
    total: number
  }
}>()

const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')

// Mock data for demonstration
const mockSales = [
  {
    id: 1,
    sale_number: 'DS000023',
    cashier: { name: 'Admin User' },
    customer: null,
    total: 45.00,
    status: 'completed',
    created_at: '2026-01-21 14:30:00'
  },
  {
    id: 2,
    sale_number: 'DS000022',
    cashier: { name: 'Admin User' },
    customer: { name: 'John Doe' },
    total: 750.00,
    status: 'completed',
    created_at: '2026-01-21 14:15:00'
  },
  {
    id: 3,
    sale_number: 'DS000021',
    cashier: { name: 'Cashier User' },
    customer: null,
    total: 125.50,
    status: 'completed',
    created_at: '2026-01-21 13:45:00'
  },
]

const sales = props.sales?.data || mockSales

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    'completed': 'bg-emerald-100 text-emerald-800',
    'refunded': 'bg-red-100 text-red-800',
    'pending': 'bg-yellow-100 text-yellow-800',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
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
                <Button variant="outline" class="border-white text-white hover:bg-white/20 gap-2">
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
                <Button class="h-12 px-6 bg-gradient-to-r from-blue-600 to-cyan-600 gap-2">
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
              <div class="text-3xl font-bold text-blue-600">$1,250.00</div>
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
              <div class="text-3xl font-bold text-cyan-600">{{ sales.length }}</div>
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
              <div class="text-3xl font-bold text-teal-600">$306.83</div>
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
                <TableRow
                  v-for="sale in sales"
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
                    <div class="text-lg font-bold text-slate-900">${{ sale.total.toFixed(2) }}</div>
                  </TableCell>
                  <TableCell>
                    <Badge :class="getStatusColor(sale.status)">
                      {{ sale.status }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button variant="ghost" size="sm" class="hover:bg-blue-100">
                        <Eye class="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="sm" class="hover:bg-orange-100">
                        <Download class="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="sm" class="hover:bg-red-100">
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

