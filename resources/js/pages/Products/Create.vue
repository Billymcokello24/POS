<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'

const props = defineProps<{
  categories: Array<any>
  taxConfigurations: Array<any>
}>()

const form = useForm({
  name: '',
  category_id: null,
  sku: '',
  barcode: '',
  barcode_type: 'CODE128',
  description: '',
  cost_price: 0,
  selling_price: 0,
  quantity: 0,
  reorder_level: 10,
  unit: 'pcs',
  track_inventory: true,
  is_active: true,
  tax_configuration_id: null,
})

const submit = () => {
  form.post('/products', {
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <AppLayout title="Create Product">
    <div class="min-h-screen bg-gradient-to-br from-purple-50 via-pink-50 to-orange-50 p-6">
      <div class="mx-auto max-w-3xl space-y-6">
        <!-- Colorful Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10">
            <h1 class="text-4xl font-bold">Create Product</h1>
            <p class="text-purple-100 text-lg mt-2">Add a new product to your inventory</p>
          </div>
        </div>

      <form @submit.prevent="submit" class="space-y-6">
          <!-- Basic Information -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader>
              <CardTitle>Basic Information</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="name">Product Name *</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  required
                  placeholder="Enter product name"
                />
                <div v-if="form.errors.name" class="text-sm text-red-600">
                  {{ form.errors.name }}
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="category_id">Category</Label>
                  <Select v-model="form.category_id">
                    <SelectTrigger>
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                      >
                        {{ category.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="space-y-2">
                  <Label for="unit">Unit</Label>
                  <Input
                    id="unit"
                    v-model="form.unit"
                    placeholder="e.g., pcs, kg, liter"
                  />
                </div>
              </div>

              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="form.description"
                  placeholder="Enter product description"
                  rows="3"
                />
              </div>
            </CardContent>
          </Card>

          <!-- Pricing -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader class="bg-gradient-to-r from-green-50 to-emerald-50">
              <CardTitle class="flex items-center gap-2 text-xl">
                💰 Pricing
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-6">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="cost_price">Cost Price</Label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                    <Input
                      id="cost_price"
                      type="number"
                      step="0.01"
                      v-model="form.cost_price"
                      class="pl-7"
                      placeholder="0.00"
                    />
                  </div>
                </div>
                <div class="space-y-2">
                  <Label for="selling_price">Selling Price *</Label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                    <Input
                      id="selling_price"
                      type="number"
                      step="0.01"
                      v-model="form.selling_price"
                      required
                      class="pl-7"
                      placeholder="0.00"
                    />
                  </div>
                </div>
              </div>
              
              <div class="space-y-2">
                <Label for="tax_configuration_id">Tax Configuration</Label>
                <Select v-model="form.tax_configuration_id">
                  <SelectTrigger>
                    <SelectValue placeholder="Select tax rule (optional)" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="tax in taxConfigurations"
                      :key="tax.id"
                      :value="tax.id"
                    >
                      {{ tax.name }} ({{ tax.rate }}%)
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>

          <!-- Inventory -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader class="bg-gradient-to-r from-blue-50 to-cyan-50">
              <CardTitle class="flex items-center gap-2 text-xl">
                📦 Inventory
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-6">
              <div class="flex items-center justify-between pointer-events-none opacity-80">
                 <!-- Track Inventory is forced to true in form default, but we can show it as checked disabled or allow toggle -->
                 <div class="space-y-0.5">
                   <Label>Track Inventory</Label>
                   <p class="text-sm text-gray-500">Enable stock tracking for this product</p>
                 </div>
                 <Switch v-model:checked="form.track_inventory" />
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="quantity">Initial Stock</Label>
                  <Input
                    id="quantity"
                    type="number"
                    v-model="form.quantity"
                    placeholder="0"
                  />
                </div>
                <div class="space-y-2">
                  <Label for="reorder_level">Reorder Level</Label>
                  <Input
                    id="reorder_level"
                    type="number"
                    v-model="form.reorder_level"
                    placeholder="10"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Identification -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader class="bg-gradient-to-r from-indigo-50 to-purple-50">
              <CardTitle class="flex items-center gap-2 text-xl">
                🏷️ Identification
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-6">
              <div class="space-y-2">
                <Label for="sku">SKU (Stock Keeping Unit)</Label>
                <Input
                  id="sku"
                  v-model="form.sku"
                  placeholder="Leave empty to auto-generate"
                />
                <p class="text-xs text-gray-500">Unique code for the product</p>
              </div>

              <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 space-y-2">
                  <Label for="barcode">Barcode</Label>
                  <Input
                    id="barcode"
                    v-model="form.barcode"
                    placeholder="Scan or enter barcode"
                  />
                  <p class="text-xs text-gray-500">Enter manually or scan using a reader</p>
                </div>
                <div class="space-y-2">
                  <Label for="barcode_type">Type</Label>
                  <Select v-model="form.barcode_type">
                    <SelectTrigger>
                      <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="CODE128">CODE128</SelectItem>
                      <SelectItem value="EAN13">EAN13</SelectItem>
                      <SelectItem value="UPCA">UPCA</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Status -->
          <Card class="border-0 shadow-xl bg-white">
            <CardHeader class="bg-gradient-to-r from-pink-50 to-rose-50">
              <CardTitle class="flex items-center gap-2 text-xl">
                ⚡ Status
              </CardTitle>
            </CardHeader>
            <CardContent class="pt-6">
              <div class="flex items-center justify-between rounded-lg border-2 border-pink-100 bg-pink-50/50 p-4">
                <div>
                  <Label class="text-lg">Active</Label>
                  <p class="text-sm text-gray-600">
                    Product is available for sale
                  </p>
                </div>
                <Switch v-model:checked="form.is_active" />
              </div>
            </CardContent>
          </Card>

          <!-- Actions -->
          <div class="flex justify-end gap-4">
            <Button
              type="button"
              variant="outline"
              @click="$inertia.visit('/products')"
              class="h-12 px-6 border-2"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              :disabled="form.processing"
              class="h-12 px-6 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700"
            >
              {{ form.processing ? 'Creating...' : '✨ Create Product' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

