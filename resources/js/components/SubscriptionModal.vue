<script setup lang="ts">
import { X, Loader2, CreditCard, Building2, User, Mail, Lock, Phone } from 'lucide-vue-next'
import { ref, computed } from 'vue'

import axios from '@/axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Plan {
    id: number
    name: string
    description: string
    price_monthly: number
    price_yearly: number
    currency: string
    features: { id: number; name: string; description: string }[]
    is_popular: boolean
}

const props = defineProps<{
    plan: Plan
    open: boolean
}>()

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'success'): void
}>()

// Steps: 1 = Business Info, 2 = Payment
const currentStep = ref(1)
const billingCycle = ref<'monthly' | 'yearly'>('monthly')
const isLoading = ref(false)
const error = ref('')

// Form data
const businessName = ref('')
const adminName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const phoneNumber = ref('')

// Payment data
const subscriptionId = ref<number | null>(null)
const checkoutRequestId = ref<string | null>(null)
const paymentPolling = ref<number | null>(null)

const selectedAmount = computed(() => {
    return billingCycle.value === 'yearly'
        ? props.plan.price_yearly
        : props.plan.price_monthly
})

const close = () => {
    if (paymentPolling.value) {
        clearInterval(paymentPolling.value)
    }
    emit('close')
}

const nextStep = async () => {
    if (currentStep.value === 1) {
        await createSubscription()
    } else if (currentStep.value === 2) {
        await initiatePayment()
    }
}

const createSubscription = async () => {
    error.value = ''
    isLoading.value = true

    try {
        const response = await axios.post('/api/public/subscriptions/create', {
            plan_id: props.plan.id,
            billing_cycle: billingCycle.value,
            business_name: businessName.value,
            admin_name: adminName.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        })

        if (response.data.success) {
            subscriptionId.value = response.data.data.subscription_id
            currentStep.value = 2
        } else {
            error.value = response.data.message || 'Failed to create subscription'
        }
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors
            error.value = Object.values(errors).flat().join(', ')
        } else {
            error.value = err.response?.data?.message || 'Failed to create subscription'
        }
    } finally {
        isLoading.value = false
    }
}

const initiatePayment = async () => {
    error.value = ''
    isLoading.value = true

    // Format phone number to 254 format
    let formattedPhone = phoneNumber.value.replace(/\s+/g, '')
    if (formattedPhone.startsWith('0')) {
        formattedPhone = '254' + formattedPhone.substring(1)
    } else if (formattedPhone.startsWith('+254')) {
        formattedPhone = formattedPhone.substring(1)
    } else if (!formattedPhone.startsWith('254')) {
        formattedPhone = '254' + formattedPhone
    }

    try {
        const response = await axios.post('/api/public/subscriptions/payment/initiate', {
            subscription_id: subscriptionId.value,
            phone_number: formattedPhone,
        })

        if (response.data.success) {
            checkoutRequestId.value = response.data.data.checkout_request_id
            // Start polling for payment status
            startPaymentPolling()
        } else {
            error.value = response.data.message || 'Failed to initiate payment'
            isLoading.value = false
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to initiate payment'
        isLoading.value = false
    }
}

const startPaymentPolling = () => {
    let attempts = 0
    const maxAttempts = 60 // Poll for 2 minutes (60 * 2s)

    paymentPolling.value = setInterval(async () => {
        attempts++

        try {
            const response = await axios.post('/api/public/subscriptions/payment/status', {
                subscription_id: subscriptionId.value,
            })

            if (response.data.success && response.data.is_paid) {
                // Payment successful!
                if (paymentPolling.value) {
                    clearInterval(paymentPolling.value)
                }
                isLoading.value = false

                // Redirect to success page
                window.location.href = '/subscription-success'
            } else if (attempts >= maxAttempts) {
                // Timeout
                if (paymentPolling.value) {
                    clearInterval(paymentPolling.value)
                }
                error.value = 'Payment timeout. Please check your M-PESA and contact support if amount was deducted.'
                isLoading.value = false
            }
        } catch (err) {
            console.error('Payment status check failed:', err)
        }
    }, 2000) // Check every 2 seconds
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm p-2 sm:p-4" @click.self="close">
        <div class="bg-white rounded-xl sm:rounded-2xl w-full max-w-2xl max-h-[95vh] flex flex-col shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between flex-shrink-0">
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900">Subscribe to {{ plan.name }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Step {{ currentStep }} of 2 - {{ currentStep === 1 ? 'Business Details' : 'Payment' }}
                    </p>
                </div>
                <button @click="close" class="p-1.5 hover:bg-slate-100 rounded-full transition-colors flex-shrink-0">
                    <X :size="20" class="text-slate-600" />
                </button>
            </div>

            <!-- Content - Scrollable if needed but fits most screens -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <!-- Error Message -->
                <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-xs">
                    {{ error }}
                </div>

                <!-- Step 1: Business Information -->
                <div v-if="currentStep === 1" class="space-y-4">
                    <!-- Billing Cycle Selection -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-2 block">Select Billing Cycle</Label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                @click="billingCycle = 'monthly'"
                                :class="[
                                    'p-3 rounded-lg border-2 transition-all',
                                    billingCycle === 'monthly'
                                        ? 'border-indigo-600 bg-indigo-100'
                                        : 'border-slate-200 bg-white hover:border-indigo-300'
                                ]"
                            >
                                <div class="text-left">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Monthly</div>
                                    <div class="text-lg sm:text-xl font-black text-slate-900 mt-1">
                                        {{ plan.currency }} {{ plan.price_monthly.toLocaleString() }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">per month</div>
                                </div>
                            </button>
                            <button
                                @click="billingCycle = 'yearly'"
                                :class="[
                                    'p-3 rounded-lg border-2 transition-all relative',
                                    billingCycle === 'yearly'
                                        ? 'border-indigo-600 bg-indigo-100'
                                        : 'border-slate-200 bg-white hover:border-indigo-300'
                                ]"
                            >
                                <div class="absolute -top-1.5 -right-1.5 bg-green-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full uppercase">
                                    Save
                                </div>
                                <div class="text-left">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Yearly</div>
                                    <div class="text-lg sm:text-xl font-black text-slate-900 mt-1">
                                        {{ plan.currency }} {{ plan.price_yearly.toLocaleString() }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">per year</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Business Name -->
                    <div class="space-y-1.5">
                        <Label for="business_name" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            <Building2 :size="12" class="inline mr-1" />Business Name
                        </Label>
                        <Input
                            id="business_name"
                            v-model="businessName"
                            type="text"
                            placeholder="Acme Retail Ltd"
                            required
                            class="h-9 text-sm rounded-lg border-slate-300"
                        />
                    </div>

                    <!-- Admin Name -->
                    <div class="space-y-1.5">
                        <Label for="admin_name" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            <User :size="12" class="inline mr-1" />Administrator Name
                        </Label>
                        <Input
                            id="admin_name"
                            v-model="adminName"
                            type="text"
                            placeholder="John Doe"
                            required
                            class="h-9 text-sm rounded-lg border-slate-300"
                        />
                    </div>

                    <!-- Email -->
                    <div class="space-y-1.5">
                        <Label for="email" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            <Mail :size="12" class="inline mr-1" />Email Address
                        </Label>
                        <Input
                            id="email"
                            v-model="email"
                            type="email"
                            placeholder="admin@company.com"
                            required
                            class="h-9 text-sm rounded-lg border-slate-300"
                        />
                    </div>

                    <!-- Password -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="password" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                <Lock :size="12" class="inline mr-1" />Password
                            </Label>
                            <Input
                                id="password"
                                v-model="password"
                                type="password"
                                required
                                class="h-9 text-sm rounded-lg border-slate-300"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="password_confirmation" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                Confirm Password
                            </Label>
                            <Input
                                id="password_confirmation"
                                v-model="passwordConfirmation"
                                type="password"
                                required
                                class="h-9 text-sm rounded-lg border-slate-300"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment -->
                <div v-if="currentStep === 2" class="space-y-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-green-500 rounded-full flex-shrink-0">
                                <CreditCard :size="18" class="text-white" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-sm text-slate-900">M-PESA Payment</h3>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Enter your M-PESA phone number to receive an STK push
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Summary -->
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-slate-600">Plan</span>
                            <span class="text-xs font-bold text-slate-900">{{ plan.name }} ({{ billingCycle === 'yearly' ? 'Yearly' : 'Monthly' }})</span>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                            <span class="text-sm font-black text-slate-900">Total Amount</span>
                            <span class="text-xl font-black text-indigo-600">
                                {{ plan.currency }} {{ selectedAmount.toLocaleString() }}
                            </span>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="space-y-1.5">
                        <Label for="phone_number" class="text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            <Phone :size="12" class="inline mr-1" />M-PESA Phone Number
                        </Label>
                        <Input
                            id="phone_number"
                            v-model="phoneNumber"
                            type="tel"
                            placeholder="0712345678"
                            required
                            :disabled="isLoading"
                            class="h-9 text-sm rounded-lg border-slate-300"
                        />
                        <p class="text-[10px] text-slate-500">Format: 07XXXXXXXX or 2547XXXXXXXX</p>
                    </div>

                    <!-- Payment Instructions -->
                    <div v-if="isLoading" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <Loader2 :size="18" class="text-blue-600 animate-spin flex-shrink-0" />
                            <div>
                                <h4 class="font-bold text-sm text-blue-900">Waiting for payment...</h4>
                                <p class="text-xs text-blue-700 mt-0.5">
                                    Please check your phone and enter your M-PESA PIN to complete payment.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between gap-3 mt-6 pt-4 border-t border-slate-200 flex-shrink-0">
                    <Button
                        v-if="currentStep === 2"
                        @click="currentStep = 1"
                        variant="outline"
                        :disabled="isLoading"
                        class="px-4 h-9 text-xs"
                    >
                        Back
                    </Button>
                    <div v-else></div>

                    <Button
                        @click="nextStep"
                        :disabled="isLoading"
                        class="px-6 h-9 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs"
                    >
                        <Loader2 v-if="isLoading" :size="16" class="animate-spin mr-2" />
                        <span v-if="currentStep === 1">Continue to Payment</span>
                        <span v-else>Confirm Payment</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Modal backdrop fade */
.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
