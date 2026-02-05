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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <Package class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Inventory Management</h1>
                  <p class="text-emerald-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">{{ totalItems }} items in stock</p>
                </div>
              </div>
            </div>
            <div class="flex gap-2">
              <Button @click="exportInventory" variant="outline" class="border-white text-white hover:bg-white/20 gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm">
                <Download class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden sm:inline">Export</span>
              </Button>
            </div>
          </div>
        </div>

        <!-- Quick Stats - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-4 grid-cols-2 md:grid-cols-4">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <Package class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Items</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-emerald-600">{{ totalItems }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <AlertCircle class="h-3 w-3 sm:h-4 sm:w-4 text-red-500" />
                <span class="truncate">Low Stock</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-red-600">{{ lowStockCount }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4 text-green-500" />
                <span class="truncate">In Stock</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-teal-600">{{ inStockCount }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 truncate">Total Value</CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-cyan-600 truncate">{{ formatCurrency(totalValue) }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Filters - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-4 sm:pt-6 p-3 sm:p-6">
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
              <div class="flex-1">
                <div class="relative">
                  <Search class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-4 w-4 sm:h-5 sm:w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search products..."
                    class="pl-10 sm:pl-12 h-10 sm:h-12 border-2 text-sm sm:text-base"
                    @keyup.enter="applyFilters"
                  />
                </div>
              </div>
              <Button
                :variant="showLowStock ? 'default' : 'outline'"
                @click="showLowStock = !showLowStock; applyFilters()"
                class="h-10 sm:h-12 px-3 sm:px-6 gap-1 sm:gap-2 text-xs sm:text-sm"
                :class="showLowStock ? 'bg-gradient-to-r from-red-500 to-orange-500' : ''"
              >
                <AlertCircle class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">Low Stock Only</span>
                <span class="xs:hidden">Low Stock</span>
              </Button>
              <Button @click="applyFilters" class="h-10 sm:h-12 px-3 sm:px-6 gap-1 sm:gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 text-xs sm:text-sm">
                <Filter class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden sm:inline">Apply Filters</span>
                <span class="sm:hidden">Apply</span>
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Inventory Table - Mobile Optimized -->
        <Card class="border-0 shadow-2xl bg-white">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100 p-3 sm:p-6">
            <CardTitle class="text-base sm:text-xl lg:text-2xl truncate">Stock Levels</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <div class="overflow-x-auto">
            <Table class="min-w-full">
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Product</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden md:table-cell">SKU</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden lg:table-cell">Category</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Stock</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden sm:table-cell">Reorder</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden md:table-cell">Value</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden lg:table-cell">Status</TableHead>
                  <TableHead class="text-right font-semibold text-xs sm:text-sm px-2 sm:px-4">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="products.data.length === 0">
                  <TableCell colspan="8" class="text-center py-8 sm:py-12 px-3">
                    <div class="flex flex-col items-center gap-2 sm:gap-3 text-slate-500">
                      <Package class="h-8 w-8 sm:h-12 sm:w-12 text-slate-300" />
                      <p class="text-base sm:text-lg font-medium">No products found</p>
                      <p class="text-xs sm:text-sm">Try adjusting your filters</p>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow
                  v-for="product in products.data"
                  :key="product.id"
                  :class="product.is_low_stock ? 'bg-red-50/50' : 'hover:bg-emerald-50/50'"
                  class="transition-colors"
                >
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="font-semibold text-slate-900 text-xs sm:text-sm truncate max-w-[120px] sm:max-w-none">{{ product.name }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                    <div class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded inline-block">
                      {{ product.sku }}
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                    <Badge variant="outline" class="text-xs">
                      {{ product.category?.name || 'Uncategorized' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <div
                        :class="product.is_low_stock ? 'text-red-600' : 'text-emerald-600'"
                        class="text-lg sm:text-xl lg:text-2xl font-bold"
                      >
                        {{ product.quantity }}
                      </div>
                      <component
                        :is="product.is_low_stock ? TrendingDown : TrendingUp"
                        :class="product.is_low_stock ? 'text-red-500' : 'text-emerald-500'"
                        class="h-4 w-4 sm:h-5 sm:w-5"
                      />
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">
                    <div class="text-xs sm:text-sm text-slate-600">{{ product.reorder_level }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                    <div class="font-semibold text-sm">{{ formatCurrency(product.quantity * product.cost_price) }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                    <Badge
                      :class="product.is_low_stock
                        ? 'bg-red-100 text-red-800 animate-pulse'
                        : 'bg-emerald-100 text-emerald-800'"
                      class="text-xs"
                    >
                      {{ product.is_low_stock ? '⚠️ Low Stock' : '✓ In Stock' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right px-2 sm:px-4 py-2 sm:py-3">
                    <div class="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-green-100 h-8 w-8 p-0"
                        @click="openAdjustModal(product, 'IN')"
                        title="Add Stock"
                      >
                        <Plus class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-green-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-red-100 h-8 w-8 p-0"
                        @click="openAdjustModal(product, 'OUT')"
                        title="Remove Stock"
                      >
                        <Minus class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-red-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-blue-100 h-8 w-8 p-0 hidden sm:inline-flex"
                        @click="viewHistory(product.id)"
                        title="View History"
                      >
                        <History class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
            </div>
          </CardContent>
        </Card>

        <!-- Pagination - Mobile Optimized -->
        <div v-if="products.last_page > 1" class="flex justify-center gap-2 p-3 sm:p-6">
          <Button
            v-for="page in products.last_page"
            :key="page"
            :variant="page === products.current_page ? 'default' : 'outline'"
            @click="router.get(`/inventory?page=${page}`)"
            :class="page === products.current_page ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : ''"
            class="h-8 w-8 sm:h-10 sm:w-10 p-0 text-xs sm:text-sm"
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

