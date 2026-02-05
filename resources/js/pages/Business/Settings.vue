<script setup lang="ts">
/* eslint-disable import/order */
import { Head, router, useForm } from '@inertiajs/vue3'
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
import { postJsonWithSanctum } from '@/lib/sanctum'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'

// Accept props from controller
const props = defineProps<{
  business: {
    id: number
    name: string
    business_type: string | null
    address: string | null
    phone: string | null
    email: string | null
    tax_id: string | null
    receipt_prefix: string
    currency: string
    timezone: string | null
    settings: {
      mpesa?: {
        consumer_key: string
        consumer_secret: string
        shortcode: string
        passkey: string
        environment: string
        callback_url: string
        head_office_shortcode?: string
        head_office_passkey?: string
        result_url?: string
        initiator_name?: string
        initiator_password?: string
        security_credential?: string
        simulate?: boolean
      }
    }
  }
  tax_configurations: Array<any>
}>()

// Initialize form with data from database
const form = useForm({
  name: props.business.name,
  business_type: props.business.business_type || '',
  address: props.business.address || '',
  phone: props.business.phone || '',
  email: props.business.email || '',
  tax_id: props.business.tax_id || '',
  receipt_prefix: props.business.receipt_prefix,
  currency: props.business.currency,
  timezone: props.business.timezone || '',
  // MPESA fields (pre-fill from business.settings.mpesa if present)
  mpesa_consumer_key: props.business.settings?.mpesa?.consumer_key ?? '',
  mpesa_consumer_secret: props.business.settings?.mpesa?.consumer_secret ?? '',
  mpesa_shortcode: props.business.settings?.mpesa?.shortcode ?? '',
  mpesa_passkey: props.business.settings?.mpesa?.passkey ?? '',
  mpesa_environment: props.business.settings?.mpesa?.environment ?? 'sandbox',
  mpesa_callback_url: props.business.settings?.mpesa?.callback_url ?? window.location.origin + '/api/payments/mpesa/callback',
  // Additional MPESA fields
  mpesa_head_office_shortcode: props.business.settings?.mpesa?.head_office_shortcode ?? props.business.settings?.mpesa?.shortcode ?? '',
  mpesa_head_office_passkey: props.business.settings?.mpesa?.head_office_passkey ?? '',
  mpesa_result_url: props.business.settings?.mpesa?.result_url ?? props.business.settings?.mpesa?.callback_url ?? window.location.origin + '/api/payments/mpesa/callback',
  mpesa_initiator_name: props.business.settings?.mpesa?.initiator_name ?? '',
  mpesa_initiator_password: props.business.settings?.mpesa?.initiator_password ?? '',
  mpesa_security_credential: props.business.settings?.mpesa?.security_credential ?? '',
  // Use numeric values 1/0 for the simulate flag so TypeScript treats it as number
  mpesa_simulate: props.business.settings?.mpesa?.simulate ? 1 : 0,
})

const submit = () => {
  form.put('/business/settings', {
    preserveScroll: true,
    onSuccess: () => {
      // Reload Inertia props so global values (like currency) are updated across pages
      router.reload()
      alert('Business settings updated successfully!')
    },
    onError: (errors) => {
      console.error('Update failed:', errors)
      alert('Failed to update settings. Please check the form.')
    },
  })
}

// Test MPESA credentials
const testMpesa = async () => {
  try {
    const res = await postJsonWithSanctum('/business/mpesa/test', {})

    const data = await res.json()
    if (res.ok && data.success) {
      alert('✅ MPESA Test Successful: ' + data.message)
    } else {
      alert('❌ MPESA Test Failed: ' + (data.message || res.statusText))
    }
  } catch (e: unknown) {
    console.error('Test MPESA error', e)
    const msg = e instanceof Error ? e.message : String(e)
    alert('Error testing MPESA credentials: ' + msg)
  }
}

// Toggle handler for simulate checkbox (typed to avoid template TS errors)
const toggleSimulate = (e: Event) => {
  const t = e.target as HTMLInputElement | null
  if (!t) return
  form.mpesa_simulate = t.checked ? 1 : 0
}
</script>

<template>
  <Head title="Business Settings" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-gray-50 to-zinc-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-slate-700 via-gray-800 to-zinc-900 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <Store class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">Business Settings</h1>
                  <p class="text-slate-200 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">Configure your business</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-3 sm:space-y-4 lg:space-y-6">
          <!-- Business Information - Mobile Optimized -->
          <Card class="border-0 shadow-xl">
            <CardHeader class="p-3 sm:p-4 lg:p-6">
              <div class="flex items-center gap-2 sm:gap-3">
                <div class="rounded-lg bg-blue-100 p-1.5 sm:p-2 flex-shrink-0">
                  <Building class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 text-blue-600" />
                </div>
                <div class="min-w-0">
                  <CardTitle class="text-sm sm:text-lg lg:text-2xl truncate">Business Information</CardTitle>
                  <CardDescription class="text-xs sm:text-sm">Basic details about your business</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-4 lg:p-6 pt-0 space-y-2 sm:space-y-3 lg:space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 lg:gap-4">
                <div class="col-span-1 sm:col-span-2 space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="name" class="text-xs sm:text-sm lg:text-base">Business Name *</Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    required
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="business_type" class="text-xs sm:text-sm lg:text-base">Business Type</Label>
                  <Input
                    id="business_type"
                    v-model="form.business_type"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="tax_id" class="text-xs sm:text-sm lg:text-base">Tax ID</Label>
                  <Input
                    id="tax_id"
                    v-model="form.tax_id"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Contact Information - Mobile Optimized -->
          <Card class="border-0 shadow-xl">
            <CardHeader class="p-3 sm:p-4 lg:p-6">
              <div class="flex items-center gap-2 sm:gap-3">
                <div class="rounded-lg bg-green-100 p-1.5 sm:p-2 flex-shrink-0">
                  <Phone class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 text-green-600" />
                </div>
                <div class="min-w-0">
                  <CardTitle class="text-sm sm:text-lg lg:text-2xl truncate">Contact Information</CardTitle>
                  <CardDescription class="text-xs sm:text-sm">How customers can reach you</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-4 lg:p-6 pt-0 space-y-2 sm:space-y-3 lg:space-y-4">
              <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                <Label for="email" class="flex items-center gap-2 text-xs sm:text-sm lg:text-base">
                  <Mail class="h-3 w-3 sm:h-4 sm:w-4" />
                  Email Address
                </Label>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                />
              </div>

              <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                <Label for="phone" class="flex items-center gap-2 text-xs sm:text-sm lg:text-base">
                  <Phone class="h-3 w-3 sm:h-4 sm:w-4" />
                  Phone Number
                </Label>
                <Input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                />
              </div>

              <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                <Label for="address" class="flex items-center gap-2 text-xs sm:text-sm lg:text-base">
                  <MapPin class="h-3 w-3 sm:h-4 sm:w-4" />
                  Business Address
                </Label>
                <Textarea
                  id="address"
                  v-model="form.address"
                  rows="3"
                  class="resize-none text-xs sm:text-sm lg:text-base min-h-[70px] sm:min-h-[80px]"
                />
              </div>
            </CardContent>
          </Card>

          <!-- POS Settings - Mobile Optimized -->
          <Card class="border-0 shadow-xl">
            <CardHeader class="p-3 sm:p-4 lg:p-6">
              <div class="flex items-center gap-2 sm:gap-3">
                <div class="rounded-lg bg-purple-100 p-1.5 sm:p-2 flex-shrink-0">
                  <Settings class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 text-purple-600" />
                </div>
                <div class="min-w-0">
                  <CardTitle class="text-sm sm:text-lg lg:text-2xl truncate">POS Configuration</CardTitle>
                  <CardDescription class="text-xs sm:text-sm">Point of sale system settings</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-4 lg:p-6 pt-0 space-y-2 sm:space-y-3 lg:space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 lg:gap-4">
                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="receipt_prefix" class="text-xs sm:text-sm lg:text-base">Receipt Prefix</Label>
                  <Input
                    id="receipt_prefix"
                    v-model="form.receipt_prefix"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                  <p class="text-[9px] sm:text-xs text-slate-500">e.g., DS000123</p>
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="currency" class="flex items-center gap-2 text-xs sm:text-sm lg:text-base">
                    <DollarSign class="h-3 w-3 sm:h-4 sm:w-4" />
                    Currency
                  </Label>
                  <Input
                    id="currency"
                    v-model="form.currency"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                  <p class="text-[9px] sm:text-xs text-slate-500">Default currency (e.g., USD)</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- MPESA Settings - Mobile Optimized -->
          <Card class="border-0 shadow-xl">
            <CardHeader class="p-3 sm:p-4 lg:p-6">
              <div class="flex items-center gap-2 sm:gap-3">
                <div class="rounded-lg bg-yellow-100 p-1.5 sm:p-2 flex-shrink-0">
                  <Settings class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 text-yellow-600" />
                </div>
                <div class="min-w-0">
                  <CardTitle class="text-sm sm:text-lg lg:text-2xl truncate">MPESA Configuration</CardTitle>
                  <CardDescription class="text-xs sm:text-sm">Mobile payment settings</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-3 sm:p-4 lg:p-6 pt-0 space-y-2 sm:space-y-3 lg:space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 lg:gap-4">
                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_consumer_key" class="text-xs sm:text-sm lg:text-base">Consumer Key</Label>
                  <Input
                    id="mpesa_consumer_key"
                    v-model="form.mpesa_consumer_key"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_consumer_secret" class="text-xs sm:text-sm lg:text-base">Consumer Secret</Label>
                  <Input
                    id="mpesa_consumer_secret"
                    v-model="form.mpesa_consumer_secret"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_shortcode" class="text-xs sm:text-sm lg:text-base">Shortcode</Label>
                  <Input
                    id="mpesa_shortcode"
                    v-model="form.mpesa_shortcode"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_passkey" class="text-xs sm:text-sm lg:text-base">Passkey</Label>
                  <Input
                    id="mpesa_passkey"
                    v-model="form.mpesa_passkey"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_environment" class="text-xs sm:text-sm lg:text-base">Environment</Label>
                  <Input
                    id="mpesa_environment"
                    v-model="form.mpesa_environment"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                  <p class="text-[9px] sm:text-xs text-slate-500">"live" or "sandbox"</p>
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_callback_url" class="text-xs sm:text-sm lg:text-base">Callback URL</Label>
                  <Input
                    id="mpesa_callback_url"
                    v-model="form.mpesa_callback_url"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <!-- New MPESA Fields -->
                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_head_office_shortcode" class="text-xs sm:text-sm lg:text-base">Head Office Shortcode</Label>
                  <Input
                    id="mpesa_head_office_shortcode"
                    v-model="form.mpesa_head_office_shortcode"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_head_office_passkey" class="text-xs sm:text-sm lg:text-base">Head Office Passkey</Label>
                  <Input
                    id="mpesa_head_office_passkey"
                    v-model="form.mpesa_head_office_passkey"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_result_url" class="text-xs sm:text-sm lg:text-base">Result URL</Label>
                  <Input
                    id="mpesa_result_url"
                    v-model="form.mpesa_result_url"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_initiator_name" class="text-xs sm:text-sm lg:text-base">Initiator Name</Label>
                  <Input
                    id="mpesa_initiator_name"
                    v-model="form.mpesa_initiator_name"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_initiator_password" class="text-xs sm:text-sm lg:text-base">Initiator Password</Label>
                  <Input
                    id="mpesa_initiator_password"
                    v-model="form.mpesa_initiator_password"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <div class="space-y-1 sm:space-y-1.5 lg:space-y-2">
                  <Label for="mpesa_security_credential" class="text-xs sm:text-sm lg:text-base">Security Credential</Label>
                  <Input
                    id="mpesa_security_credential"
                    v-model="form.mpesa_security_credential"
                    class="h-9 sm:h-10 lg:h-12 text-xs sm:text-sm lg:text-base"
                  />
                </div>

                <!-- Simulate Checkbox -->
                <div class="col-span-1 sm:col-span-2 pt-2 sm:pt-3 lg:pt-4">
                  <div class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 lg:p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <input
                      type="checkbox"
                      id="mpesa_simulate"
                      :checked="form.mpesa_simulate === 1"
                      @change="toggleSimulate"
                      class="h-4 w-4 sm:h-5 sm:w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                    />
                    <Label for="mpesa_simulate" class="text-xs sm:text-sm lg:text-base cursor-pointer select-none">
                      Enable Simulation Mode (Sandbox Only)
                    </Label>
                  </div>
                </div>
              </div>

              <!-- Test Button -->
              <div class="pt-2 sm:pt-3 lg:pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  class="w-full sm:w-auto h-9 sm:h-10 lg:h-12 gap-2 text-xs sm:text-sm lg:text-base"
                  @click="testMpesa"
                >
                  <Settings class="h-3 w-3 sm:h-4 sm:w-4 lg:h-5 lg:w-5" />
                  Test Credentials
                </Button>
              </div>
            </CardContent>
          </Card>

          <Button type="submit" class="w-full h-9 sm:h-10 lg:h-12 text-sm sm:text-base lg:text-lg bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-lg">
            <Save class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6 mr-2" />
            Save Changes
          </Button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

