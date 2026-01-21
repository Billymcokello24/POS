<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Store,
  Save,
  Building,
  Mail,
  Phone,
  MapPin,
  DollarSign,
  Settings
} from 'lucide-vue-next'

const form = useForm({
  name: 'Demo Store',
  business_type: 'retail',
  address: '123 Main Street',
  phone: '+1234567890',
  email: 'demo@store.com',
  tax_id: 'TAX123456',
  receipt_prefix: 'DS',
  currency: 'USD',
})

const submit = () => {
  alert('Settings will be saved to backend')
  // form.put('/business/settings')
}
</script>

<template>
  <Head title="Business Settings" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-gray-50 to-zinc-50 p-6">
      <div class="mx-auto max-w-4xl space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-700 via-gray-800 to-zinc-900 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
              <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                <Store class="h-8 w-8" />
              </div>
              <div>
                <h1 class="text-4xl font-bold">Business Settings</h1>
                <p class="text-slate-200 text-lg mt-1">Configure your business information</p>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <!-- Business Information -->
          <Card class="border-0 shadow-xl">
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-lg bg-blue-100 p-2">
                  <Building class="h-6 w-6 text-blue-600" />
                </div>
                <div>
                  <CardTitle class="text-2xl">Business Information</CardTitle>
                  <CardDescription>Basic details about your business</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 space-y-2">
                  <Label for="name">Business Name *</Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    required
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="business_type">Business Type</Label>
                  <Input
                    id="business_type"
                    v-model="form.business_type"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="tax_id">Tax ID</Label>
                  <Input
                    id="tax_id"
                    v-model="form.tax_id"
                    class="h-12"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Contact Information -->
          <Card class="border-0 shadow-xl">
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-lg bg-green-100 p-2">
                  <Phone class="h-6 w-6 text-green-600" />
                </div>
                <div>
                  <CardTitle class="text-2xl">Contact Information</CardTitle>
                  <CardDescription>How customers can reach you</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="email" class="flex items-center gap-2">
                  <Mail class="h-4 w-4" />
                  Email Address
                </Label>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="h-12"
                />
              </div>

              <div class="space-y-2">
                <Label for="phone" class="flex items-center gap-2">
                  <Phone class="h-4 w-4" />
                  Phone Number
                </Label>
                <Input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  class="h-12"
                />
              </div>

              <div class="space-y-2">
                <Label for="address" class="flex items-center gap-2">
                  <MapPin class="h-4 w-4" />
                  Business Address
                </Label>
                <Textarea
                  id="address"
                  v-model="form.address"
                  rows="3"
                  class="resize-none"
                />
              </div>
            </CardContent>
          </Card>

          <!-- POS Settings -->
          <Card class="border-0 shadow-xl">
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-lg bg-purple-100 p-2">
                  <Settings class="h-6 w-6 text-purple-600" />
                </div>
                <div>
                  <CardTitle class="text-2xl">POS Configuration</CardTitle>
                  <CardDescription>Point of sale system settings</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="receipt_prefix">Receipt Prefix</Label>
                  <Input
                    id="receipt_prefix"
                    v-model="form.receipt_prefix"
                    class="h-12"
                  />
                  <p class="text-xs text-slate-500">Prefix for receipt numbers (e.g., DS000123)</p>
                </div>

                <div class="space-y-2">
                  <Label for="currency" class="flex items-center gap-2">
                    <DollarSign class="h-4 w-4" />
                    Currency
                  </Label>
                  <Input
                    id="currency"
                    v-model="form.currency"
                    class="h-12"
                  />
                  <p class="text-xs text-slate-500">Default currency (e.g., USD, EUR)</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Save Button -->
          <div class="flex justify-end gap-4">
            <Button type="button" variant="outline" class="h-12 px-6">
              Cancel
            </Button>
            <Button
              type="submit"
              class="h-12 px-6 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 gap-2"
              :disabled="form.processing"
            >
              <Save class="h-5 w-5" />
              {{ form.processing ? 'Saving...' : 'Save Settings' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

