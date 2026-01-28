<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import {
  AlertCircle,
  Package,
  TrendingUp,
  TrendingDown,
  Search,
  Plus,
  Minus,
  History,
  Download,
  Filter,
  RefreshCw
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
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
  quantity: number
  reorder_level: number
  cost_price: number
  selling_price: number
  category: { name: string } | null
  is_low_stock: boolean
}

const props = defineProps<{
  products: {
    data: Product[]
    current_page: number
    last_page: number
    total: number
  }
  filters?: {
    search?: string
    low_stock?: string
  }
}>()

const search = ref(props.filters?.search || '')
const showLowStock = ref(props.filters?.low_stock === '1')
const showAdjustModal = ref(false)
const selectedProduct = ref<Product | null>(null)
const adjustmentType = ref<'IN' | 'OUT'>('IN')

// Adjustment form
const adjustForm = useForm({
  product_id: 0,
  adjustment_type: 'IN' as 'IN' | 'OUT' | 'ADJUSTMENT',
  quantity: 1,
  reason: '',
  notes: '',
})

// Computed stats from real data
const totalItems = computed(() => props.products.total)
const lowStockCount = computed(() => props.products.data.filter(p => p.is_low_stock).length)
const inStockCount = computed(() => props.products.data.filter(p => !p.is_low_stock).length)
const totalValue = computed(() => {
  return props.products.data.reduce((sum, p) => sum + (p.quantity * p.cost_price), 0)
})

// Apply filters
const applyFilters = () => {
  router.get('/inventory', {
    search: search.value,
    low_stock: showLowStock.value ? '1' : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Open adjustment modal
const openAdjustModal = (product: Product, type: 'IN' | 'OUT') => {
  selectedProduct.value = product
  adjustmentType.value = type
  adjustForm.product_id = product.id
  adjustForm.adjustment_type = type === 'IN' ? 'IN' : 'OUT'
  adjustForm.quantity = 1
  adjustForm.reason = type === 'IN' ? 'Stock in' : 'Stock out'
  adjustForm.notes = ''
  showAdjustModal.value = true
}

// Close modal
const closeAdjustModal = () => {
  showAdjustModal.value = false
  selectedProduct.value = null
  adjustForm.reset()
}

// Submit adjustment
const submitAdjustment = () => {
  adjustForm.post('/inventory/adjust', {
    preserveScroll: true,
    onSuccess: () => {
      closeAdjustModal()
      router.reload({ only: ['products'] })
    },
    onError: (errors) => {
      console.error('Adjustment failed:', errors)
      alert('Failed to adjust inventory. Please try again.')
    }
  })
}

// View transaction history
const viewHistory = (productId: number) => {
  router.visit(`/inventory/transactions?product_id=${productId}`)
}

// Export inventory
const exportInventory = () => {
  window.location.href = `/inventory/export?search=${search.value}&low_stock=${showLowStock.value ? '1' : ''}`
}
</script>

<template>
  <Head title="Inventory Management" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Package class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Inventory Management</h1>
                  <p class="text-emerald-100 text-lg mt-1">{{ totalItems }} items in stock</p>
                </div>
              </div>
            </div>
            <div class="flex gap-3">
              <Button @click="exportInventory" variant="outline" class="border-white text-white hover:bg-white/20 gap-2">
                <Download class="h-5 w-5" />
                Export
              </Button>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid gap-4 md:grid-cols-4">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <Package class="h-4 w-4" />
                Total Items
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-emerald-600">{{ totalItems }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <AlertCircle class="h-4 w-4 text-red-500" />
                Low Stock
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-red-600">{{ lowStockCount }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <TrendingUp class="h-4 w-4 text-green-500" />
                In Stock
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-teal-600">{{ inStockCount }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Total Value</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-cyan-600">{{ formatCurrency(totalValue) }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Filters -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-6">
            <div class="flex gap-4">
              <div class="flex-1">
                <div class="relative">
                  <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search products by name or SKU..."
                    class="pl-12 h-12 border-2"
                    @keyup.enter="applyFilters"
                  />
                </div>
              </div>
              <Button
                :variant="showLowStock ? 'default' : 'outline'"
                @click="showLowStock = !showLowStock; applyFilters()"
                class="h-12 px-6 gap-2"
                :class="showLowStock ? 'bg-gradient-to-r from-red-500 to-orange-500' : ''"
              >
                <AlertCircle class="h-5 w-5" />
                Low Stock Only
              </Button>
              <Button @click="applyFilters" class="h-12 px-6 gap-2 bg-gradient-to-r from-emerald-600 to-teal-600">
                <Filter class="h-5 w-5" />
                Apply Filters
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Inventory Table -->
        <Card class="border-0 shadow-2xl bg-white">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <CardTitle class="text-2xl">Stock Levels</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold">Product</TableHead>
                  <TableHead class="font-semibold">SKU</TableHead>
                  <TableHead class="font-semibold">Category</TableHead>
                  <TableHead class="font-semibold">Current Stock</TableHead>
                  <TableHead class="font-semibold">Reorder Level</TableHead>
                  <TableHead class="font-semibold">Value</TableHead>
                  <TableHead class="font-semibold">Status</TableHead>
                  <TableHead class="text-right font-semibold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="products.data.length === 0">
                  <TableCell colspan="8" class="text-center py-12">
                    <div class="flex flex-col items-center gap-3 text-slate-500">
                      <Package class="h-12 w-12 text-slate-300" />
                      <p class="text-lg font-medium">No products found</p>
                      <p class="text-sm">Try adjusting your filters</p>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow
                  v-for="product in products.data"
                  :key="product.id"
                  :class="product.is_low_stock ? 'bg-red-50/50' : 'hover:bg-emerald-50/50'"
                  class="transition-colors"
                >
                  <TableCell>
                    <div class="font-semibold text-slate-900">{{ product.name }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-mono text-sm bg-slate-100 px-2 py-1 rounded inline-block">
                      {{ product.sku }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">
                      {{ product.category?.name || 'Uncategorized' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <div
                        :class="product.is_low_stock ? 'text-red-600' : 'text-emerald-600'"
                        class="text-2xl font-bold"
                      >
                        {{ product.quantity }}
                      </div>
                      <component
                        :is="product.is_low_stock ? TrendingDown : TrendingUp"
                        :class="product.is_low_stock ? 'text-red-500' : 'text-emerald-500'"
                        class="h-5 w-5"
                      />
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-sm text-slate-600">{{ product.reorder_level }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="font-semibold">{{ formatCurrency(product.quantity * product.cost_price) }}</div>
                  </TableCell>
                  <TableCell>
                    <Badge
                      :class="product.is_low_stock
                        ? 'bg-red-100 text-red-800 animate-pulse'
                        : 'bg-emerald-100 text-emerald-800'"
                    >
                      {{ product.is_low_stock ? '⚠️ Low Stock' : '✓ In Stock' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-green-100"
                        @click="openAdjustModal(product, 'IN')"
                        title="Add Stock"
                      >
                        <Plus class="h-4 w-4 text-green-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-red-100"
                        @click="openAdjustModal(product, 'OUT')"
                        title="Remove Stock"
                      >
                        <Minus class="h-4 w-4 text-red-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-blue-100"
                        @click="viewHistory(product.id)"
                        title="View History"
                      >
                        <History class="h-4 w-4 text-blue-600" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- Pagination -->
        <div v-if="products.last_page > 1" class="flex justify-center gap-2 p-6">
          <Button
            v-for="page in products.last_page"
            :key="page"
            :variant="page === products.current_page ? 'default' : 'outline'"
            @click="router.get(`/inventory?page=${page}`)"
            :class="page === products.current_page ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : ''"
          >
            {{ page }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Adjustment Modal -->
    <Dialog :open="showAdjustModal" @update:open="closeAdjustModal">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle class="text-2xl flex items-center gap-2">
            <component :is="adjustmentType === 'IN' ? Plus : Minus" class="h-6 w-6" />
            {{ adjustmentType === 'IN' ? 'Add Stock' : 'Remove Stock' }}
          </DialogTitle>
          <DialogDescription>
            Adjust inventory for: <strong>{{ selectedProduct?.name }}</strong>
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label>Current Stock</Label>
            <div class="text-3xl font-bold text-emerald-600">
              {{ selectedProduct?.quantity }} units
            </div>
          </div>

          <div class="space-y-2">
            <Label for="quantity">Quantity to {{ adjustmentType === 'IN' ? 'Add' : 'Remove' }} *</Label>
            <Input
              id="quantity"
              v-model.number="adjustForm.quantity"
              type="number"
              min="1"
              required
              class="h-11"
              placeholder="Enter quantity"
            />
          </div>

          <div class="space-y-2">
            <Label for="reason">Reason *</Label>
            <Select v-model="adjustForm.reason">
              <SelectTrigger class="h-11">
                <SelectValue placeholder="Select reason..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Stock in">Stock in</SelectItem>
                <SelectItem value="Stock out">Stock out</SelectItem>
                <SelectItem value="Damaged">Damaged</SelectItem>
                <SelectItem value="Lost">Lost</SelectItem>
                <SelectItem value="Return">Return</SelectItem>
                <SelectItem value="Adjustment">Adjustment</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes (optional)</Label>
            <Textarea
              id="notes"
              v-model="adjustForm.notes"
              placeholder="Additional notes..."
              class="resize-none"
              rows="3"
            />
          </div>

          <div v-if="adjustForm.quantity > 0" class="p-4 bg-slate-50 rounded-lg">
            <div class="text-sm text-slate-600 mb-2">New Stock Level:</div>
            <div class="text-2xl font-bold" :class="adjustmentType === 'IN' ? 'text-green-600' : 'text-orange-600'">
              {{ adjustmentType === 'IN'
                ? (selectedProduct?.quantity || 0) + adjustForm.quantity
                : (selectedProduct?.quantity || 0) - adjustForm.quantity
              }} units
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="closeAdjustModal">
            Cancel
          </Button>
          <Button
            type="button"
            @click="submitAdjustment"
            :disabled="adjustForm.processing || !adjustForm.reason"
            :class="adjustmentType === 'IN' ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'"
          >
            <component :is="adjustmentType === 'IN' ? Plus : Minus" class="h-4 w-4 mr-2" />
            {{ adjustmentType === 'IN' ? 'Add Stock' : 'Remove Stock' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>

