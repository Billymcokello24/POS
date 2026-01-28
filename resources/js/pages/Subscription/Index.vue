<script setup lang="ts">
/* eslint-disable import/order */
import { ref, onUnmounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { postJsonWithSanctum } from '@/lib/sanctum'

// Provide a file-local alias used throughout this file (mirrors Sales Create safePost)
const safePost = (url: string, payload: any, extraHeaders: Record<string,string> = {}) => postJsonWithSanctum(url, payload, extraHeaders)

// icons and UI
import {
    Check,
    Zap,
    Crown,
    Building2,
    ShieldCheck,
    Smartphone,
    Loader2,
    CheckCircle2,
    XCircle,
    Info,
    CreditCard,
    Sparkles,
    ShieldAlert,
    ArrowUpRight,
    Store,
    DollarSign
} from 'lucide-vue-next'

import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'

const props = defineProps<{
    plans: Array<any>
    currentSubscription: any
    pendingSubscription: any
    business: any
}>()

const showPaymentModal = ref(false)
const selectedPlan = ref<any | null>(null)
const billingCycle = ref<'monthly' | 'yearly'>('monthly')

// Strongly type the form
const form = useForm<{
    plan_id: string
    phone_number: string
    billing_cycle: string
    payment_method: string
    transaction_code: string
    card_number: string
    expiry_month: string
    expiry_year: string
    cvv: string
    cardholder_name: string
    reference_number: string
    bank_name: string
    account_number: string
    received_amount: number
    mpesa_receipt?: string
}>({
    plan_id: '',
    phone_number: '',
    billing_cycle: 'monthly',
    payment_method: 'stk',
    transaction_code: '',
    card_number: '',
    expiry_month: '',
    expiry_year: '',
    cvv: '',
    cardholder_name: '',
    reference_number: '',
    bank_name: '',
    account_number: '',
    received_amount: 0,
    mpesa_receipt: undefined
})

// --- Payment state ---
const processingMpesa = ref(false)
const paymentSuccess = ref(false)
const paymentError = ref<string | null>(null)
const paymentSuccessData = ref<{ receipt?: string; amount?: number } | null>(null)

// --- Monitoring state ---
// Only keep polling interval registry (used to clear polling on completion)
const pollingIntervals = new Map<string, number>()

// --- Cleanup on unmount ---
onUnmounted(() => {
    // Clear all polling intervals
    pollingIntervals.forEach((intervalId) => {
        clearInterval(intervalId)
    })
    pollingIntervals.clear()
})

const openPaymentModal = (plan: any) => {
    selectedPlan.value = plan
    form.plan_id = String(plan.id)
    form.phone_number = ''
    form.transaction_code = ''
    paymentError.value = null
    paymentSuccess.value = false
    processingMpesa.value = false
    showPaymentModal.value = true

    // Pre-fill received_amount for cash
    const amount = billingCycle.value === 'monthly' ? Number(plan.price_monthly) : Number(plan.price_yearly)
    if (form.payment_method === 'cash') {
        form.received_amount = amount
    }
}

// helper to close modal and reload page (not used directly in this file)
// kept for potential future use
// const closeAndReload = () => { showPaymentModal.value = false; window.location.reload() }

const resetProcessing = () => {
    paymentError.value = null
    processingMpesa.value = false
    paymentSuccess.value = false
    paymentSuccessData.value = null
}

// Initiate STK Push: open a popup synchronously, POST to /subscription/api/pay with CSRF token (expects JSON),
// then poll /api/payments/mpesa/check-status for the result. Mirrors the Sales STK flow to avoid 419s.
const initiateStkPush = async () => {
    paymentError.value = null
    paymentSuccess.value = false

    if (!selectedPlan.value) {
        paymentError.value = 'No plan selected.'
        return
    }

    const amount = billingCycle.value === 'monthly'
        ? Number(selectedPlan.value.price_monthly)
        : Number(selectedPlan.value.price_yearly)

    let phone = (form.phone_number || '').trim()
    if (!phone) {
        paymentError.value = 'Please enter phone number for STK prompt.'
        return
    }

    // Normalize Kenyan phone number
    if (phone.startsWith('0') && phone.length === 10) {
        phone = '254' + phone.substring(1)
    }

    processingMpesa.value = true

    // Open popup synchronously to avoid blockers (used later for receipts or messages)
    let popup: Window | null = null
    try {
        popup = window.open('', '_blank')
        if (popup) {
            try { popup.document.write('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Payment...</title></head><body><p style="font-family: sans-serif; padding:24px;">Sending M-Pesa prompt, please check your phone...</p></body></html>') } catch { /* ignore cross-origin write errors */ }
        }
    } catch {
        popup = null
    }

    try {
        // Use safePost helper which ensures the sanctum csrf-cookie and retries on 419.
        const resp = await safePost('/subscription/api/pay', {
            plan_id: selectedPlan.value.id,
            billing_cycle: billingCycle.value,
            phone_number: phone,
            amount: amount,
            payment_method: 'stk'
        })

        if (!resp.ok) {
            const text = await resp.text()
            // Try to parse as JSON for a friendly message
            let errorMessage = text
            try {
                const parsed = JSON.parse(text)
                errorMessage = parsed.message || text
            } catch {}

            // Surface a helpful error
            throw new Error(errorMessage || `HTTP ${resp.status}: ${resp.statusText}`)
        }

        const data = await resp.json()

        if (!data.success) {
            paymentError.value = data.message || 'Failed to initiate STK'
            processingMpesa.value = false
            if (popup && !popup.closed) try { popup.close() } catch {}
            return
        }

        const checkoutId = data.data?.checkout_request_id || data.data?.checkoutRequestID || data.checkout_request_id
        if (!checkoutId) {
            throw new Error('Missing checkout ID from server')
        }

        // Keep preloader and start polling
        paymentError.value = null
        paymentSuccess.value = false
        // Start polling for payment status using the same authoritative endpoint Sales uses
        pollMpesaStatus(checkoutId, amount, popup)

    } catch (err: any) {
        console.error('STK push error', err)
        paymentError.value = err?.message || 'STK push failed'
        processingMpesa.value = false
        if (popup && !popup.closed) {
            try { popup.close() } catch { /* ignore */ }
        }
    }
}

// Polling helper for M-Pesa status — mirrored from Sales/Create.vue
const pollMpesaStatus = (checkoutRequestId: string, amount: number, popup: Window | null = null) => {
    let attempts = 0
    const maxAttempts = 40 // Poll for ~2 minutes

    console.log('Starting M-Pesa status polling for:', checkoutRequestId)

    // M-Pesa result code friendly map (same as sales)
    const mpesaErrorCodes: Record<string, string> = {
        '0': 'Success',
        '1': 'Insufficient Balance',
        '1032': 'Request cancelled by user',
        '1037': 'Transaction pending (waiting for customer)',
        '2001': 'Wrong PIN entered',
        '2002': 'Agent number and Store number mismatch',
        '2006': 'PIN blocked',
        '2007': 'SIM Toolkit menu timeout',
        '2029': 'Transaction cancelled by customer',
        '4001': 'System error',
        '4031': 'Customer MSISDN not found',
        '4032': 'Invalid Bill Reference Number',
        '4999': 'Transaction still under processing'
    }

    const pendingCodes = ['1037', '4999']
    const cancelledCodes = ['1032', '2029']

    const interval = setInterval(async () => {
        attempts++
        try {
            const response = await safePost('/api/payments/mpesa/check-status', { checkout_request_id: checkoutRequestId })

            if (!response.ok) {
                console.error('Status check HTTP error:', response.status)
                if (attempts >= maxAttempts) {
                    clearInterval(interval)
                    paymentError.value = 'Payment verification timed out. Please check your M-Pesa messages.'
                    processingMpesa.value = false
                    if (popup && !popup.closed) try { popup.close() } catch {}
                }
                return
            }

            const result = await response.json()
            console.log('Status check result:', result)

            if (!result.success) {
                console.warn('Query returned success=false:', result.message)
                // Treat common transient backend messages as temporary (keep polling)
                const msg = String(result.message || '').toLowerCase()
                const transient = [
                    'authentication failed',
                    'auth url missing',
                    'not configured',
                    'unable to verify status',
                    'failed to initiate payment'
                ]
                if (transient.some(t => msg.includes(t))) {
                    console.warn('Transient backend MPESA issue, continuing to poll:', result.message)
                    // don't set error yet
                    if (attempts >= maxAttempts) {
                        clearInterval(interval)
                        paymentError.value = result.message || 'Could not verify payment'
                        processingMpesa.value = false
                        if (popup && !popup.closed) try { popup.close() } catch {}
                    }
                    return
                }

                if (attempts >= maxAttempts) {
                    clearInterval(interval)
                    paymentError.value = 'Cannot verify payment status. Please check your M-Pesa messages.'
                    processingMpesa.value = false
                    if (popup && !popup.closed) try { popup.close() } catch {}
                }
                return
            }

            const resultCode = result.data?.ResultCode

            if (resultCode === '0') {
                // Success
                clearInterval(interval)
                console.log('✅ Payment successful!')

                const receipt = result.data.MpesaReceiptNumber || checkoutRequestId
                // Prevent duplicates
                // Use payments array if you want to keep transaction history; for subscription we set modal success
                paymentSuccessData.value = { receipt: receipt, amount: amount }
                paymentSuccess.value = true
                paymentError.value = null
                processingMpesa.value = false

                // Close popup with success message
                if (popup && !popup.closed) {
                    try {
                        const subId = result.data?.subscription_id || null
                        if (subId) {
                            popup.location.href = `/subscription/${subId}`
                        } else {
                            popup.document.body.innerHTML = '<p style="font-family:sans-serif;padding:16px;">Payment received. You can close this window.</p>'
                        }
                    } catch (e) { console.debug(e) }
                }

                // Allow backend to have activated subscription already (ProcessMpesaCallback does activation)
                // Wait a short moment then reload so page-level state reflects activation
                setTimeout(() => {
                    showPaymentModal.value = false
                    form.reset()
                    window.location.reload()
                }, 1500)

            } else if (resultCode && pendingCodes.includes(resultCode.toString())) {
                console.log(`Transaction still pending (code ${resultCode}), will check again...`)
                // keep polling, do not change processingMpesa

            } else if (resultCode) {
                // definitive failure
                clearInterval(interval)
                const errorDesc = result.data?.ResultDesc || 'Unknown error'
                const friendly = mpesaErrorCodes[resultCode] || errorDesc
                console.error('Payment failed with code:', resultCode, '-', friendly)

                if (cancelledCodes.includes(resultCode.toString())) {
                    if ((result.data?.ResultDesc || '').includes('unresolved reason')) {
                        paymentError.value = 'The payment could not be processed. Possible reasons include:\n• Phone number is not registered with M-Pesa\n• Network connectivity issues\n• Phone is switched off or out of coverage\n• M-Pesa account has restrictions\n\nPlease verify the phone number and try again or use a different payment method.'
                    } else {
                        paymentError.value = 'Payment cancelled by customer'
                    }
                } else if (resultCode === '1') {
                    paymentError.value = 'Insufficient Balance'
                } else if (resultCode === '2001') {
                    paymentError.value = 'Wrong PIN'
                } else if (resultCode === '2006') {
                    paymentError.value = 'PIN Blocked'
                } else if (resultCode === '2007') {
                    paymentError.value = 'Request Timed Out'
                } else {
                    paymentError.value = `M-Pesa Payment Failed: ${friendly}`
                }

                processingMpesa.value = false
                if (popup && !popup.closed) try { popup.close() } catch {}
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval)
                paymentError.value = 'Payment verification timed out. Please check your M-Pesa messages.'
                processingMpesa.value = false
                if (popup && !popup.closed) try { popup.close() } catch {}
            }

        } catch (err) {
            console.error('Status check error:', err)
            if (attempts >= maxAttempts) {
                clearInterval(interval)
                paymentError.value = 'Network error during verification. Please check your M-Pesa messages.'
                processingMpesa.value = false
                if (popup && !popup.closed) try { popup.close() } catch {}
            }
        }
    }, 3000)

    // store interval so it can be cleared elsewhere if needed
    pollingIntervals.set(checkoutRequestId, interval)
}


const submitPayment = () => {
    form.billing_cycle = billingCycle.value

    if (form.payment_method === 'stk') {
        initiateStkPush()
        return
    }

    form.submit('post', '/subscription/pay', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showPaymentModal.value = false
            form.reset()
            // Reload to show updated subscription status
            setTimeout(() => window.location.reload(), 1000)
        },
        onError: (errors) => {
            console.error('Payment submission failed:', errors)
            // Error messages are automatically bound to form.errors
        }
    })
}

const isCurrentPlan = (planId: number) => {
    return props.business?.plan_id === planId && !!props.currentSubscription
}
</script>

<template>
    <Head title="Subscription Management" />

    <AppLayout>
        <div class="py-10 space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-700 w-full mx-auto">

            <!-- Minimalist Centered Header -->
            <div class="text-center space-y-4 max-w-2xl mx-auto px-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100 shadow-sm">
                    <Sparkles class="size-3.5" />
                    <span class="text-[10px] font-black uppercase tracking-widest leading-none">Subscription Workspace</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    Scale Your Operations with <span class="bg-gradient-to-r from-indigo-600 to-indigo-400 bg-clip-text text-transparent">Professional Power</span>
                </h1>
                <p class="text-slate-500 font-medium text-lg leading-relaxed">
                    Flexible plans designed to grow with your business. Select a tier to unlock advanced features.
                </p>

                <!-- Professional Billing Switch -->
                <div class="pt-6">
                    <div class="inline-flex items-center p-1.5 bg-slate-100 rounded-2xl border border-slate-200/60 shadow-inner">
                        <button
                            @click="billingCycle = 'monthly'"
                            :class="[
                                'px-8 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-[0.1em] transition-all duration-300',
                                billingCycle === 'monthly' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                            ]"
                        >
                            Monthly
                        </button>
                        <button
                            @click="billingCycle = 'yearly'"
                            :class="[
                                'px-8 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-[0.1em] transition-all duration-300 flex items-center gap-2',
                                billingCycle === 'yearly' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                            ]"
                        >
                            Yearly
                            <span class="bg-emerald-500 text-white text-[8px] px-1.5 py-0.5 rounded-full font-bold">SAVE 15%</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Focused Content Area -->
            <div class="space-y-16 w-full mx-auto px-6">

                <!-- Pending Payment Status -->
                <div v-if="props.pendingSubscription" class="bg-amber-50 border border-amber-200/60 rounded-[2rem] p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden group">
                    <div class="absolute inset-x-0 top-0 h-1 bg-amber-400 animate-pulse"></div>
                    <div class="flex items-center gap-6 relative z-10 w-full md:w-auto">
                        <div class="p-5 bg-amber-100 rounded-[1.5rem] text-amber-600 shadow-sm group-hover:rotate-3 transition-transform">
                            <Loader2 class="size-10 animate-spin" />
                        </div>
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-amber-200/40 text-amber-700 rounded-md border border-amber-300/30 text-[10px] font-bold uppercase tracking-widest mb-1">
                                Awaiting Verification
                            </div>
                            <h3 class="text-2xl font-black text-slate-900">{{ props.pendingSubscription.plan_name }} Tier Payment</h3>
                            <p class="text-slate-500 text-sm font-medium">
                                We are verifying your <span class="text-slate-900 font-black uppercase">{{ props.pendingSubscription.payment_method }}</span> transaction.
                            </p>
                            <p class="text-slate-500 text-sm font-medium mt-2">
                                <span class="text-[11px] font-black uppercase text-slate-400">M-Pesa Receipt:</span>
                                <span v-if="props.pendingSubscription.mpesa_receipt || props.pendingSubscription.transaction_id" class="text-green-700 font-bold ml-2">{{ props.pendingSubscription.mpesa_receipt || props.pendingSubscription.transaction_id }}</span>
                                <span v-else class="text-gray-400 ml-2">N/A</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Active Membership Status -->
                <div v-if="props.currentSubscription" class="bg-white border border-slate-200/60 rounded-[2rem] p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden group">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-indigo-400 to-indigo-500"></div>

                    <div class="flex items-center gap-6 relative z-10 w-full md:w-auto">
                        <div class="p-5 bg-indigo-50 rounded-[1.5rem] text-indigo-600 shadow-sm group-hover:scale-105 transition-transform">
                            <ShieldCheck class="size-10" />
                        </div>
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-md border border-emerald-100/50 text-[10px] font-bold uppercase tracking-widest mb-1">
                                <span class="size-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                Currently Active
                            </div>
                            <h3 class="text-2xl font-black text-slate-900">{{ props.currentSubscription.plan_name }} Tier</h3>
                            <p class="text-slate-500 text-sm font-medium flex items-center gap-2">
                                Next billing on <span class="text-slate-900 font-bold decoration-indigo-200 decoration-2 underline-offset-4 underline">{{ new Date(props.currentSubscription.ends_at).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' }) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto border-t md:border-t-0 md:border-l border-slate-100 pt-6 md:pt-0 md:pl-12 relative z-10">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Payment Method</span>
                            <div class="flex items-center gap-2 text-slate-700">
                                <CreditCard class="size-4 text-emerald-500" />
                                <span class="text-sm font-black uppercase tracking-tight">{{ props.currentSubscription.payment_method || 'M-PESA WALLET' }}</span>
                            </div>
                        </div>
                        <Button variant="outline" class="ml-auto md:ml-8 h-11 rounded-xl border-slate-200 hover:bg-slate-50 font-bold text-xs uppercase tracking-widest gap-2">
                            Billing History
                            <ArrowUpRight class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Balanced Pricing Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <Card
                        v-for="plan in props.plans"
                        :key="plan.id"
                        :class="[
                            'flex flex-col border-none shadow-sm rounded-[2.5rem] transition-all duration-500 relative group overflow-hidden',
                            isCurrentPlan(plan.id)
                                ? 'bg-slate-900 text-white ring-4 ring-indigo-500/10'
                                : 'bg-white hover:shadow-xl hover:-translate-y-2'
                        ]"
                    >
                        <!-- Dynamic Background Accents -->
                        <div v-if="plan.size_category === 'Medium' || plan.size_category === 'Large'" class="absolute -top-12 -right-12 size-32 bg-indigo-500/10 rounded-full blur-3xl opacity-50 group-hover:opacity-100 transition-opacity"></div>

                        <CardHeader class="pt-10 pb-4 px-8 space-y-6 text-center">
                            <div v-if="plan.size_category === 'Medium'" class="absolute top-6 right-8">
                                <Badge class="bg-indigo-600 text-white border-none text-[8px] font-black uppercase tracking-[0.2em] px-3 py-1 rounded-full shadow-lg h-6">Best Value</Badge>
                            </div>

                            <div class="inline-flex p-4 rounded-3xl mx-auto transition-transform duration-500 group-hover:rotate-6 shadow-inner" :class="isCurrentPlan(plan.id) ? 'bg-white/10' : 'bg-slate-50 text-indigo-600'">
                                <component :is="plan.size_category === 'Enterprise' ? Crown : (plan.size_category === 'Large' || plan.size_category === 'Medium' ? Zap : Building2)" class="size-8" />
                            </div>

                            <div class="space-y-1">
                                <CardTitle class="text-2xl font-black uppercase tracking-tighter" :class="isCurrentPlan(plan.id) ? 'text-white' : 'text-slate-900'">{{ plan.name }}</CardTitle>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="isCurrentPlan(plan.id) ? 'text-indigo-400' : 'text-slate-400'">{{ plan.size_category }} Business Entity</p>
                            </div>

                            <div class="flex items-baseline justify-center gap-1">
                                <span class="text-sm font-bold opacity-60 mr-1">KSh</span>
                                <span class="text-5xl font-black tracking-tighter">
                                    {{ billingCycle === 'monthly' ? Number(plan.price_monthly).toLocaleString() : Number(plan.price_yearly).toLocaleString() }}
                                </span>
                                <span class="text-xs font-bold opacity-40 uppercase tracking-widest leading-none">/{{ billingCycle === 'monthly' ? 'mo' : 'yr' }}</span>
                            </div>
                        </CardHeader>

                        <CardContent class="flex-1 px-10 pb-8 space-y-8">
                            <div class="h-px w-full" :class="isCurrentPlan(plan.id) ? 'bg-white/10' : 'bg-slate-100'"></div>

                            <div class="space-y-6">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3 group/item">
                                        <div class="p-1 rounded-full" :class="isCurrentPlan(plan.id) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600'">
                                            <Check class="size-3 font-bold" />
                                        </div>
                                        <span class="text-sm font-bold tracking-tight" :class="isCurrentPlan(plan.id) ? 'text-white/80' : 'text-slate-700'">
                                            {{ plan.max_users || 'Unlimited' }} <span class="opacity-50 font-medium ml-1">Staff Users</span>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 group/item">
                                        <div class="p-1 rounded-full" :class="isCurrentPlan(plan.id) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600'">
                                            <Check class="size-3 font-bold" />
                                        </div>
                                        <span class="text-sm font-bold tracking-tight" :class="isCurrentPlan(plan.id) ? 'text-white/80' : 'text-slate-700'">
                                            {{ plan.max_employees || 'Unlimited' }} <span class="opacity-50 font-medium ml-1">Total Employees</span>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 group/item">
                                        <div class="p-1 rounded-full" :class="isCurrentPlan(plan.id) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600'">
                                            <Check class="size-3 font-bold" />
                                        </div>
                                        <span class="text-sm font-bold tracking-tight" :class="isCurrentPlan(plan.id) ? 'text-white/80' : 'text-slate-700'">
                                            {{ plan.max_products || 'Unlimited' }} <span class="opacity-50 font-medium ml-1">Inventory Slots</span>
                                        </span>
                                    </div>
                                    <div v-for="feature in plan.features" :key="feature.id" class="flex items-center gap-3">
                                        <div class="p-1 rounded-full" :class="isCurrentPlan(plan.id) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600'">
                                            <Check class="size-3 font-bold" />
                                        </div>
                                        <span class="text-sm font-bold tracking-tight" :class="isCurrentPlan(plan.id) ? 'text-white/80' : 'text-slate-700'">{{ feature.name }} Access</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>

                        <CardFooter class="px-8 pb-10">
                            <Button
                                :disabled="isCurrentPlan(plan.id) || !!props.pendingSubscription"
                                @click="openPaymentModal(plan)"
                                :class="[
                                    'w-full h-14 rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] transition-all duration-300 shadow-lg',
                                    isCurrentPlan(plan.id) || !!props.pendingSubscription
                                        ? 'bg-transparent border-2 border-white/20 text-white/50 cursor-not-allowed shadow-none'
                                        : (plan.size_category === 'Medium' || plan.size_category === 'Large'
                                            ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-200'
                                            : 'bg-slate-900 hover:bg-slate-800 text-white')
                                ]"
                            >
                                {{ isCurrentPlan(plan.id) ? 'Currently Using' : (props.pendingSubscription ? 'Pending Review' : 'Select ' + plan.name) }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Sleek Minimalist Footer Section -->
                <div class="pt-8 border-t border-slate-100 flex flex-col md:flex-row items-center justify-center gap-12 text-slate-400">
                    <div class="flex items-center gap-2 group transition-colors hover:text-slate-600">
                        <CreditCard class="size-5" />
                        <span class="text-[10px] font-black uppercase tracking-widest">M-PESA Integrated</span>
                    </div>
                    <div class="flex items-center gap-2 group transition-colors hover:text-slate-600">
                        <ShieldAlert class="size-5" />
                        <span class="text-[10px] font-black uppercase tracking-widest">Policy Gated Access</span>
                    </div>
                    <div class="flex items-center gap-2 group transition-colors hover:text-slate-600">
                        <Info class="size-5" />
                        <span class="text-[10px] font-black uppercase tracking-widest">Real-time Activation</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Premium Centered Dialog -->
        <Dialog v-model:open="showPaymentModal">
            <DialogContent class="sm:max-w-[500px] p-0 border-none shadow-3xl bg-white overflow-hidden rounded-[2.5rem] animate-in zoom-in-95 duration-300">
                <!-- Multi-state modal: form (default) / processing / success / error -->
                <!-- Processing State -->
                <div v-if="processingMpesa && !paymentSuccess && !paymentError" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="relative mb-6">
                        <div class="relative">
                            <Loader2 class="h-20 w-20 text-indigo-600 animate-spin" />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <Smartphone class="h-8 w-8 text-indigo-400" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Processing Payment</h3>
                    <p class="text-center text-gray-600 mb-4">Please wait while we process your M-Pesa payment... The prompt should appear on your phone.</p>

                    <div class="bg-indigo-50 border-2 border-indigo-200 rounded-lg p-4 w-full max-w-sm">
                        <p class="text-sm text-indigo-800 text-center font-medium">📱 Check your phone to confirm the payment prompt</p>
                    </div>
                </div>

                <!-- Success State -->
                <div v-else-if="paymentSuccess" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="mb-6 relative">
                        <div class="relative">
                            <div class="absolute inset-0 bg-green-100 rounded-full animate-ping opacity-75"></div>
                            <div class="relative bg-gradient-to-br from-green-500 to-emerald-600 rounded-full p-4">
                                <CheckCircle2 class="h-16 w-16 text-white" stroke-width="2.5" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-green-800 mb-2">Payment Successful!</h3>
                    <p class="text-center text-gray-600 mb-6">Your M-Pesa payment has been processed successfully.</p>

                    <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4 w-full max-w-sm space-y-2 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700">Receipt Number:</span>
                            <span class="font-mono font-bold text-green-900">{{ paymentSuccessData?.receipt }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700">Amount Paid:</span>
                            <span class="font-bold text-green-900">{{ paymentSuccessData?.amount?.toLocaleString() || (billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString()) }}</span>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 text-center mb-6">Your subscription will be activated automatically...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="paymentError" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="mb-6 relative">
                        <div class="relative">
                            <div class="absolute inset-0 bg-red-100 rounded-full animate-pulse"></div>
                            <div class="relative bg-gradient-to-br from-red-500 to-rose-600 rounded-full p-4">
                                <XCircle class="h-16 w-16 text-white" stroke-width="2.5" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-red-800 mb-2">Payment Failed</h3>

                    <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4 w-full max-w-sm mb-6">
                        <p class="text-sm text-red-800 text-center whitespace-pre-line">{{ paymentError }}</p>
                    </div>

                    <div class="flex gap-3 w-full max-w-sm">
                        <Button @click="showPaymentModal = false" variant="outline" class="flex-1 h-12 border-2 border-red-300 text-red-700 hover:bg-red-50">Close</Button>
                        <Button @click="resetProcessing" class="flex-1 h-12 bg-gradient-to-r from-purple-600 to-indigo-600">Try Again</Button>
                    </div>
                </div>

                <!-- Default: Form State -->
                <div v-else>
                    <form @submit.prevent="submitPayment" class="max-h-[90vh] overflow-y-auto custom-scrollbar p-10 space-y-8">
                        <div class="text-center space-y-3">
                            <div class="p-4 bg-indigo-50 rounded-[1.5rem] inline-flex items-center justify-center mb-2">
                                <Smartphone class="size-10 text-indigo-600" />
                            </div>
                            <DialogTitle class="text-3xl font-black text-slate-900 tracking-tight leading-none">Choose Payment Way</DialogTitle>
                            <DialogDescription class="text-slate-500 font-medium">Complete payment for <strong class="text-indigo-600 uppercase">{{ selectedPlan?.name }}</strong> Tier.</DialogDescription>
                        </div>

                        <div class="space-y-6">
                            <div class="flex items-center justify-between p-6 rounded-[1.5rem] bg-slate-900 text-white shadow-xl shadow-slate-200">
                                <div class="space-y-1">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Total Commitment</span>
                                    <div class="text-2xl font-black tracking-tight">
                                        <span class="text-xs mr-1">KES</span>
                                        {{ billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString() }}
                                    </div>
                                </div>
                                <Badge class="bg-indigo-600 text-white border-none px-3 uppercase text-[9px] font-black tracking-widest">{{ billingCycle }}</Badge>
                            </div>

                            <!-- High Fidelity Payment Method Toggle -->
                            <div class="p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/50 flex items-center gap-1.5 shadow-inner">
                                <button
                                    type="button"
                                    @click="form.payment_method = 'stk'"
                                    :class="[
                                        'flex-1 flex items-center justify-center gap-2 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                                        form.payment_method === 'stk' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                                    ]"
                                >
                                    <Smartphone class="size-3.5" />
                                    STK Push
                                </button>
                                <button
                                    type="button"
                                    @click="form.payment_method = 'till'"
                                    :class="[
                                        'flex-1 flex items-center justify-center gap-2 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                                        form.payment_method === 'till' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                                    ]"
                                >
                                    <Store class="size-3.5" />
                                    Till Number
                                </button>
                                <button
                                    type="button"
                                    @click="form.payment_method = 'card'"
                                    :class="[
                                        'flex-1 flex items-center justify-center gap-2 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                                        form.payment_method === 'card' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                                    ]"
                                >
                                    <CreditCard class="size-3.5" />
                                    Card Payment
                                </button>
                                <button
                                    type="button"
                                    @click="form.payment_method = 'bank_transfer'"
                                    :class="[
                                        'flex-1 flex items-center justify-center gap-2 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                                        form.payment_method === 'bank_transfer' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                                    ]"
                                >
                                    <Building2 class="size-3.5" />
                                    Bank Transfer
                                </button>
                                <button
                                    type="button"
                                    @click="form.payment_method = 'cash'"
                                    :class="[
                                        'flex-1 flex items-center justify-center gap-2 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                                        form.payment_method === 'cash' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/50' : 'text-slate-400 hover:text-slate-600'
                                    ]"
                                >
                                    <DollarSign class="size-3.5" />
                                    Cash Payment
                                </button>
                            </div>

                            <!-- Dynamic Content Panes -->
                            <div class="space-y-6 pt-2">
                                <!-- STK Content -->
                                <div v-if="form.payment_method === 'stk'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="space-y-3">
                                        <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Phone Number</Label>
                                        <div class="relative group">
                                            <Smartphone class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                                            <Input
                                                v-model="form.phone_number"
                                                placeholder="07XXXXXXXX"
                                                class="h-14 pl-12 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                            />
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium italic">We'll send a prompt to your M-PESA phone instantly.</p>
                                    </div>
                                </div>

                                <!-- Till Content -->
                                <div v-if="form.payment_method === 'till'" class="space-y-6 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="p-8 bg-emerald-50 rounded-[2.5rem] border-2 border-emerald-100 flex flex-col items-center text-center space-y-4 relative overflow-hidden shadow-sm">
                                        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-100/40 rounded-full -mr-16 -mt-16"></div>
                                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-emerald-100/30 rounded-full -ml-12 -mb-12"></div>

                                        <div class="flex flex-col items-center space-y-1 relative z-10">
                                            <span class="text-[10px] font-black uppercase text-emerald-600/70 block tracking-[0.3em]">Exact Amount to Pay</span>
                                            <div class="text-4xl font-black text-emerald-900 tracking-tight">
                                                <span class="text-sm mr-1">KES</span>
                                                {{ billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString() }}
                                            </div>
                                        </div>

                                        <div class="w-full h-px bg-emerald-200/50 relative z-10"></div>

                                        <div class="space-y-1 relative z-10">
                                            <span class="text-[10px] font-black uppercase text-emerald-600/70 block tracking-[0.3em]">Official Lipa Na M-Pesa Till</span>
                                            <div class="flex flex-col">
                                                <span class="text-5xl font-black text-emerald-700 tracking-tighter tabular-nums selection:bg-emerald-200">4084618</span>
                                                <span class="text-xs font-bold text-emerald-600/60 uppercase tracking-widest mt-1">Vocal Hub Limited</span>
                                            </div>
                                        </div>

                                        <div class="pt-2 relative z-10">
                                            <Badge variant="outline" class="bg-white text-emerald-700 border-emerald-100 px-4 py-1.5 font-black uppercase text-[9px] tracking-[0.2em] rounded-full shadow-sm">
                                                Verified Receiving Account
                                            </Badge>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="bg-indigo-50/40 p-5 rounded-[1.5rem] border border-indigo-100/50 space-y-3">
                                            <div class="flex items-center gap-2 text-indigo-700">
                                                <div class="p-1 bg-indigo-100 rounded-lg">
                                                    <Info class="size-3.5" />
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-widest">Payment Verification Steps</span>
                                            </div>
                                            <ol class="text-[11px] text-slate-600 font-medium space-y-2 ml-4 list-decimal marker:font-black marker:text-indigo-400">
                                                <li>Dial <span class="text-slate-900 font-bold">*334#</span> or use M-PESA App</li>
                                                <li>Select <strong>Lipa Na M-PESA</strong> > <strong>Buy Goods</strong></li>
                                                <li>Enter Till Number: <strong class="text-slate-900 text-xs">4084618</strong></li>
                                                <li>Authorize <strong>KES {{ (billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString()) }}</strong></li>
                                            </ol>
                                        </div>

                                        <div class="space-y-2.5 bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">M-PESA Confirmation Code</Label>
                                            <div class="relative group">
                                                <CheckCircle2 class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-300 group-focus-within:text-emerald-500 transition-colors" />
                                                <Input
                                                    v-model="form.transaction_code"
                                                    placeholder="SAB123XXXX"
                                                    class="h-14 pl-12 rounded-xl border-slate-200 focus-visible:ring-emerald-500 bg-slate-50/30 font-black text-slate-900 text-lg uppercase selection:bg-emerald-100"
                                                />
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-medium italic text-center px-4">Submit the 10-character code from your M-PESA payment confirmation.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Payment Content -->
                                <div v-if="form.payment_method === 'card'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="space-y-3">
                                        <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Card Details</Label>
                                        <div class="grid grid-cols-1 gap-4">
                                            <div class="grid grid-cols-2 gap-4">
                                                <Input
                                                    v-model="form.card_number"
                                                    placeholder="Card Number"
                                                    class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium col-span-2"
                                                />
                                                <div class="grid grid-cols-2 gap-4">
                                                    <Input
                                                        v-model="form.expiry_month"
                                                        placeholder="MM"
                                                        class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                                    />
                                                    <Input
                                                        v-model="form.expiry_year"
                                                        placeholder="YY"
                                                        class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                                    />
                                                </div>
                                                <Input
                                                    v-model="form.cvv"
                                                    placeholder="CVV"
                                                    class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Cardholder Name</Label>
                                            <Input
                                                v-model="form.cardholder_name"
                                                placeholder="John Doe"
                                                class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="bg-indigo-50/40 p-5 rounded-[1.5rem] border border-indigo-100/50 space-y-3">
                                            <div class="flex items-center gap-2 text-indigo-700">
                                                <div class="p-1 bg-indigo-100 rounded-lg">
                                                    <Info class="size-3.5" />
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-widest">Secure Payment Processing</span>
                                            </div>
                                            <p class="text-[11px] text-slate-600 font-medium leading-relaxed">
                                                Your card details are securely transmitted and processed. We do not store your card information.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Transfer Content -->
                                <div v-if="form.payment_method === 'bank_transfer'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="space-y-3">
                                        <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Bank Transfer Details</Label>
                                        <div class="grid grid-cols-1 gap-4">
                                            <Input
                                                v-model="form.bank_name"
                                                placeholder="Bank Name"
                                                class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                            />
                                            <Input
                                                v-model="form.account_number"
                                                placeholder="Account Number"
                                                class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                            />
                                            <Input
                                                v-model="form.reference_number"
                                                placeholder="Reference Number"
                                                class="h-14 rounded-xl border-slate-200 focus-visible:ring-indigo-600 bg-slate-50/50 font-black text-slate-900 text-lg placeholder:font-medium"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="bg-indigo-50/40 p-5 rounded-[1.5rem] border border-indigo-100/50 space-y-3">
                                            <div class="flex items-center gap-2 text-indigo-700">
                                                <div class="p-1 bg-indigo-100 rounded-lg">
                                                    <Info class="size-3.5" />
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-widest">Payment Instructions</span>
                                            </div>
                                            <p class="text-[11px] text-slate-600 font-medium leading-relaxed">
                                                Please transfer the exact amount to the provided bank account. Use your phone number as the reference.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash Payment Content -->
                                <div v-if="form.payment_method === 'cash'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="space-y-3">
                                        <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Cash Payment Instructions</Label>
                                        <div class="p-4 bg-emerald-50 rounded-[1.5rem] border-2 border-emerald-100 flex flex-col items-center text-center space-y-4 relative overflow-hidden shadow-sm">
                                            <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-100/40 rounded-full -mr-16 -mt-16"></div>
                                            <div class="absolute bottom-0 left-0 w-24 h-24 bg-emerald-100/30 rounded-full -ml-12 -mb-12"></div>

                                            <div class="flex flex-col items-center space-y-1 relative z-10">
                                                <span class="text-[10px] font-black uppercase text-emerald-600/70 block tracking-[0.3em]">Exact Amount to Pay</span>
                                                <div class="text-4xl font-black text-emerald-900 tracking-tight">
                                                    <span class="text-sm mr-1">KES</span>
                                                    {{ billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString() }}
                                                </div>
                                            </div>

                                            <div class="w-full h-px bg-emerald-200/50 relative z-10"></div>

                                            <div class="space-y-1 relative z-10">
                                                <span class="text-[10px] font-black uppercase text-emerald-600/70 block tracking-[0.3em]">Payment Location</span>
                                                <div class="flex flex-col">
                                                    <span class="text-lg font-black text-emerald-700 tracking-tight">Vocal Hub Office</span>
                                                    <span class="text-xs font-bold text-emerald-600/60 uppercase tracking-widest mt-1">Nairobi, Kenya</span>
                                                </div>
                                            </div>

                                            <div class="pt-2 relative z-10">
                                                <Badge variant="outline" class="bg-white text-emerald-700 border-emerald-100 px-4 py-1.5 font-black uppercase text-[9px] tracking-[0.2em] rounded-full shadow-sm">
                                                    Verified Payment Location
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2.5 bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                                        <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Amount Received</Label>
                                        <Input
                                            v-model.number="form.received_amount"
                                            type="number"
                                            step="0.01"
                                            class="h-14 rounded-xl border-slate-200 focus-visible:ring-emerald-500 bg-slate-50/30 font-black text-slate-900 text-lg"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-50 rounded-2xl border border-red-100 space-y-1">
                                <div v-for="(error, field) in form.errors" :key="field" class="text-[10px] text-red-600 font-black flex items-center gap-1.5 uppercase">
                                    <ShieldAlert class="size-3" />
                                    {{ error }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full h-16 rounded-[1.5rem] bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-[0.2em] text-[10px] shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-3 disabled:bg-slate-100 disabled:text-slate-400 cursor-pointer active:scale-[0.98]"
                            >
                                <Loader2 v-if="form.processing" class="animate-spin size-4" />
                                <span>{{ form.processing ? 'Establishing Gateway...' : (form.payment_method === 'stk' ? 'Prompt My Phone' : 'Confirm KES ' + (billingCycle === 'monthly' ? Number(selectedPlan?.price_monthly).toLocaleString() : Number(selectedPlan?.price_yearly).toLocaleString()) + ' Paid') }}</span>
                            </button>

                            <p class="text-[9px] text-slate-400 text-center font-bold uppercase tracking-widest px-4 leading-relaxed">
                                {{ form.payment_method === 'stk' ? 'Prompt will appear on your phone instantly.' : 'By clicking "Confirm", you verify that you have successfully sent the payment to the till number above.' }}
                            </p>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.shadow-3xl {
    box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.25);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
