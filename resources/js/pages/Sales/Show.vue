<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import {
  ArrowLeft,
  Download,
  RefreshCw,
  User,
  Calendar,
  DollarSign,
  CreditCard,
  Package,
  Receipt
} from 'lucide-vue-next'
import { computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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

interface SaleItem {
  id: number
  product_name: string
  product_sku: string
  quantity: number
  unit_price: number
  tax_amount: number
  discount_amount: number
  subtotal: number
  total: number
}

interface Payment {
  id: number
  payment_method: string
  amount: number
  reference_number: string | null
  status: string
}

interface Sale {
  id: number
  sale_number: string
  cashier: { name: string }
  customer: { name: string; email: string; phone: string } | null
  items: SaleItem[]
  payments: Payment[]
  subtotal: number
  tax_amount: number
  discount_amount: number
  total: number
  status: string
  notes: string | null
  created_at: string
  completed_at: string
}

const props = defineProps<{
  sale: Sale
}>()

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    'completed': 'bg-emerald-100 text-emerald-800',
    'refunded': 'bg-red-100 text-red-800',
    'pending': 'bg-yellow-100 text-yellow-800',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const downloadReceipt = () => {
  window.open(`/sales/${props.sale.id}/receipt`, '_blank')
}

const refundSale = () => {
  if (confirm('Are you sure you want to refund this sale? This action cannot be undone.')) {
    router.post(`/sales/${props.sale.id}/refund`, {
      reason: 'Customer request',
      items: props.sale.items.map(item => ({
        sale_item_id: item.id,
        quantity: item.quantity,
      })),
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
  <Head :title="`Sale ${sale.sale_number}`" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-gray-50 to-zinc-50 p-6">
      <div class="mx-auto max-w-5xl space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <Button @click="router.visit('/sales')" variant="outline" size="icon">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div>
              <h1 class="text-3xl font-bold text-slate-900">Sale Details</h1>
              <p class="text-slate-600 mt-1">{{ sale.sale_number }}</p>
            </div>
          </div>
          <div class="flex gap-3">
            <Button @click="downloadReceipt" class="gap-2">
              <Download class="h-5 w-5" />
              Download Receipt
            </Button>
            <Button
              v-if="sale.status === 'completed'"
              @click="refundSale"
              variant="destructive"
              class="gap-2"
            >
              <RefreshCw class="h-5 w-5" />
              Refund
            </Button>
          </div>
        </div>

        <!-- Status & Info -->
        <div class="grid gap-6 md:grid-cols-3">
          <Card class="border-0 shadow-lg">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <Receipt class="h-4 w-4" />
                Status
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Badge :class="getStatusColor(sale.status)" class="text-lg px-3 py-1">
                {{ sale.status }}
              </Badge>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-lg">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <Calendar class="h-4 w-4" />
                Date & Time
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-lg font-semibold">
                {{ new Date(sale.created_at).toLocaleString() }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-lg">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <DollarSign class="h-4 w-4" />
                Total Amount
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-emerald-600">
                {{ formatCurrency(sale.total) }}
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Customer & Cashier Info -->
        <div class="grid gap-6 md:grid-cols-2">
          <Card class="border-0 shadow-lg">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <User class="h-5 w-5" />
                Customer
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="sale.customer">
                <div class="text-lg font-semibold">{{ sale.customer.name }}</div>
                <div class="text-sm text-slate-600 mt-1">{{ sale.customer.email }}</div>
                <div class="text-sm text-slate-600">{{ sale.customer.phone }}</div>
              </div>
              <div v-else class="text-slate-500">Walk-in Customer</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-lg">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <User class="h-5 w-5" />
                Cashier
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-lg font-semibold">{{ sale.cashier.name }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Items -->
        <Card class="border-0 shadow-lg">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Package class="h-5 w-5" />
              Items ({{ sale.items.length }})
            </CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead class="text-right">Qty</TableHead>
                  <TableHead class="text-right">Unit Price</TableHead>
                  <TableHead class="text-right">Subtotal</TableHead>
                  <TableHead class="text-right">Tax</TableHead>
                  <TableHead class="text-right">Total</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="item in sale.items" :key="item.id">
                  <TableCell class="font-medium">{{ item.product_name }}</TableCell>
                  <TableCell class="font-mono text-sm">{{ item.product_sku }}</TableCell>
                  <TableCell class="text-right">{{ item.quantity }}</TableCell>
                  <TableCell class="text-right">{{ formatCurrency(item.unit_price) }}</TableCell>
                  <TableCell class="text-right">{{ formatCurrency(item.subtotal) }}</TableCell>
                  <TableCell class="text-right">{{ formatCurrency(item.tax_amount) }}</TableCell>
                  <TableCell class="text-right font-semibold">{{ formatCurrency(item.total) }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- Totals & Payment -->
        <div class="grid gap-6 md:grid-cols-2">
          <Card class="border-0 shadow-lg">
            <CardHeader>
              <CardTitle>Totals</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex justify-between text-lg">
                <span class="text-slate-600">Subtotal:</span>
                <span class="font-semibold">{{ formatCurrency(sale.subtotal) }}</span>
              </div>
              <div class="flex justify-between text-lg">
                <span class="text-slate-600">Tax (16%):</span>
                <span class="font-semibold">{{ formatCurrency(sale.tax_amount) }}</span>
              </div>
              <div v-if="sale.discount_amount > 0" class="flex justify-between text-lg text-red-600">
                <span>Discount:</span>
                <span class="font-semibold">-{{ formatCurrency(sale.discount_amount) }}</span>
              </div>
              <div class="border-t pt-3 flex justify-between text-xl font-bold">
                <span>Total:</span>
                <span class="text-emerald-600">{{ formatCurrency(sale.total) }}</span>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-lg">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <CreditCard class="h-5 w-5" />
                Payment Methods
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="payment in sale.payments"
                :key="payment.id"
                class="flex justify-between items-center p-3 bg-slate-50 rounded-lg"
              >
                <div>
                  <div class="font-semibold">{{ payment.payment_method }}</div>
                  <div v-if="payment.reference_number" class="text-sm text-slate-600">
                    Ref: {{ payment.reference_number }}
                  </div>
                </div>
                <div class="text-lg font-bold">{{ formatCurrency(payment.amount) }}</div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Notes -->
        <Card v-if="sale.notes" class="border-0 shadow-lg">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-slate-700">{{ sale.notes }}</p>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

