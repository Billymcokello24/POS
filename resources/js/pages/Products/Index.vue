<script setup lang="ts">
/* eslint-disable */
import { Plus, Package, AlertTriangle, Edit, Trash2, Search, Filter, X, DollarSign, Barcode, Sparkles, ArrowUp, Download } from 'lucide-vue-next'
import { ref, computed, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

// UI components (internal)
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

import AppLayout from '@/layouts/AppLayout.vue'

// Get currency from page props (use any to avoid TS prop inference errors)
const page = usePage<any>()

const currency = computed(() => {
  const curr = page.props.currency
  return typeof curr === 'function' ? curr() : curr || 'USD'
})

// Currency formatting function
const formatCurrency = (amount: number | string): string => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'INR': '₹', 'KES': 'KSh', 'TZS': 'TSh', 'UGX': 'USh', 'ZAR': 'R', 'NGN': '₦',
  }

  const symbol = symbols[currencyCode] || currencyCode + ' '
  return `${symbol}${num.toFixed(2)}`
}

// Get currency symbol
const getCurrencySymbol = (): string => {
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'INR': '₹', 'KES': 'KSh', 'TZS': 'TSh', 'UGX': 'USh', 'ZAR': 'R', 'NGN': '₦',
  }

  return symbols[currencyCode] || currencyCode
}

interface Product {
  id: number
  name: string
  sku: string
  barcode: string
  description?: string
  category_id?: number
  category: { id: number, name: string } | null
  selling_price: number
  cost_price: number
  quantity: number
  reorder_level: number
  unit?: string
  is_low_stock: boolean
  is_active: boolean
  track_inventory?: boolean
}

interface Category {
  id: number
  name: string
}

const props = defineProps<{
  products?: {
    data: Product[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  categories?: Category[]
  filters?: {
    search?: string
    category_id?: number
    low_stock?: boolean
  }
}>()

// Mock data for when backend doesn't return data
const mockProducts = {
  data: [
    {
      id: 1,
      name: 'Laptop HP ProBook 450 G8',
      sku: 'ELEC-001',
      barcode: '1234567890123',
      description: 'HP ProBook 450 G8 - Intel Core i5',
      category_id: 1,
      category: { id: 1, name: 'Electronics' },
      selling_price: 750.00,
      cost_price: 600.00,
      quantity: 10,
      reorder_level: 5,
      unit: 'pcs',
      is_low_stock: false,
      is_active: true,
      track_inventory: true,
    },
    {
      id: 2,
      name: 'Wireless Mouse Logitech',
      sku: 'ELEC-002',
      barcode: '1234567890124',
      description: 'Logitech Wireless Mouse',
      category_id: 1,
      category: { id: 1, name: 'Electronics' },
      selling_price: 20.00,
      cost_price: 15.00,
      quantity: 8,
      reorder_level: 10,
      unit: 'pcs',
      is_low_stock: true,
      is_active: true,
      track_inventory: true,
    },
    {
      id: 3,
      name: 'T-Shirt Blue Cotton',
      sku: 'CLOTH-001',
      barcode: '1234567890125',
      description: '100% Cotton Blue T-Shirt',
      category_id: 2,
      category: { id: 2, name: 'Clothing' },
      selling_price: 15.00,
      cost_price: 10.00,
      quantity: 15,
      reorder_level: 20,
      unit: 'pcs',
      is_low_stock: true,
      is_active: true,
      track_inventory: true,
    },
    {
      id: 4,
      name: 'Jeans Black Denim',
      sku: 'CLOTH-002',
      barcode: '1234567890126',
      description: 'Black Denim Jeans',
      category_id: 2,
      category: { id: 2, name: 'Clothing' },
      selling_price: 45.00,
      cost_price: 35.00,
      quantity: 30,
      reorder_level: 10,
      unit: 'pcs',
      is_low_stock: false,
      is_active: true,
      track_inventory: true,
    },
  ] as Product[],
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 4,
}

const mockCategories = [
  { id: 1, name: 'Electronics' },
  { id: 2, name: 'Clothing' },
  { id: 3, name: 'Food & Beverage' },
]

// Use props data if available, otherwise use mock data
const productsData = ref(props.products || mockProducts)

// Ensure all prices are numbers (backend sends them as strings)
if (productsData.value?.data) {
  productsData.value.data = productsData.value.data.map(product => ({
    ...product,
    selling_price: Number(product.selling_price),
    cost_price: Number(product.cost_price),
    quantity: Number(product.quantity),
    reorder_level: Number(product.reorder_level),
  }))
}

const categoriesData = ref(props.categories || mockCategories)

// Multi-select state for bulk actions
const selectedIds = ref<number[]>([])

const isSelected = (id: number) => selectedIds.value.includes(id)

const toggleSelect = (id: number) => {
  if (isSelected(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

// Computed flag - true when all visible products are selected, with a setter so v-model works
const allSelected = computed<boolean>({
  get() {
    return !!(productsData.value && productsData.value.data && productsData.value.data.length > 0 && selectedIds.value.length === productsData.value.data.length)
  },
  set(value: boolean) {
    if (!productsData.value?.data) return
    if (value) {
      // select all
      selectedIds.value = productsData.value.data.map(p => p.id)
    } else {
      // clear selection
      selectedIds.value = []
    }
  }
})

const selectedCount = computed(() => selectedIds.value.length)

// Bulk delete - uses the existing delete endpoint per product to avoid adding new backend routes
const bulkDelete = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Delete ${selectedIds.value.length} selected product(s)? This cannot be undone.`)) return

  try {
    // delete in parallel
    await Promise.all(selectedIds.value.map(id =>
      router.delete(`/products/${id}`, { preserveState: true })
    ))
    // clear selection and reload
    selectedIds.value = []
    router.reload()
  } catch (e) {
    console.error('Bulk delete failed', e)
    alert('Failed to delete selected products. Check console for details.')
  }
}

// reset selection when products data changes (e.g., after filtering/pagination)
watch(() => productsData.value && productsData.value.data.map(p => p.id).join(','), () => {
  selectedIds.value = []
})

const search = ref(props.filters?.search || '')
const showLowStock = ref(props.filters?.low_stock || false)
const showModal = ref(false)
const editingProduct = ref<Product | null>(null)

// New reactive state for import
const showImportModal = ref(false)
// import form now includes business_ids so backend knows which businesses to create products for
const importForm = useForm<{ file: File | null; business_ids: number[] }>({ file: null, business_ids: [] })
const importErrors = ref<string[]>([])
const importResult = ref<string | null>(null)
// selected businesses for import (defaults to current business)
const selectedBusinessIds = ref<number[]>([])

// populate default selected businesses from page props when available
if (Array.isArray(page.props.businesses) && page.props.businesses.length > 0) {
  // default to current business if available, otherwise select first
  const current = page.props.auth?.user?.current_business_id
  if (typeof current === 'number') {
    selectedBusinessIds.value = [current]
  } else {
    selectedBusinessIds.value = [page.props.businesses[0].id]
  }
  // also set importForm.business_ids so upload uses it
  importForm.business_ids = selectedBusinessIds.value
}

const form = useForm({
  name: '',
  description: '',
  sku: '',
  barcode: '',
  category_id: null as number | null,
  cost_price: 0,
  selling_price: 0,
  quantity: 0,
  reorder_level: 10,
  unit: 'pcs',
  track_inventory: true,
  is_active: true,
})

// VAT and barcode generation
const VAT_RATE = 0.16 // 16% VAT

const generateBarcode = () => {
  // Generate 13-digit EAN barcode
  const timestamp = Date.now().toString()
  const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0')
  const barcode = (timestamp.slice(-9) + random + '0').slice(0, 13)
  return barcode
}

const calculateVATAmount = (basePrice: number) => {
  return basePrice * VAT_RATE
}

const calculateBasePrice = (priceWithVAT: number) => {
  return priceWithVAT / (1 + VAT_RATE)
}

// Watch for price changes to auto-generate barcode (REMOVED)
const onSellingPriceChange = () => {
  // Manual entry only
}

const applyFilters = () => {
  router.get('/products', {
    search: search.value,
    low_stock: showLowStock.value ? '1' : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const openCreateModal = () => {
  editingProduct.value = null
  form.reset()
  form.quantity = 0
  form.reorder_level = 10
  form.unit = 'pcs'
  form.track_inventory = true
  form.is_active = true
  // Auto-generate SKU
  const timestamp = Date.now().toString().slice(-6)
  form.sku = `PRD-${timestamp}`
  showModal.value = true
}

const openEditModal = (product: Product) => {
  editingProduct.value = product
  form.name = product.name
  form.description = product.description || ''
  form.sku = product.sku
  form.barcode = product.barcode
  form.category_id = product.category?.id || null
  form.cost_price = Number(product.cost_price)
  form.selling_price = Number(product.selling_price)
  form.quantity = Number(product.quantity)
  form.reorder_level = Number(product.reorder_level)
  form.unit = product.unit || 'pcs'
  form.track_inventory = product.track_inventory ?? true
  form.is_active = product.is_active
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  form.reset()
  editingProduct.value = null
}

const submitForm = () => {
  if (editingProduct.value) {
    form.put(`/products/${editingProduct.value.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        closeModal()
        // Reload Inertia props so updated products and global values refresh
        router.reload()
      },
      onError: (errors) => {
        console.error('Update failed:', errors)
        alert('Failed to update product. Please check the form.')
      }
    })
  } else {
    form.post('/products', {
      preserveScroll: true,
      onSuccess: () => {
        closeModal()
        // Reload Inertia props to show newly created product
        router.reload()
      },
      onError: (errors) => {
        console.error('Create failed:', errors)
        alert('Failed to create product. Please check the form.')
      }
    })
  }
}

const deleteProduct = (product: Product) => {
  if (confirm(`Delete ${product.name}?`)) {
    router.delete(`/products/${product.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        // Refresh products list after deletion
        router.reload()
      },
      onError: (errors) => {
        console.error('Delete failed:', errors)
        alert('Failed to delete product')
      }
    })
  }
}

// New method for file input change
const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    importForm.file = target.files[0]
  }
}

const submitImport = () => {
  importErrors.value = []
  importResult.value = null
  if (!importForm.file) {
    alert('Please select a file to import')
    return
  }

  // ensure form has the currently selected business IDs
  importForm.business_ids = selectedBusinessIds.value
  // The inertia useForm supports file uploads automatically when the file is set on the form state.
  importForm.post('/products/import', {
    preserveState: true,
    onStart: () => {
      importResult.value = 'Uploading...'
    },
    onSuccess: () => {
      // success handled via flash from server; close modal and reload
      showImportModal.value = false
      importResult.value = null
      router.reload()
    },
    onError: (errors) => {
      console.error('Import failed:', errors)
      importResult.value = null
      // If server returns validation errors for file, show them
      if (errors && errors.file) {
        importErrors.value = Array.isArray(errors.file) ? errors.file : [errors.file]
      }
    }
  })
}

// Access flash from page props (typed any to avoid TS property errors)
const flash: any = page.props.flash || {}
</script>

<template>
  <Head title="Products" />

  <AppLayout title="Products">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <Package class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Product Catalog</h1>
                  <p class="text-blue-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">{{ productsData.total }} items in inventory</p>
                </div>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
              <Button
                v-if="(page.props.auth as any).permissions?.includes('create_products')"
                @click="openCreateModal"
                class="bg-white text-blue-600 hover:bg-blue-50 gap-1 sm:gap-2 h-9 sm:h-12 px-3 sm:px-6 text-xs sm:text-sm flex-1 sm:flex-initial"
              >
                <Plus class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">Add Product</span>
                <span class="xs:hidden">Add</span>
              </Button>
              <Button
                @click="showImportModal = true"
                class="bg-white text-blue-600 hover:bg-blue-50 gap-1 sm:gap-2 h-9 sm:h-12 px-3 sm:px-6 text-xs sm:text-sm flex-1 sm:flex-initial"
              >
                <ArrowUp class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden sm:inline">Import</span>
              </Button>
              <a
                href="/products/import/template"
                download
                data-inertia="false"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 sm:gap-2 px-3 sm:px-4 h-9 sm:h-12 rounded bg-white text-green-600 hover:bg-green-50 text-xs sm:text-sm flex-1 sm:flex-initial justify-center"
              >
                <Download class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden md:inline">Download Template</span>
                <span class="md:hidden">Template</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Flash Messages -->
        <div v-if="flash.success" class="p-4 rounded-lg bg-green-50 border border-green-200 text-green-800">
          {{ flash.success }}
        </div>
        <div v-if="flash.import_errors && flash.import_errors.length" class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
          <div class="font-semibold mb-2">Import Errors:</div>
          <ul class="list-disc list-inside">
            <li v-for="(error, index) in flash.import_errors" :key="index">{{ error }}</li>
          </ul>
        </div>
        <div v-if="flash.import_warnings && flash.import_warnings.length" class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800">
          <div class="font-semibold mb-2">Import Warnings:</div>
          <ul class="list-disc list-inside">
            <li v-for="(warning, index) in flash.import_warnings" :key="index">{{ warning }}</li>
          </ul>
        </div>

        <!-- Filters - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardContent class="pt-4 sm:pt-6 p-3 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-4">
              <div class="flex-1 min-w-0 sm:min-w-[300px]">
                <div class="relative">
                  <Search class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-4 w-4 sm:h-5 sm:w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search products..."
                    @keyup.enter="applyFilters"
                    class="pl-10 sm:pl-12 h-10 sm:h-12 border-2 focus:border-blue-500 text-sm sm:text-base"
                  />
                </div>
              </div>
              <Button
                :variant="showLowStock ? 'default' : 'outline'"
                @click="showLowStock = !showLowStock; applyFilters()"
                class="h-10 sm:h-12 px-3 sm:px-6 gap-1 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-initial"
                :class="showLowStock ? 'bg-gradient-to-r from-red-500 to-orange-500' : ''"
              >
                <AlertTriangle class="h-4 w-4 sm:h-5 sm:w-5" />
                <span class="hidden xs:inline">Low Stock Only</span>
                <span class="xs:hidden">Low Stock</span>
              </Button>
              <Button @click="applyFilters" class="h-10 sm:h-12 px-3 sm:px-6 gap-1 sm:gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-xs sm:text-sm">
                <Filter class="h-4 w-4 sm:h-5 sm:w-5" />
                Apply
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Products Grid/Table - Mobile Optimized -->
        <Card class="border-0 shadow-2xl bg-white/90 backdrop-blur">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100 p-3 sm:p-6">
            <div class="flex items-center justify-between gap-2">
              <CardTitle class="text-base sm:text-xl lg:text-2xl flex items-center gap-2 min-w-0 flex-1">
                <Sparkles class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 text-blue-600 flex-shrink-0" />
                <span class="truncate">Products</span>
              </CardTitle>
              <div class="text-xs sm:text-sm text-slate-600 flex-shrink-0">
                {{ productsData.data.length }} / {{ productsData.total }}
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-0">
            <div class="overflow-x-auto">
            <Table class="min-w-full">
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold w-4 px-2 sm:px-4">
                    <!-- Select All Checkbox -->
                    <div class="flex items-center">
                      <input
                        type="checkbox"
                        v-model="allSelected"
                        class="h-4 w-4 sm:h-5 sm:w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                    </div>
                  </TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Product</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden md:table-cell">SKU / Barcode</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden lg:table-cell">Category</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4">Price</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden sm:table-cell">Stock</TableHead>
                  <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 hidden lg:table-cell">Status</TableHead>
                  <TableHead class="text-right font-semibold text-xs sm:text-sm px-2 sm:px-4">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="product in productsData.data"
                  :key="product.id"
                  class="hover:bg-blue-50/50 transition-colors"
                >
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <!-- Individual Select Checkbox -->
                    <div class="flex items-center">
                      <input
                        type="checkbox"
                        :value="product.id"
                        :checked="isSelected(product.id)"
                        @change="toggleSelect(product.id)"
                        class="h-4 w-4 sm:h-5 sm:w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="font-semibold text-slate-900 text-xs sm:text-sm truncate max-w-[120px] sm:max-w-none">{{ product.name }}</div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                    <div class="space-y-1">
                      <div class="text-xs font-mono bg-slate-100 px-2 py-0.5 rounded inline-block">{{ product.sku }}</div>
                      <div class="text-[10px] text-slate-500">{{ product.barcode }}</div>
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                    <Badge variant="outline" class="bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200 text-xs">
                      {{ product.category?.name || 'Uncategorized' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                    <div class="space-y-0.5">
                      <div class="text-sm sm:text-base lg:text-lg font-bold text-slate-900">{{ formatCurrency(product.selling_price) }}</div>
                      <div class="text-[10px] sm:text-xs text-slate-500 hidden sm:block">Cost: {{ formatCurrency(product.cost_price) }}</div>
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">
                    <div class="flex items-center gap-2">
                      <div
                        :class="product.is_low_stock ? 'text-red-600 font-bold text-base sm:text-lg' : 'text-slate-900 font-semibold'"
                        class="flex items-center gap-1"
                      >
                        {{ product.quantity }}
                        <AlertTriangle
                          v-if="product.is_low_stock"
                          class="h-4 w-4 sm:h-5 sm:w-5 animate-pulse"
                        />
                      </div>
                    </div>
                    <div class="text-[10px] sm:text-xs text-slate-500 mt-1">
                      Min: {{ product.reorder_level }}
                    </div>
                  </TableCell>
                  <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                    <Badge
                      :class="product.is_active
                        ? 'bg-gradient-to-r from-emerald-500 to-green-500 text-white'
                        : 'bg-slate-300 text-slate-700'"
                      class="text-xs"
                    >
                      {{ product.is_active ? '✓ Active' : '✕ Inactive' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right px-2 sm:px-4 py-2 sm:py-3">
                    <div class="flex justify-end gap-1 sm:gap-2">
                       <Button
                        v-if="(page.props.auth as any).permissions?.includes('edit_products')"
                        variant="ghost"
                        size="sm"
                        @click="openEditModal(product)"
                        class="hover:bg-blue-100 hover:text-blue-600 h-8 w-8 p-0"
                      >
                        <Edit class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                      </Button>
                      <Button
                        v-if="(page.props.auth as any).permissions?.includes('delete_products')"
                        variant="ghost"
                        size="sm"
                        @click="deleteProduct(product)"
                        class="hover:bg-red-100 hover:text-red-600 h-8 w-8 p-0"
                      >
                        <Trash2 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
            </div>


            <!-- Pagination -->
            <div v-if="productsData.last_page > 1" class="flex justify-center gap-2 p-6 bg-slate-50/50 border-t">
              <Button
                v-for="page in productsData.last_page"
                :key="page"
                :variant="page === productsData.current_page ? 'default' : 'outline'"
                size="sm"
                @click="router.get(`/products?page=${page}`)"
                :class="page === productsData.current_page ? 'bg-gradient-to-r from-blue-600 to-indigo-600' : ''"
              >
                {{ page }}
              </Button>
            </div>

            <!-- Bulk Actions -->
            <div v-if="selectedCount > 0" class="p-4 border-t bg-slate-50">
              <div class="flex items-center justify-between">
                <div class="text-sm text-slate-600">
                  Selected <span class="font-semibold text-slate-900">{{ selectedCount }}</span> item(s)
                </div>
                <div class="flex gap-2">
                  <Button
                    v-if="(page.props.auth as any).permissions?.includes('delete_products')"
                    @click="bulkDelete"
                    class="h-12 px-6 bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700"
                  >
                    <Trash2 class="h-5 w-5 mr-2" />
                    Delete Selected
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Add/Edit Product Modal -->
        <Dialog v-model:open="showModal">
          <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle class="text-2xl flex items-center gap-2">
                <Package class="h-6 w-6 text-blue-600" />
                {{ editingProduct ? 'Edit Product' : 'Add New Product' }}
              </DialogTitle>
              <DialogDescription>
                {{ editingProduct ? 'Update the product details below' : 'Add a new product to your inventory' }}
              </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-6 py-4">
              <!-- Product Name & Description -->
              <div class="space-y-4">
                <div class="space-y-2">
                  <Label for="product-name" class="text-base font-semibold flex items-center gap-2">
                    <Package class="h-4 w-4" />
                    Product Name *
                  </Label>
                  <Input
                    id="product-name"
                    v-model="form.name"
                    placeholder="e.g., Laptop HP ProBook"
                    required
                    class="h-12"
                    :class="form.errors.name ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                  <Label for="description" class="text-base">Description</Label>
                  <Textarea
                    id="description"
                    v-model="form.description"
                    placeholder="Optional product description"
                    rows="2"
                    class="resize-none"
                  />
                </div>
              </div>

              <!-- SKU, Barcode & Category -->
              <div class="grid grid-cols-3 gap-4">
                <div class="space-y-2">
                  <Label for="sku" class="text-base flex items-center gap-2">
                    <Package class="h-4 w-4" />
                    SKU (Auto-generated)
                  </Label>
                  <Input
                    id="sku"
                    v-model="form.sku"
                    placeholder="PRD-XXXXXX"
                    class="h-11 font-mono"
                    readonly
                  />
                  <p class="text-xs text-slate-500">✨ Auto-generated on create</p>
                </div>

                <div class="space-y-2">
                  <Label for="barcode" class="text-base flex items-center gap-2">
                    <Barcode class="h-4 w-4" />
                    Barcode
                  </Label>
                  <div class="flex gap-2">
                    <Input
                      id="barcode"
                      v-model="form.barcode"
                      placeholder="Scan or enter manually"
                      class="h-11 font-mono"
                    />
                    <Button
                      type="button"
                      variant="outline"
                      @click="form.barcode = generateBarcode()"
                      class="h-11 px-3"
                      title="Generate random barcode"
                    >
                      🔄
                    </Button>
                  </div>
                  <p class="text-xs text-slate-500">Enter manually, scan, or click refresh to generate</p>
                </div>

                <div class="space-y-2">
                  <Label for="category" class="text-base">Category</Label>
                  <Select v-model="form.category_id">
                    <SelectTrigger class="h-11">
                      <SelectValue placeholder="Select..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem :value="null">Uncategorized</SelectItem>
                      <SelectItem
                        v-for="category in categoriesData"
                        :key="category.id"
                        :value="category.id"
                      >
                        {{ category.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- Pricing -->
              <div class="space-y-3 p-4 bg-green-50 rounded-lg border-2 border-green-200">
                <h3 class="font-semibold text-green-900 flex items-center gap-2">
                  <DollarSign class="h-5 w-5" />
                  Pricing (Prices include 16% VAT)
                </h3>
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <Label for="cost-price" class="text-base">Cost Price (incl. VAT) *</Label>
                    <div class="relative">
                      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">{{ getCurrencySymbol() }}</span>
                      <Input
                        id="cost-price"
                        v-model.number="form.cost_price"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="h-11 pl-8"
                        placeholder="0.00"
                      />
                    </div>
                    <div v-if="form.cost_price > 0" class="text-xs space-y-1 bg-white/50 p-2 rounded">
                      <div class="flex justify-between">
                        <span class="text-slate-600">Base Price:</span>
                        <span class="font-semibold">{{ formatCurrency(calculateBasePrice(form.cost_price)) }}</span>
                      </div>
                      <div class="flex justify-between">
                        <span class="text-slate-600">VAT (16%):</span>
                        <span class="font-semibold text-green-600">{{ formatCurrency(calculateVATAmount(calculateBasePrice(form.cost_price))) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <Label for="selling-price" class="text-base">Selling Price (incl. VAT) *</Label>
                    <div class="relative">
                      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">{{ getCurrencySymbol() }}</span>
                      <Input
                        id="selling-price"
                        v-model.number="form.selling_price"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="h-11 pl-8"
                        placeholder="0.00"
                        @input="onSellingPriceChange"
                      />
                    </div>
                    <div v-if="form.selling_price > 0" class="text-xs space-y-1 bg-white/50 p-2 rounded">
                      <div class="flex justify-between">
                        <span class="text-slate-600">Base Price:</span>
                        <span class="font-semibold">{{ formatCurrency(calculateBasePrice(form.selling_price)) }}</span>
                      </div>
                      <div class="flex justify-between">
                        <span class="text-slate-600">VAT (16%):</span>
                        <span class="font-semibold text-green-600">{{ formatCurrency(calculateVATAmount(calculateBasePrice(form.selling_price))) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-if="form.selling_price > 0 && form.cost_price > 0" class="mt-3 p-3 bg-gradient-to-r from-green-100 to-emerald-100 rounded-lg border border-green-300">
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <div class="text-slate-600 text-xs mb-1">Gross Profit:</div>
                      <div class="text-xl font-bold text-green-700">
                        {{ formatCurrency(form.selling_price - form.cost_price) }}
                      </div>
                    </div>
                    <div>
                      <div class="text-slate-600 text-xs mb-1">Profit Margin:</div>
                      <div class="text-xl font-bold text-green-700">
                        {{ form.cost_price > 0 ? (((form.selling_price - form.cost_price) / form.cost_price) * 100).toFixed(1) : '—' }}%
                      </div>
                    </div>
                  </div>
                  <div class="mt-2 pt-2 border-t border-green-300 text-xs text-green-800">
                    💡 Net Profit (excl. VAT): {{ form.cost_price > 0 ? formatCurrency(calculateBasePrice(form.selling_price) - calculateBasePrice(form.cost_price)) : formatCurrency(0) }}
                  </div>
                </div>
              </div>

              <!-- Inventory -->
              <div class="space-y-3 p-4 bg-blue-50 rounded-lg border-2 border-blue-200">
                <h3 class="font-semibold text-blue-900 flex items-center gap-2">
                  <Package class="h-5 w-5" />
                  Inventory
                </h3>
                <div class="grid grid-cols-3 gap-4">
                  <div class="space-y-2">
                    <Label for="quantity" class="text-base">Quantity *</Label>
                    <Input
                      id="quantity"
                      v-model.number="form.quantity"
                      type="number"
                      min="0"
                      required
                      class="h-11"
                      placeholder="0"
                    />
                  </div>

                  <div class="space-y-2">
                    <Label for="reorder-level" class="text-base">Reorder Level</Label>
                    <Input
                      id="reorder-level"
                      v-model.number="form.reorder_level"
                      type="number"
                      min="0"
                      class="h-11"
                      placeholder="10"
                    />
                  </div>

                  <div class="space-y-2">
                    <Label for="unit" class="text-base">Unit</Label>
                    <Select v-model="form.unit">
                      <SelectTrigger class="h-11">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="pcs">Pieces</SelectItem>
                        <SelectItem value="kg">Kilograms</SelectItem>
                        <SelectItem value="liter">Liters</SelectItem>
                        <SelectItem value="box">Boxes</SelectItem>
                        <SelectItem value="pairs">Pairs</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <!-- Settings -->
              <div class="space-y-3">
                <div class="flex items-center justify-between rounded-lg border-2 border-purple-100 bg-purple-50/50 p-4">
                  <div>
                    <Label class="text-base font-semibold">Track Inventory</Label>
                    <p class="text-sm text-slate-600">Automatically update stock on sales</p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="form.track_inventory"
                    class="h-5 w-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                  />
                </div>

                <div class="flex items-center justify-between rounded-lg border-2 border-green-100 bg-green-50/50 p-4">
                  <div>
                    <Label class="text-base font-semibold">Active Status</Label>
                    <p class="text-sm text-slate-600">Product is available for sale</p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="form.is_active"
                    class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                  />
                </div>
              </div>

              <DialogFooter class="gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  @click="closeModal"
                  class="h-12 px-6"
                >
                  <X class="h-4 w-4 mr-2" />
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                >
                  <Plus v-if="!editingProduct" class="h-4 w-4 mr-2" />
                  <Edit v-else class="h-4 w-4 mr-2" />
                  {{ form.processing ? 'Saving...' : (editingProduct ? 'Update Product' : 'Create Product') }}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <!-- Import Products Modal -->
        <Dialog v-model:open="showImportModal">
          <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle class="text-xl font-semibold">
                <Package class="h-6 w-6 text-blue-600" />
                Import Products
              </DialogTitle>
              <DialogDescription class="text-sm text-slate-500">
                Upload a CSV or XLSX file to import products. Ensure the file is formatted correctly.
              </DialogDescription>
            </DialogHeader>

            <div class="p-4">
              <form @submit.prevent="submitImport" class="space-y-4">
                <!-- Business selector -->
                <div v-if="page.props.businesses && page.props.businesses.length" class="mb-3">
                  <Label class="block text-base font-semibold mb-2">Import into Businesses</Label>
                  <div class="space-y-2">
                    <label v-for="b in page.props.businesses" :key="b.id" class="flex items-center gap-2">
                      <input type="checkbox" class="h-4 w-4" :value="b.id" v-model="selectedBusinessIds" />
                      <span class="text-sm">{{ b.name }}</span>
                    </label>
                  </div>
                </div>

                <div>
                  <Label for="file-upload" class="block text-base font-semibold mb-2">
                    Select File *
                  </Label>
                  <!-- Use native input for file uploads to avoid wrapper limitations -->
                  <input
                    id="file-upload"
                    type="file"
                    accept=".csv, .xlsx, .xls"
                    @change="onFileChange"
                    class="h-12 w-full rounded border px-3"
                  />
                  <p v-if="importForm.errors.file" class="text-sm text-red-600 mt-1">{{ importForm.errors.file }}</p>
                </div>

                <div class="flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    @click="showImportModal = false"
                    class="h-12 px-6"
                  >
                    <X class="h-4 w-4 mr-2" />
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                  >
                    <Plus class="h-4 w-4 mr-2" />
                    Import
                  </Button>
                </div>
              </form>

              <!-- Import Results -->
              <div v-if="importResult" class="mt-4 p-3 bg-green-50 rounded-lg border-2 border-green-200 text-sm text-green-800">
                {{ importResult }}
              </div>
              <div v-if="importErrors.length > 0" class="mt-4 p-3 bg-red-50 rounded-lg border-2 border-red-200 text-sm text-red-800">
                <div class="font-semibold mb-2">Errors:</div>
                <ul class="list-disc list-inside">
                  <li v-for="(error, index) in importErrors" :key="index">{{ error }}</li>
                </ul>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </div>
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

.backdrop-blur {
  backdrop-filter: blur(10px);
}
</style>

