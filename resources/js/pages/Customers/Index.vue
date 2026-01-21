<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  Users,
  Plus,
  Search,
  Mail,
  Phone,
  DollarSign,
  ShoppingCart,
  Eye,
  Edit,
  UserPlus
} from 'lucide-vue-next'

interface Customer {
  id: number
  name: string
  email: string
  phone: string
  total_spent: number
  total_visits: number
  last_visit_at: string
}

const mockCustomers = [
  { id: 1, name: 'John Doe', email: 'john@example.com', phone: '+1234567890', total_spent: 1250.00, total_visits: 15, last_visit_at: '2026-01-21' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', phone: '+1234567891', total_spent: 850.50, total_visits: 8, last_visit_at: '2026-01-20' },
  { id: 3, name: 'Bob Johnson', email: 'bob@example.com', phone: '+1234567892', total_spent: 2100.75, total_visits: 22, last_visit_at: '2026-01-19' },
  { id: 4, name: 'Alice Williams', email: 'alice@example.com', phone: '+1234567893', total_spent: 450.25, total_visits: 5, last_visit_at: '2026-01-18' },
]

const customers = ref(mockCustomers)
const search = ref('')
</script>

<template>
  <Head title="Customers" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Users class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Customer Management</h1>
                  <p class="text-indigo-100 text-lg mt-1">Manage your customer relationships</p>
                </div>
              </div>
            </div>
            <Button class="bg-white text-indigo-600 hover:bg-indigo-50 gap-2 h-12 px-6">
              <UserPlus class="h-5 w-5" />
              Add Customer
            </Button>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid gap-4 md:grid-cols-4">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <Users class="h-4 w-4" />
                Total Customers
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-indigo-600">{{ customers.length }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <DollarSign class="h-4 w-4" />
                Total Revenue
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-purple-600">
                ${{ customers.reduce((sum, c) => sum + c.total_spent, 0).toFixed(2) }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600 flex items-center gap-2">
                <ShoppingCart class="h-4 w-4" />
                Total Visits
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-pink-600">
                {{ customers.reduce((sum, c) => sum + c.total_visits, 0) }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Avg Spending</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-orange-600">
                ${{ (customers.reduce((sum, c) => sum + c.total_spent, 0) / customers.length).toFixed(2) }}
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Search -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-6">
            <div class="relative">
              <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
              <Input
                v-model="search"
                placeholder="Search customers by name, email, or phone..."
                class="pl-12 h-12 border-2"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Customers Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="customer in customers"
            :key="customer.id"
            class="border-0 shadow-xl bg-white hover:shadow-2xl transition-all group"
          >
            <CardHeader>
              <div class="flex items-start gap-4">
                <div class="rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 p-4 text-white font-bold text-xl group-hover:scale-110 transition-transform">
                  {{ customer.name.charAt(0) }}
                </div>
                <div class="flex-1">
                  <CardTitle class="text-xl mb-2">{{ customer.name }}</CardTitle>
                  <div class="space-y-1 text-sm text-slate-600">
                    <div class="flex items-center gap-2">
                      <Mail class="h-3 w-3" />
                      {{ customer.email }}
                    </div>
                    <div class="flex items-center gap-2">
                      <Phone class="h-3 w-3" />
                      {{ customer.phone }}
                    </div>
                  </div>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                <div>
                  <div class="text-2xl font-bold text-indigo-600">
                    ${{ customer.total_spent.toFixed(2) }}
                  </div>
                  <div class="text-xs text-slate-500">Total Spent</div>
                </div>
                <div>
                  <div class="text-2xl font-bold text-purple-600">
                    {{ customer.total_visits }}
                  </div>
                  <div class="text-xs text-slate-500">Visits</div>
                </div>
              </div>

              <div class="pt-2">
                <div class="text-xs text-slate-500 mb-2">Last Visit</div>
                <Badge variant="outline" class="bg-slate-50">
                  {{ new Date(customer.last_visit_at).toLocaleDateString() }}
                </Badge>
              </div>

              <div class="flex gap-2 pt-4 border-t">
                <Button variant="outline" size="sm" class="flex-1 hover:bg-indigo-50">
                  <Eye class="h-4 w-4 mr-2" />
                  View
                </Button>
                <Button variant="outline" size="sm" class="flex-1 hover:bg-purple-50">
                  <Edit class="h-4 w-4 mr-2" />
                  Edit
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Add New Customer Card -->
          <Card class="border-2 border-dashed border-indigo-300 bg-indigo-50/50 hover:bg-indigo-100/50 hover:border-indigo-500 transition-all cursor-pointer group">
            <CardContent class="flex flex-col items-center justify-center h-full min-h-[300px]">
              <div class="rounded-full bg-indigo-200 group-hover:bg-indigo-300 p-6 mb-4 transition-colors">
                <Plus class="h-12 w-12 text-indigo-600" />
              </div>
              <h3 class="text-xl font-bold text-indigo-900 mb-2">Add New Customer</h3>
              <p class="text-sm text-indigo-600">Click to register a new customer</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

