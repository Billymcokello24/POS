<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
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
import { ref } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'


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

  <AppLayout title="Customers">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <Users class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Customer Management</h1>
                  <p class="text-indigo-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">Manage your customer relationships</p>
                </div>
              </div>
            </div>
            <Button class="bg-white text-indigo-600 hover:bg-indigo-50 gap-1 sm:gap-2 h-9 sm:h-12 px-3 sm:px-6 text-xs sm:text-sm">
              <UserPlus class="h-4 w-4 sm:h-5 sm:w-5" />
              <span class="hidden xs:inline">Add Customer</span>
              <span class="xs:hidden">Add</span>
            </Button>
          </div>
        </div>

        <!-- Quick Stats - Mobile Optimized -->
        <div class="grid gap-3 sm:gap-4 grid-cols-2 md:grid-cols-4">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <Users class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Customers</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-indigo-600">{{ customers.length }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Revenue</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-purple-600 truncate">
                ${{ customers.reduce((sum, c) => sum + c.total_spent, 0).toFixed(2) }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 flex items-center gap-1 sm:gap-2">
                <ShoppingCart class="h-3 w-3 sm:h-4 sm:w-4" />
                <span class="truncate">Total Visits</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-pink-600">
                {{ customers.reduce((sum, c) => sum + c.total_visits, 0) }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-2 sm:pb-3 p-3 sm:p-6">
              <CardTitle class="text-xs sm:text-sm text-slate-600 truncate">Avg Spending</CardTitle>
            </CardHeader>
            <CardContent class="p-3 sm:p-6 pt-0">
              <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-orange-600 truncate">
                ${{ (customers.reduce((sum, c) => sum + c.total_spent, 0) / customers.length).toFixed(2) }}
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Search - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white">
          <CardContent class="pt-4 sm:pt-6 p-3 sm:p-6">
            <div class="relative">
              <Search class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-4 w-4 sm:h-5 sm:w-5 text-slate-400" />
              <Input
                v-model="search"
                placeholder="Search customers..."
                class="pl-10 sm:pl-12 h-10 sm:h-12 border-2 text-sm sm:text-base"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Customers Grid - Mobile Optimized -->
        <div class="grid gap-4 sm:gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="customer in customers"
            :key="customer.id"
            class="border-0 shadow-xl bg-white hover:shadow-2xl transition-all group"
          >
            <CardHeader class="p-4 sm:p-6">
              <div class="flex items-start gap-3 sm:gap-4">
                <div class="rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 p-3 sm:p-4 text-white font-bold text-lg sm:text-xl group-hover:scale-110 transition-transform flex-shrink-0">
                  {{ customer.name.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0">
                  <CardTitle class="text-base sm:text-lg lg:text-xl mb-2 truncate">{{ customer.name }}</CardTitle>
                  <div class="space-y-1 text-xs sm:text-sm text-slate-600">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Mail class="h-3 w-3 flex-shrink-0" />
                      <span class="truncate">{{ customer.email }}</span>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Phone class="h-3 w-3 flex-shrink-0" />
                      <span class="truncate">{{ customer.phone }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3 sm:space-y-4 p-4 sm:p-6 pt-0">
              <div class="grid grid-cols-2 gap-3 sm:gap-4 pt-3 sm:pt-4 border-t">
                <div>
                  <div class="text-lg sm:text-xl lg:text-2xl font-bold text-indigo-600 truncate">
                    ${{ customer.total_spent.toFixed(2) }}
                  </div>
                  <div class="text-[10px] sm:text-xs text-slate-500">Total Spent</div>
                </div>
                <div>
                  <div class="text-lg sm:text-xl lg:text-2xl font-bold text-purple-600">
                    {{ customer.total_visits }}
                  </div>
                  <div class="text-[10px] sm:text-xs text-slate-500">Visits</div>
                </div>
              </div>

              <div class="pt-2">
                <div class="text-[10px] sm:text-xs text-slate-500 mb-2">Last Visit</div>
                <Badge variant="outline" class="bg-slate-50 text-xs">
                  {{ new Date(customer.last_visit_at).toLocaleDateString() }}
                </Badge>
              </div>

              <div class="flex gap-2 pt-3 sm:pt-4 border-t">
                <Button variant="outline" size="sm" class="flex-1 hover:bg-indigo-50 h-8 sm:h-9 text-xs sm:text-sm">
                  <Eye class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                  View
                </Button>
                <Button variant="outline" size="sm" class="flex-1 hover:bg-purple-50 h-8 sm:h-9 text-xs sm:text-sm">
                  <Edit class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
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

