<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Package, AlertTriangle, Edit, Trash2, Search, Filter, X, DollarSign, Barcode, Sparkles } from 'lucide-vue-next'

// Get currency from page props
const page = usePage()
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

const search = ref(props.filters?.search || '')
const showLowStock = ref(props.filters?.low_stock || false)
const showModal = ref(false)
const editingProduct = ref<Product | null>(null)

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

const calculatePriceWithVAT = (basePrice: number) => {
  return basePrice * (1 + VAT_RATE)
}

const calculateVATAmount = (basePrice: number) => {
  return basePrice * VAT_RATE
}

const calculateBasePrice = (priceWithVAT: number) => {
  return priceWithVAT / (1 + VAT_RATE)
}

// Watch for price changes to auto-generate barcode
const onSellingPriceChange = () => {
  if (form.selling_price > 0 && !form.barcode && !editingProduct.value) {
    form.barcode = generateBarcode()
  }
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
        // Force reload to show updated data
        router.reload({ only: ['products'] })
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
        // Force reload to show new product
        router.reload({ only: ['products'] })
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
    })
  }
}
</script>

<template>
  <Head title="Products" />

  <AppLayout title="Products">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Package class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Product Catalog</h1>
                  <p class="text-blue-100 text-lg mt-1">{{ productsData.total }} items in inventory</p>
                </div>
              </div>
            </div>
            <Button @click="openCreateModal" class="bg-white text-blue-600 hover:bg-blue-50 gap-2 h-12 px-6">
              <Plus class="h-5 w-5" />
              Add Product
            </Button>
          </div>
        </div>

        <!-- Filters -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardContent class="pt-6">
            <div class="flex flex-wrap gap-4">
              <div class="flex-1 min-w-[300px]">
                <div class="relative">
                  <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search by name, SKU, or barcode..."
                    @keyup.enter="applyFilters"
                    class="pl-12 h-12 border-2 focus:border-blue-500"
                  />
                </div>
              </div>
              <Button
                :variant="showLowStock ? 'default' : 'outline'"
                @click="showLowStock = !showLowStock; applyFilters()"
                class="h-12 px-6 gap-2"
                :class="showLowStock ? 'bg-gradient-to-r from-red-500 to-orange-500' : ''"
              >
                <AlertTriangle class="h-5 w-5" />
                Low Stock Only
              </Button>
              <Button @click="applyFilters" class="h-12 px-6 gap-2 bg-gradient-to-r from-blue-600 to-indigo-600">
                <Filter class="h-5 w-5" />
                Apply
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Products Grid/Table -->
        <Card class="border-0 shadow-2xl bg-white/90 backdrop-blur">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <div class="flex items-center justify-between">
              <CardTitle class="text-2xl flex items-center gap-2">
                <Sparkles class="h-6 w-6 text-blue-600" />
                Products
              </CardTitle>
              <div class="text-sm text-slate-600">
                Showing {{ productsData.data.length }} of {{ productsData.total }}
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold">Product</TableHead>
                  <TableHead class="font-semibold">SKU / Barcode</TableHead>
                  <TableHead class="font-semibold">Category</TableHead>
                  <TableHead class="font-semibold">Price</TableHead>
                  <TableHead class="font-semibold">Stock</TableHead>
                  <TableHead class="font-semibold">Status</TableHead>
                  <TableHead class="text-right font-semibold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="product in productsData.data"
                  :key="product.id"
                  class="hover:bg-blue-50/50 transition-colors"
                >
                  <TableCell>
                    <div class="font-semibold text-slate-900">{{ product.name }}</div>
                  </TableCell>
                  <TableCell>
                    <div class="space-y-1">
                      <div class="text-sm font-mono bg-slate-100 px-2 py-1 rounded inline-block">{{ product.sku }}</div>
                      <div class="text-xs text-slate-500">{{ product.barcode }}</div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" class="bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
                      {{ product.category?.name || 'Uncategorized' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="space-y-1">
                      <div class="text-lg font-bold text-slate-900">{{ formatCurrency(product.selling_price) }}</div>
                      <div class="text-xs text-slate-500">Cost: {{ formatCurrency(product.cost_price) }}</div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <div
                        :class="product.is_low_stock ? 'text-red-600 font-bold text-lg' : 'text-slate-900 font-semibold'"
                        class="flex items-center gap-1"
                      >
                        {{ product.quantity }}
                        <AlertTriangle
                          v-if="product.is_low_stock"
                          class="h-5 w-5 animate-pulse"
                        />
                      </div>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                      Min: {{ product.reorder_level }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge
                      :class="product.is_active
                        ? 'bg-gradient-to-r from-emerald-500 to-green-500 text-white'
                        : 'bg-slate-300 text-slate-700'"
                    >
                      {{ product.is_active ? '✓ Active' : '✕ Inactive' }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="openEditModal(product)"
                        class="hover:bg-blue-100 hover:text-blue-600"
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="deleteProduct(product)"
                        class="hover:bg-red-100 hover:text-red-600"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>

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
                    Barcode (Auto-generated)
                  </Label>
                  <div class="flex gap-2">
                    <Input
                      id="barcode"
                      v-model="form.barcode"
                      placeholder="Generated after price"
                      class="h-11 font-mono"
                      readonly
                    />
                    <Button
                      type="button"
                      variant="outline"
                      @click="form.barcode = generateBarcode()"
                      class="h-11 px-3"
                      title="Generate new barcode"
                    >
                      🔄
                    </Button>
                  </div>
                  <p class="text-xs text-slate-500">✨ Auto-generated when you enter selling price</p>
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
                        {{ (((form.selling_price - form.cost_price) / form.cost_price) * 100).toFixed(1) }}%
                      </div>
                    </div>
                  </div>
                  <div class="mt-2 pt-2 border-t border-green-300 text-xs text-green-800">
                    💡 Net Profit (excl. VAT): ${{ (calculateBasePrice(form.selling_price) - calculateBasePrice(form.cost_price)).toFixed(2) }}
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

