<script setup lang="ts">
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

// CSRF token for POST requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const testMpesa = async () => {
  try {
    const res = await fetch('/business/mpesa/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    })

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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-gray-50 to-zinc-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
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

          <!-- MPESA Settings -->
          <Card class="border-0 shadow-xl">
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-lg bg-yellow-100 p-2">
                  <Settings class="h-6 w-6 text-yellow-600" />
                </div>
                <div>
                  <CardTitle class="text-2xl">MPESA Configuration</CardTitle>
                  <CardDescription>Mobile payment settings</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="mpesa_consumer_key">MPESA Consumer Key</Label>
                  <Input
                    id="mpesa_consumer_key"
                    v-model="form.mpesa_consumer_key"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_consumer_secret">MPESA Consumer Secret</Label>
                  <Input
                    id="mpesa_consumer_secret"
                    v-model="form.mpesa_consumer_secret"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_shortcode">MPESA Shortcode</Label>
                  <Input
                    id="mpesa_shortcode"
                    v-model="form.mpesa_shortcode"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_passkey">MPESA Passkey</Label>
                  <Input
                    id="mpesa_passkey"
                    v-model="form.mpesa_passkey"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_environment">MPESA Environment</Label>
                  <Input
                    id="mpesa_environment"
                    v-model="form.mpesa_environment"
                    class="h-12"
                  />
                  <p class="text-xs text-slate-500">Set to "live" or "sandbox"</p>
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_callback_url">MPESA Callback URL</Label>
                  <Input
                    id="mpesa_callback_url"
                    v-model="form.mpesa_callback_url"
                    class="h-12"
                  />
                  <p class="text-xs text-slate-500">e.g., https://yourdomain.com/api/payments/mpesa/callback</p>
                </div>

                <!-- New MPESA Fields -->
                <div class="space-y-2">
                  <Label for="mpesa_head_office_shortcode">Head Office Shortcode</Label>
                  <Input
                    id="mpesa_head_office_shortcode"
                    v-model="form.mpesa_head_office_shortcode"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_head_office_passkey">Head Office Passkey</Label>
                  <Input
                    id="mpesa_head_office_passkey"
                    v-model="form.mpesa_head_office_passkey"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_result_url">Result URL</Label>
                  <Input
                    id="mpesa_result_url"
                    v-model="form.mpesa_result_url"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_initiator_name">Initiator Name</Label>
                  <Input
                    id="mpesa_initiator_name"
                    v-model="form.mpesa_initiator_name"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_initiator_password">Initiator Password</Label>
                  <Input
                    id="mpesa_initiator_password"
                    v-model="form.mpesa_initiator_password"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_security_credential">Security Credential</Label>
                  <Input
                    id="mpesa_security_credential"
                    v-model="form.mpesa_security_credential"
                    class="h-12"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="mpesa_simulate" class="flex items-center gap-2">
                    <input
                      id="mpesa_simulate"
                      type="checkbox"
                      :checked="form.mpesa_simulate == 1"
                      @change="toggleSimulate"
                      class="h-4 w-4"
                    />
                    Simulate Mode
                  </Label>
                  <p class="text-xs text-slate-500">Enable to test transactions without real payments</p>
                </div>
              </div>

              <!-- Test Credentials Button -->
              <div class="flex justify-end">
                <Button
                  type="button"
                  @click="testMpesa"
                  class="h-12 px-6 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 gap-2"
                >
                  Test MPESA Credentials
                </Button>
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

