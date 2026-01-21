<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ShoppingCart, Scan, Trash2, Plus, CreditCard, Users } from 'lucide-vue-next'

const props = defineProps<{
  customers: Array<any>
}>()

const barcode = ref('')
const searchQuery = ref('')
const cart = ref<Array<any>>([])
const selectedCustomer = ref<number | null>(null)
const showPaymentModal = ref(false)

const payments = ref<Array<{ method: string; amount: number; reference?: string }>>([])

const cartTotal = computed(() => {
  return cart.value.reduce((total, item) => {
    return total + (item.quantity * item.unit_price)
  }, 0)
})

const cartTax = computed(() => {
  return cart.value.reduce((total, item) => {
    if (item.tax_rate) {
      const itemTotal = item.quantity * item.unit_price
      return total + (itemTotal * (item.tax_rate / 100))
    }
    return total
  }, 0)
})

const grandTotal = computed(() => {
  return cartTotal.value + cartTax.value
})

const totalPaid = computed(() => {
  return payments.value.reduce((sum, p) => sum + p.amount, 0)
})

const changeAmount = computed(() => {
  return Math.max(0, totalPaid.value - grandTotal.value)
})

const scanBarcode = async () => {
  if (!barcode.value) return

  try {
    const response = await fetch(`/api/products/scan?barcode=${barcode.value}`)
    const product = await response.json()

    if (product.id) {
      addToCart(product)
      barcode.value = ''
    }
  } catch (error) {
    console.error('Product not found')
  }
}

const searchProducts = async () => {
  if (!searchQuery.value) return

  try {
    const response = await fetch(`/api/products/search?q=${searchQuery.value}`)
    const products = await response.json()
    // Show products in a dropdown or modal for selection
  } catch (error) {
    console.error('Search failed')
  }
}

const addToCart = (product: any) => {
  const existingItem = cart.value.find(item => item.product_id === product.id)

  if (existingItem) {
    existingItem.quantity++
  } else {
    cart.value.push({
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      quantity: 1,
      unit_price: product.selling_price,
      tax_rate: product.tax_configuration?.rate || 0,
      available_quantity: product.quantity,
    })
  }
}

const removeFromCart = (index: number) => {
  cart.value.splice(index, 1)
}

const updateQuantity = (index: number, quantity: number) => {
  if (quantity > 0 && quantity <= cart.value[index].available_quantity) {
    cart.value[index].quantity = quantity
  }
}

const addPayment = (method: string) => {
  const remaining = grandTotal.value - totalPaid.value
  payments.value.push({
    method,
    amount: remaining > 0 ? remaining : 0,
  })
}

const removePayment = (index: number) => {
  payments.value.splice(index, 1)
}

const completeSale = () => {
  if (cart.value.length === 0) {
    alert('Cart is empty')
    return
  }

  if (totalPaid.value < grandTotal.value) {
    alert('Insufficient payment')
    return
  }

  const form = useForm({
    customer_id: selectedCustomer.value,
    items: cart.value.map(item => ({
      product_id: item.product_id,
      quantity: item.quantity,
      unit_price: item.unit_price,
      discount_amount: 0,
    })),
    payments: payments.value,
    discount_amount: 0,
  })

  form.post('/sales', {
    onSuccess: () => {
      cart.value = []
      payments.value = []
      selectedCustomer.value = null
      showPaymentModal.value = false
    },
  })
}
</script>

<template>
  <AppLayout title="Point of Sale">
    <div class="min-h-screen bg-gradient-to-br from-cyan-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Colorful Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
              <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                <ShoppingCart class="h-8 w-8" />
              </div>
              <div>
                <h1 class="text-4xl font-bold">Point of Sale</h1>
                <p class="text-cyan-100 text-lg mt-1">Fast & Easy Checkout Terminal</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <!-- Product Search & Cart -->
          <div class="lg:col-span-2 space-y-4">
            <!-- Barcode Scanner -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-cyan-50 to-blue-50">
                <CardTitle class="flex items-center gap-2 text-xl">
                  <div class="rounded-lg bg-cyan-100 p-2">
                    <Scan class="h-5 w-5 text-cyan-600" />
                  </div>
                  Quick Scan
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <div class="flex gap-2">
                  <Input
                    v-model="barcode"
                    placeholder="🔍 Scan or enter barcode..."
                    @keyup.enter="scanBarcode"
                    class="flex-1 h-12 border-2 focus:border-cyan-500"
                  />
                  <Button
                    @click="scanBarcode"
                    class="h-12 px-6 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700"
                  >
                    <Scan class="h-5 w-5 mr-2" />
                    Scan
                  </Button>
                </div>
              </CardContent>
            </Card>

            <!-- Product Search -->
            <Card class="border-0 shadow-xl bg-white">
              <CardContent class="pt-6">
                <div class="flex gap-2">
                  <Input
                    v-model="searchQuery"
                    placeholder="🔎 Search products by name or SKU..."
                    @keyup.enter="searchProducts"
                    class="flex-1 h-12 border-2 focus:border-blue-500"
                  />
                  <Button
                    @click="searchProducts"
                    class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                  >
                    Search
                  </Button>
                </div>
              </CardContent>
            </Card>

            <!-- Cart -->
            <Card class="border-0 shadow-2xl bg-white">
              <CardHeader class="border-b bg-gradient-to-r from-blue-50 to-indigo-50">
                <CardTitle class="flex items-center gap-2 text-2xl">
                  <div class="rounded-lg bg-blue-100 p-2">
                    <ShoppingCart class="h-6 w-6 text-blue-600" />
                  </div>
                  Shopping Cart
                  <Badge class="ml-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white">
                    {{ cart.length }} items
                  </Badge>
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <div v-if="cart.length === 0" class="py-12 text-center">
                  <ShoppingCart class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                  <p class="text-gray-500 text-lg">Cart is empty</p>
                  <p class="text-gray-400 text-sm mt-2">Scan or search for products to add</p>
                </div>
                <div v-else class="space-y-3">
                  <div
                    v-for="(item, index) in cart"
                    :key="index"
                    class="flex items-center justify-between rounded-xl border-2 border-blue-100 p-4 bg-gradient-to-r from-white to-blue-50 hover:border-blue-300 transition-all"
                  >
                    <div class="flex-1">
                      <div class="font-semibold text-lg text-slate-900">{{ item.name }}</div>
                      <div class="text-sm text-slate-600 mt-1">
                        <Badge variant="outline" class="mr-2">{{ item.sku }}</Badge>
                        ${{ item.unit_price.toFixed(2) }} each
                      </div>
                    </div>
                    <div class="flex items-center gap-3">
                      <Input
                        type="number"
                        :model-value="item.quantity"
                        @update:model-value="(val) => updateQuantity(index, Number(val))"
                        :max="item.available_quantity"
                        min="1"
                        class="w-20 h-10 border-2 text-center font-bold"
                      />
                      <div class="w-28 text-right">
                        <div class="text-2xl font-bold text-blue-600">
                          ${{ (item.quantity * item.unit_price).toFixed(2) }}
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        @click="removeFromCart(index)"
                        class="hover:bg-red-100 hover:text-red-600"
                      >
                        <Trash2 class="h-5 w-5" />
                      </Button>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Order Summary & Payment -->
          <div class="space-y-4">
            <!-- Customer Selection -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-purple-50 to-pink-50">
                <CardTitle class="flex items-center gap-2">
                  <div class="rounded-lg bg-purple-100 p-2">
                    <Users class="h-5 w-5 text-purple-600" />
                  </div>
                  Customer
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <Select v-model="selectedCustomer">
                  <SelectTrigger class="h-12 border-2">
                    <SelectValue placeholder="👤 Walk-in Customer" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="null">Walk-in Customer</SelectItem>
                    <SelectItem
                      v-for="customer in customers"
                      :key="customer.id"
                      :value="customer.id"
                    >
                      {{ customer.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </CardContent>
            </Card>

            <!-- Order Summary -->
            <Card class="border-0 shadow-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
              <CardHeader>
                <CardTitle class="text-white text-xl">Order Summary</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex justify-between text-lg">
                  <span class="text-blue-100">Subtotal:</span>
                  <span class="font-semibold">${{ cartTotal.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between text-lg">
                  <span class="text-blue-100">Tax:</span>
                  <span class="font-semibold">${{ cartTax.toFixed(2) }}</span>
                </div>
                <Separator class="bg-white/20" />
                <div class="flex justify-between text-2xl font-bold">
                  <span>Total:</span>
                  <span>${{ grandTotal.toFixed(2) }}</span>
                </div>
              </CardContent>
            </Card>

            <!-- Payment Methods -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-green-50 to-emerald-50">
                <CardTitle class="flex items-center gap-2">
                  <div class="rounded-lg bg-green-100 p-2">
                    <CreditCard class="h-5 w-5 text-green-600" />
                  </div>
                  Payment Methods
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <Button
                    @click="addPayment('CASH')"
                    variant="outline"
                    class="h-12 border-2 hover:bg-green-50 hover:border-green-500"
                  >
                    💵 Cash
                  </Button>
                  <Button
                    @click="addPayment('CARD')"
                    variant="outline"
                    class="h-12 border-2 hover:bg-blue-50 hover:border-blue-500"
                  >
                    💳 Card
                  </Button>
                  <Button
                    @click="addPayment('MPESA')"
                    variant="outline"
                    class="h-12 border-2 hover:bg-purple-50 hover:border-purple-500"
                  >
                    📱 M-Pesa
                  </Button>
                  <Button
                    @click="addPayment('BANK_TRANSFER')"
                    variant="outline"
                    class="h-12 border-2 hover:bg-orange-50 hover:border-orange-500"
                  >
                    🏦 Bank
                  </Button>
                </div>

                <div v-if="payments.length > 0" class="space-y-3">
                  <Separator />
                  <div
                    v-for="(payment, index) in payments"
                    :key="index"
                    class="flex items-center justify-between p-3 bg-slate-50 rounded-lg"
                  >
                    <Badge class="bg-blue-100 text-blue-800">{{ payment.method }}</Badge>
                    <div class="flex items-center gap-2">
                      <Input
                        v-model.number="payment.amount"
                        type="number"
                        step="0.01"
                        class="w-28 h-10 text-right font-bold border-2"
                      />
                      <Button
                        variant="ghost"
                        size="icon"
                        @click="removePayment(index)"
                        class="hover:bg-red-100"
                      >
                        <Trash2 class="h-4 w-4 text-red-600" />
                      </Button>
                    </div>
                  </div>
                  <Separator />
                  <div class="flex justify-between font-semibold text-lg">
                    <span>Paid:</span>
                    <span class="text-green-600">${{ totalPaid.toFixed(2) }}</span>
                  </div>
                  <div v-if="changeAmount > 0" class="flex justify-between text-xl font-bold">
                    <span>Change:</span>
                    <span class="text-emerald-600">${{ changeAmount.toFixed(2) }}</span>
                  </div>
                </div>

                <Button
                  @click="completeSale"
                  :disabled="cart.length === 0 || totalPaid < grandTotal"
                  class="w-full h-14 text-lg bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700"
                  size="lg"
                >
                  <CreditCard class="mr-2 h-6 w-6" />
                  Complete Sale
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

