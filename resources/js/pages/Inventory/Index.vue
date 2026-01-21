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
  AlertCircle,
  Package,
  TrendingUp,
  TrendingDown,
  Search,
  Plus,
  Minus,
  History,
  Download
} from 'lucide-vue-next'

interface Product {
  id: number
  name: string
  sku: string
  quantity: number
  reorder_level: number
  category: { name: string } | null
  is_low_stock: boolean
}

const mockProducts = [
  { id: 1, name: 'Laptop HP ProBook', sku: 'ELEC-001', quantity: 10, reorder_level: 5, category: { name: 'Electronics' }, is_low_stock: false },
  { id: 2, name: 'Wireless Mouse', sku: 'ELEC-002', quantity: 8, reorder_level: 10, category: { name: 'Electronics' }, is_low_stock: true },
  { id: 3, name: 'T-Shirt Blue', sku: 'CLOTH-001', quantity: 15, reorder_level: 20, category: { name: 'Clothing' }, is_low_stock: true },
  { id: 4, name: 'Jeans Black', sku: 'CLOTH-002', quantity: 30, reorder_level: 10, category: { name: 'Clothing' }, is_low_stock: false },
]

const products = ref(mockProducts)
const search = ref('')
const showLowStock = ref(false)

const filteredProducts = ref(products.value)

const adjustStock = (product: Product, type: 'increase' | 'decrease') => {
  alert(`Stock adjustment for ${product.name} - ${type}`)
  // This will be connected to backend
}
</script>

<template>
  <Head title="Inventory Management" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <AlertCircle class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Inventory Management</h1>
                  <p class="text-emerald-100 text-lg mt-1">Stock levels and adjustments</p>
                </div>
              </div>
            </div>
            <div class="flex gap-3">
              <Button class="bg-white text-emerald-600 hover:bg-emerald-50 gap-2">
                <History class="h-5 w-5" />
                View History
              </Button>
              <Button variant="outline" class="border-white text-white hover:bg-white/20 gap-2">
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
              <div class="text-3xl font-bold text-emerald-600">{{ products.length }}</div>
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
              <div class="text-3xl font-bold text-red-600">
                {{ products.filter(p => p.is_low_stock).length }}
              </div>
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
              <div class="text-3xl font-bold text-teal-600">
                {{ products.filter(p => !p.is_low_stock).length }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Total Value</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-cyan-600">$12,450</div>
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
                    placeholder="Search products..."
                    class="pl-12 h-12 border-2"
                  />
                </div>
              </div>
              <Button
                :variant="showLowStock ? 'default' : 'outline'"
                @click="showLowStock = !showLowStock"
                class="h-12 px-6 gap-2"
                :class="showLowStock ? 'bg-gradient-to-r from-red-500 to-orange-500' : ''"
              >
                <AlertCircle class="h-5 w-5" />
                Low Stock Only
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
                  <TableHead class="font-semibold">Status</TableHead>
                  <TableHead class="text-right font-semibold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="product in filteredProducts"
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
                        @click="adjustStock(product, 'increase')"
                      >
                        <Plus class="h-4 w-4 text-green-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-red-100"
                        @click="adjustStock(product, 'decrease')"
                      >
                        <Minus class="h-4 w-4 text-red-600" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="hover:bg-blue-100"
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
</style>

