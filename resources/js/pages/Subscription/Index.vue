<script setup lang="ts">
/* eslint-disable import/order */
import { ref, onUnmounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

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
    Store
} from 'lucide-vue-next'

import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import axios from 'axios'

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
    mpesa_receipt?: string
}>({
    plan_id: '',
    phone_number: '',
    billing_cycle: 'monthly',
    payment_method: 'stk',
    transaction_code: '',
    mpesa_receipt: undefined
})

// --- Payment state ---
const processingMpesa = ref(false)
const paymentSuccess = ref(false)
const paymentError = ref<string | null>(null)
const paymentSuccessData = ref<{ receipt?: string; amount?: number } | null>(null)

// --- Monitoring state ---
const sseConnections = new Map<string, EventSource>()
const pollingIntervals = new Map<string, number>()
const processedCheckouts = new Set<string>()
const activeMonitorings = new Set<string>()

// --- Cleanup on unmount ---
onUnmounted(() => {
    // Close all SSE connections
    sseConnections.forEach((sse) => {
        try { sse.close() } catch { }
    })
    sseConnections.clear()

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

// Initiate STK Push using Inertia's router for proper CSRF handling
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

    // Use axios to POST and receive plain JSON (avoid X-Inertia header which expects Inertia responses)
    try {
        const resp = await axios.post('/subscription/pay', {
            plan_id: selectedPlan.value?.id,
            phone_number: phone,
            billing_cycle: billingCycle.value,
            payment_method: 'stk'
        }, { withCredentials: true, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' } })

        const data = resp.data || {}
        const checkoutId = data.checkout_request_id || data.data?.checkout_request_id || data.data?.CheckoutRequestID || data.CheckoutRequestID || data.checkoutId || data.checkoutID
        const sseToken = data.data?.sse_token || data.sse_token || null

        if (!checkoutId) {
            console.error('No checkout ID found in response:', data)
            paymentError.value = 'Failed to obtain checkout request ID from server response.'
            processingMpesa.value = false
            return
        }

        // We got a checkout ID from the server — STK push was initiated.
        // Stop the busy spinner (prompt will appear on user's phone). Polling/SSE will continue in background.
        processingMpesa.value = false
        startPaymentMonitoring(checkoutId, amount, sseToken)
    } catch (err: unknown) {
        console.error('STK push error (axios):', err)
        let msg = 'Failed to initiate STK push.'
        if (err && typeof err === 'object') {
            // @ts-expect-error - narrowing axios error object for message extraction
            msg = err?.response?.data?.message || err?.response?.data?.error || (err as any)?.message || msg
        } else if (typeof err === 'string') {
            msg = err
        }
        paymentError.value = String(msg)
        processingMpesa.value = false
    }
}

const startPaymentMonitoring = (checkoutRequestId: string, amount: number, sseToken: string | null = null) => {
    if (processedCheckouts.has(checkoutRequestId) || activeMonitorings.has(checkoutRequestId)) {
        return
    }

    activeMonitorings.add(checkoutRequestId)
    startSSEConnection(checkoutRequestId, amount, sseToken)
    startPolling(checkoutRequestId, amount, sseToken)
}

const startSSEConnection = (checkoutRequestId: string, amount: number, sseToken: string | null = null) => {
    try {
        if (sseConnections.has(checkoutRequestId)) return

        // Build SSE URL including the short-lived server token when available
        let sseUrl = `/api/payments/mpesa/stream?checkoutRequestID=${encodeURIComponent(checkoutRequestId)}&_=${Date.now()}`
        if (sseToken) sseUrl += `&token=${encodeURIComponent(sseToken)}`

        const sse = new EventSource(sseUrl)
        sseConnections.set(checkoutRequestId, sse)

        sse.onmessage = (ev) => {
            try {
                const payload = JSON.parse(ev.data)
                handlePaymentUpdate(payload, checkoutRequestId, amount)
            } catch (e) {
                console.error('Error parsing SSE data:', e)
            }
        }

        sse.onerror = (error) => {
            console.error('SSE connection error:', error)
            // Close SSE connection and remove it; fall back to polling (which is running).
            try { sse.close() } catch { }
            sseConnections.delete(checkoutRequestId)
            // Stop the active "processing" spinner because the STK was already sent; we are now waiting for confirmation.
            processingMpesa.value = false
            // Keep a user-friendly notice in paymentError to show status — will be overwritten on real updates.
            paymentError.value = 'Real-time updates unavailable; verifying payment via background check.'
            setTimeout(() => { paymentError.value = null }, 5000)
        }

        // Close connection after 5 minutes
        setTimeout(() => {
            if (sseConnections.has(checkoutRequestId)) {
                try { sse.close() } catch { }
                sseConnections.delete(checkoutRequestId)
            }
        }, 300000) // 5 minutes

    } catch (e) {
        console.error('Failed to open SSE connection:', e)
    }
}

const startPolling = (checkoutRequestId: string, amount: number, sseToken: string | null = null) => {
    if (pollingIntervals.has(checkoutRequestId)) return

    let attempts = 0
    const maxAttempts = 40 // ~2 minutes at 3-second intervals

    const pollStatus = async () => {
        if (processedCheckouts.has(checkoutRequestId) || !activeMonitorings.has(checkoutRequestId)) {
            return
        }

        attempts++

        try {
            // Use fetch with credentials for automatic cookie sending
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            const headers: Record<string,string> = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            }
            if (sseToken) headers['X-SSE-TOKEN'] = sseToken

            const endpoint = sseToken ? '/api/payments/mpesa/check-status-token' : '/api/payments/mpesa/check-status'
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: headers,
                // use include so cookies (session) are sent even on cross-origin (ngrok) tests
                credentials: 'include',
                body: JSON.stringify({ checkout_request_id: checkoutRequestId })
            })

            if (response.ok) {
                // We have a response from the server; stop the active spinner (we're now verifying)
                processingMpesa.value = false
                const data = await response.json()
                handlePaymentUpdate(data, checkoutRequestId, amount)
            } else if (attempts >= maxAttempts) {
                stopPolling(checkoutRequestId)
                if (!processedCheckouts.has(checkoutRequestId)) {
                    processingMpesa.value = false
                    paymentError.value = 'Payment verification timed out.'
                }
            }
        } catch (error) {
            console.error('Polling error:', error)
            if (attempts >= maxAttempts) {
                stopPolling(checkoutRequestId)
                if (!processedCheckouts.has(checkoutRequestId)) {
                    processingMpesa.value = false
                    paymentError.value = 'Network error during verification.'
                }
            }
        }
    }

    // Initial immediate check
    pollStatus()

    // Start interval polling
    const intervalId = setInterval(pollStatus, 3000)
    pollingIntervals.set(checkoutRequestId, intervalId)

    // Auto-stop after max attempts
    setTimeout(() => {
        stopPolling(checkoutRequestId)
    }, maxAttempts * 3000)
}

const stopPolling = (checkoutRequestId?: string) => {
    if (checkoutRequestId) {
        const id = pollingIntervals.get(checkoutRequestId)
        if (id) {
            clearInterval(id)
            pollingIntervals.delete(checkoutRequestId)
        }
        activeMonitorings.delete(checkoutRequestId)
        return
    }

    pollingIntervals.forEach((id, key) => {
        clearInterval(id)
        activeMonitorings.delete(key)
    })
    pollingIntervals.clear()
}

const closeSSE = (checkoutRequestId?: string) => {
    if (checkoutRequestId) {
        const sse = sseConnections.get(checkoutRequestId)
        if (sse) {
            try { sse.close() } catch { }
        }
        sseConnections.delete(checkoutRequestId)
        return
    }

    sseConnections.forEach((sse, key) => {
        try { sse.close() } catch { }
        sseConnections.delete(key)
    })
}

const handlePaymentUpdate = (data: any, checkoutRequestId: string, amount: number) => {
    if (processedCheckouts.has(checkoutRequestId)) return

    // Normalize the response data
    let resultCode: string | null = null
    let resultDesc: string | null = null
    let receipt: string | null = null

    // Extract data from different possible response structures
    if (data?.data?.ResultCode !== undefined) {
        resultCode = String(data.data.ResultCode)
        resultDesc = data.data.ResultDesc
        receipt = data.data.MpesaReceiptNumber
    } else if (data?.ResultCode !== undefined) {
        resultCode = String(data.ResultCode)
        resultDesc = data.ResultDesc
        receipt = data.MpesaReceiptNumber
    } else if (data?.status) {
        if (data.status === 'success') resultCode = '0'
        if (data.status === 'failed') resultCode = '1'
        receipt = data.receipt || data.mpesa_receipt
    }

    // Handle based on result code
    const pendingCodes = ['1037', '4999']
    const cancelledCodes = ['1032', '2029']

    if (resultCode === '0') {
        // Success
        processedCheckouts.add(checkoutRequestId)
        const normalized = {
            MpesaReceiptNumber: receipt || checkoutRequestId,
            Amount: data?.Amount || amount
        }
        handlePaymentSuccess(normalized, checkoutRequestId, amount)
        stopPolling(checkoutRequestId)
        closeSSE(checkoutRequestId)
        activeMonitorings.delete(checkoutRequestId)
    } else if (cancelledCodes.includes(resultCode || '')) {
        // Cancelled
        processedCheckouts.add(checkoutRequestId)
        handlePaymentFailure(resultDesc || 'Payment was cancelled by user', checkoutRequestId)
        stopPolling(checkoutRequestId)
        closeSSE(checkoutRequestId)
        activeMonitorings.delete(checkoutRequestId)
    } else if (pendingCodes.includes(resultCode || '')) {
        // Still pending - do nothing, keep monitoring
        console.log('Payment pending:', resultDesc)
    } else if (resultCode && !pendingCodes.includes(resultCode)) {
        // Other failure
        processedCheckouts.add(checkoutRequestId)
        handlePaymentFailure(resultDesc || 'Payment failed', checkoutRequestId)
        stopPolling(checkoutRequestId)
        closeSSE(checkoutRequestId)
        activeMonitorings.delete(checkoutRequestId)
    }
}

const handlePaymentSuccess = (data: any, checkoutRequestId: string, amount: number) => {
    if (paymentSuccess.value) return // Prevent duplicate handling

    const receipt = data.receipt || data.MpesaReceiptNumber || data.mpesa_receipt || checkoutRequestId

    paymentSuccessData.value = {
        receipt,
        amount: data.Amount || amount
    }
    paymentSuccess.value = true
    processingMpesa.value = false
    paymentError.value = null

    // Show success for 3 seconds, then reload
    setTimeout(() => {
        showPaymentModal.value = false
        form.reset()
        paymentSuccess.value = false
        paymentSuccessData.value = null
        window.location.reload()
    }, 3000)
}

const handlePaymentFailure = (errorMessage: string, checkoutRequestId: string) => {
    if (processedCheckouts.has(checkoutRequestId)) return

    processingMpesa.value = false
    paymentError.value = errorMessage
    activeMonitorings.delete(checkoutRequestId)
}

// Submit payment based on selected method
const submitPayment = () => {
    form.billing_cycle = billingCycle.value

    if (form.payment_method === 'stk') {
        initiateStkPush()
        return
    }

    // For till payments, use Inertia form submission
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
