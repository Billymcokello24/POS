<script setup lang="ts">
/* eslint-disable import/order */
/* Consolidated, cleaned script for Subscription Index page.
   - Delegates payment logic to SubscriptionPaymentModal.vue.
   - Manages plan selection and dashboard refresh on success.
   - Polling-first by default (USE_POLLING_ONLY = true) for dashboard status.
*/

import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3' // Keeping useForm if needed for other things, though likely obsolete
import AppLayout from '@/layouts/AppLayout.vue'
import SubscriptionPaymentModal from '@/components/Subscription/SubscriptionPaymentModal.vue'
import { postJsonWithSanctum } from '@/lib/sanctum'

// UI imports (include icons used in template)
import { Loader2, Smartphone, ShieldCheck, CheckCircle2, XCircle, Info, CreditCard, Sparkles, ShieldAlert, ArrowUpRight, Store, DollarSign, Check, Zap, Crown, Building2 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps<{
    plans: Array<any>
    currentSubscription: any
    pendingSubscription: any
    business: any
}>()

// Make props reactive
const currentSubscription = ref<any | null>(props.currentSubscription || null)
const pendingSubscriptionRef = ref<any | null>(props.pendingSubscription || null)
const businessRef = ref<any>({ ...(props.business || {}) })

// Update when props change (e.g., from Inertia visits)
watch(() => props.currentSubscription, (newVal) => {
    currentSubscription.value = newVal
}, { immediate: true })

watch(() => props.pendingSubscription, (newVal) => {
    pendingSubscriptionRef.value = newVal
}, { immediate: true })

watch(() => props.business, (newVal) => {
    businessRef.value = { ...newVal }
}, { immediate: true })

const showPaymentModal = ref(false)
const selectedPlan = ref<any | null>(null)

const billingCycle = ref<'monthly' | 'yearly'>('monthly')
const verifyLoading = ref(false)

// --- Payment flow helpers ---
const openPaymentModal = (plan: any) => {
    selectedPlan.value = plan
    showPaymentModal.value = true
}

const handlePaymentSuccess = () => {
    // Refresh subscription status immediately
    fetchSubscriptionStatus()
}


const verifyPending = async () => {
    if (!pendingSubscriptionRef.value) return
    const checkout = pendingSubscriptionRef.value.transaction_id || pendingSubscriptionRef.value.checkout_request_id
    if (!checkout) return

    verifyLoading.value = true
    try {
        await postJsonWithSanctum('/api/payments/mpesa/check-status', { checkout_request_id: checkout })
        await fetchSubscriptionStatus()
    } catch (e) {
        console.error('Manual verification failed', e)
    } finally {
        verifyLoading.value = false
    }
}

// Helper to find plan by name (for optimistic updates data matching)
const findPlanByName = (planName: string) => {
    return props.plans.find((p: any) => String(p.name) === String(planName))
}

// Polling registry so we can clear intervals on unmount
const pollingIntervals = new Map<string, number>()

// Choose behavior: polling-only (safe for PHP-FPM) or realtime-first (socket -> SSE -> polling)
const USE_POLLING_ONLY = true

// Realtime socket (optional)
let socket: any = null
let usingSocket = false

// Computed property to check if a plan is current
const isCurrentPlan = (planId: number) => {
    if (!currentSubscription.value) return false

    // First check if businessRef has matching plan_id
    if (businessRef.value?.plan_id === planId) return true

    // Fallback: try to match by plan name
    const currentPlan = findPlanByName(currentSubscription.value.plan_name)
    return currentPlan?.id === planId
}

// --- Realtime and polling helpers ---
const fetchSubscriptionStatus = async () => {
    try {
        if (typeof document !== 'undefined' && document.hidden) return
        const resp = await fetch(`/api/business/${props.business.id}/subscription-status`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        if (!resp.ok) {
            console.warn('Failed to fetch subscription status:', resp.status)
            return
        }
        const data = await resp.json()
        if (!data || !data.success) {
            console.warn('Invalid subscription status response:', data)
            return
        }

        console.log('Fetched subscription status:', data)

        currentSubscription.value = data.active ? {
            id: data.active.id,
            plan_name: data.active.plan_name,
            starts_at: data.active.starts_at,
            ends_at: data.active.ends_at,
            status: data.active.status,
            payment_method: data.active.payment_method || 'M-PESA'
        } : null

        pendingSubscriptionRef.value = data.pending ? {
            id: data.pending.id,
            plan_name: data.pending.plan_name,
            created_at: data.pending.created_at,
            status: data.pending.status,
            payment_method: data.pending.payment_method
        } : null

        // Update businessRef with correct plan_id from active subscription
        if (currentSubscription.value && currentSubscription.value.plan_name) {
            const matched = findPlanByName(currentSubscription.value.plan_name)
            if (matched) {
                businessRef.value = { ...businessRef.value, plan_id: matched.id }
            }
        } else if (pendingSubscriptionRef.value && pendingSubscriptionRef.value.plan_name) {
            const matched = findPlanByName(pendingSubscriptionRef.value.plan_name)
            if (matched) {
                businessRef.value = { ...businessRef.value, plan_id: matched.id }
            }
        }

    } catch (err) {
        console.error('Failed to fetch subscription status', err)
    }
}

const startPolling = (intervalMs = 2000) => {
    if (pollingIntervals.has('subscription_status_poll')) return
    // Initial fetch
    fetchSubscriptionStatus()
    // Then poll regularly
    const id = window.setInterval(fetchSubscriptionStatus, intervalMs)
    pollingIntervals.set('subscription_status_poll', id)
}

const startSocketBridge = async () => {
    if (USE_POLLING_ONLY) return
    try {
        // dynamic import (optional)
        const client = await import('socket.io-client')
        const io = client?.io || client?.default || client
        const BRIDGE = (window as any)?.REACT_APP_REALTIME_BRIDGE || 'http://localhost:4000'
        socket = io(BRIDGE, { transports: ['websocket'], autoConnect: true })

        socket.on('connect', () => {
            usingSocket = true
            try { socket.emit('subscribe:business', props.business?.id) } catch { }
        })

        socket.on('business:update', (payload: any) => {
            try {
                const type = payload?.type || payload?.payload?.type;
                if (type && String(type).startsWith('subscription.')) {
                    fetchSubscriptionStatus()
                }
            } catch { }
        })

        socket.on('disconnect', () => { usingSocket = false; socket = null })

        // if socket fails to connect quickly, fallback to SSE/polling
        setTimeout(() => {
            if (!usingSocket) {
                if (socket) try { socket.disconnect() } catch {}
                socket = null;
                startSse()
            }
        }, 1200)

    } catch (e) {
        console.debug('socket init failed', e)
        usingSocket = false
        socket = null
        startSse()
    }
}

let sse: EventSource | null = null
const startSse = () => {
    if (USE_POLLING_ONLY) return
    try {
        sse = new EventSource('/sse/business-stream', { withCredentials: true } as any)
        sse.addEventListener('business:update', (ev: MessageEvent) => {
            try {
                const payload = JSON.parse(String(ev.data));
                const type = payload.type || payload.payload?.type;
                if (type && String(type).startsWith('subscription.')) {
                    fetchSubscriptionStatus()
                }
            } catch { }
        })
        sse.onerror = () => {
            try { sse?.close() } catch {}
            sse = null;
            if (!pollingIntervals.has('subscription_status_poll')) startPolling(5000)
        }
    } catch (e) {
        console.debug('SSE init failed', e);
        if (!pollingIntervals.has('subscription_status_poll')) startPolling(5000)
    }
}

const stopSse = () => { if (sse) { try { sse.close() } catch {} sse = null } }

// Lifecycle
onMounted(() => {
    console.log('Subscription page mounted, props:', {
        currentSubscription: props.currentSubscription,
        pendingSubscription: props.pendingSubscription,
        business: props.business
    })

    // Always fetch fresh status on mount
    fetchSubscriptionStatus()

    if (USE_POLLING_ONLY) {
        startPolling(3000) // Poll every 3 seconds
        return
    }

    // try socket bridge first, then SSE, then polling
    startSocketBridge()
    // ensure initial state (already done above)
})

onUnmounted(() => {
    // clear polling
    pollingIntervals.forEach((id) => clearInterval(id))
    pollingIntervals.clear()

    // cleanup socket
    if (socket) try { socket.disconnect() } catch {}
    socket = null
    usingSocket = false

    // cleanup sse
    stopSse()
})
</script>

<template>
    <Head title="Subscription Management" />

    <AppLayout>
        <div class="py-10 space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-700 w-full mx-auto">

            <!-- Debug info (remove in production) -->
            <div v-if="false" class="fixed bottom-4 right-4 bg-gray-900 text-white p-4 rounded-lg text-xs z-50">
                <div>Current Sub: {{ currentSubscription?.plan_name }}</div>
                <div>Business Plan ID: {{ businessRef?.plan_id }}</div>
                <div>Pending: {{ pendingSubscriptionRef?.plan_name }}</div>
            </div>

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

                <!-- Rejected Subscription Alert -->
                <div v-if="pendingSubscriptionRef?.status === 'rejected'" 
                    class="border rounded-[2rem] p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden group bg-red-50 border-red-200/60"
                >
                    <div class="absolute inset-x-0 top-0 h-1 bg-red-400"></div>
                    <div class="flex items-center gap-6 relative z-10 w-full md:w-auto">
                        <div class="p-5 rounded-[1.5rem] shadow-sm group-hover:rotate-3 transition-transform bg-red-100 text-red-600">
                            <XCircle class="size-10" />
                        </div>
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-widest mb-1 bg-red-200/40 text-red-700 border-red-300/30">
                                <span class="text-red-700">Subscription Rejected</span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900">
                                {{ pendingSubscriptionRef?.plan_name }} Tier - Rejected
                            </h3>
                            <p class="text-slate-500 text-sm font-medium">
                                Your subscription request was reviewed and rejected by our admin team.
                            </p>
                            <div v-if="pendingSubscriptionRef?.rejection_reason" class="mt-3 p-3 bg-red-100/50 border border-red-200 rounded-lg">
                                <p class="text-xs font-bold text-red-800 uppercase tracking-wide mb-1">Rejection Reason:</p>
                                <p class="text-sm text-red-700 font-medium">{{ pendingSubscriptionRef.rejection_reason }}</p>
                            </div>
                            <div class="pt-3">
                                <p class="text-xs font-bold text-slate-600">
                                    Pick another plan that suits you or contact support for assistance.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="pendingSubscriptionRef && pendingSubscriptionRef.status !== 'rejected'" 
                    :class="[
                        'border rounded-[2rem] p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden group',
                        pendingSubscriptionRef?.status === 'cancelled' ? 'bg-red-50 border-red-200/60' : 'bg-amber-50 border-amber-200/60'
                    ]"
                >
                    <div :class="['absolute inset-x-0 top-0 h-1', pendingSubscriptionRef?.status === 'cancelled' ? 'bg-red-400' : 'bg-amber-400 animate-pulse']"></div>
                    <div class="flex items-center gap-6 relative z-10 w-full md:w-auto">
                        <div :class="['p-5 rounded-[1.5rem] shadow-sm group-hover:rotate-3 transition-transform', pendingSubscriptionRef?.status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600']">
                            <XCircle v-if="pendingSubscriptionRef?.status === 'cancelled'" class="size-10" />
                            <Loader2 v-else class="size-10 animate-spin" />
                        </div>
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-widest mb-1"
                                :class="pendingSubscriptionRef?.status === 'cancelled' ? 'bg-red-200/40 text-red-700 border-red-300/30' : 'bg-amber-200/40 text-amber-700 border-amber-300/30'"
                            >
                                <span v-if="pendingSubscriptionRef?.status === 'cancelled'" class="text-red-700">Transaction Failed</span>
                                <span v-else>Awaiting Verification</span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900">
                                {{ pendingSubscriptionRef?.plan_name }} Tier 
                                <span v-if="pendingSubscriptionRef?.status === 'cancelled'">Payment Failed</span>
                                <span v-else>Payment</span>
                            </h3>
                            <p class="text-slate-500 text-sm font-medium">
                                <span v-if="pendingSubscriptionRef?.status === 'cancelled'">
                                    The payment attempt for this subscription failed. Error: {{ pendingSubscriptionRef?.failure_reason || 'Unknown internal error' }}.
                                </span>
                                <span v-else>
                                    We are verifying your <span class="text-slate-900 font-black uppercase">{{ pendingSubscriptionRef?.payment_method || 'M-PESA' }}</span> transaction.
                                </span>
                            </p>
                            <p class="text-slate-500 text-sm font-medium mt-2">
                                <span class="text-[11px] font-black uppercase text-slate-400">Ref:</span>
                                <span v-if="pendingSubscriptionRef?.mpesa_receipt || pendingSubscriptionRef?.transaction_id" class="font-bold ml-2" :class="pendingSubscriptionRef?.status === 'cancelled' ? 'text-red-700' : 'text-green-700'">{{ pendingSubscriptionRef?.mpesa_receipt || pendingSubscriptionRef?.transaction_id }}</span>
                                <span v-else class="text-gray-400 ml-2">N/A</span>
                            </p>
                            
                            <!-- Force Verify Button -->
                            <div v-if="pendingSubscriptionRef?.status !== 'cancelled'" class="pt-2">
                                <Button 
                                    @click="verifyPending"
                                    :disabled="verifyLoading"
                                    variant="outline" 
                                    class="h-8 text-[10px] font-black uppercase tracking-widest bg-white border-amber-200 text-amber-700 hover:bg-amber-50 hover:text-amber-800"
                                >
                                    <Loader2 v-if="verifyLoading" class="size-3 animate-spin mr-2" />
                                    <Zap v-else class="size-3 mr-2" />
                                    {{ verifyLoading ? 'Verifying...' : 'Verify Now' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Membership Status -->
                <div v-if="currentSubscription" class="bg-white border border-slate-200/60 rounded-[2rem] p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden group">
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
                            <h3 class="text-2xl font-black text-slate-900">{{ currentSubscription?.plan_name }} Tier</h3>
                            <p class="text-slate-500 text-sm font-medium flex items-center gap-2">
                                Next billing on <span class="text-slate-900 font-bold decoration-indigo-200 decoration-2 underline-offset-4 underline">{{ currentSubscription?.ends_at ? new Date(currentSubscription.ends_at).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' }) : '' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto border-t md:border-t-0 md:border-l border-slate-100 pt-6 md:pt-0 md:pl-12 relative z-10">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Payment Method</span>
                            <div class="flex items-center gap-2 text-slate-700">
                                <CreditCard class="size-4 text-emerald-500" />
                                <span class="text-sm font-black uppercase tracking-tight">{{ currentSubscription?.payment_method || 'M-PESA WALLET' }}</span>
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
                                :disabled="isCurrentPlan(plan.id) || (pendingSubscriptionRef && pendingSubscriptionRef.status !== 'rejected')"
                                @click="openPaymentModal(plan)"
                                :class="[
                                    'w-full h-14 rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] transition-all duration-300 shadow-lg',
                                    (isCurrentPlan(plan.id) || (pendingSubscriptionRef && pendingSubscriptionRef.status !== 'rejected'))
                                        ? 'bg-transparent border-2 border-white/20 text-white/50 cursor-not-allowed shadow-none'
                                        : (plan.size_category === 'Medium' || plan.size_category === 'Large'
                                            ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-200'
                                            : 'bg-slate-900 hover:bg-slate-800 text-white')
                                ]"
                            >
                                {{ isCurrentPlan(plan.id) ? 'Currently Using' : ((pendingSubscriptionRef && pendingSubscriptionRef.status !== 'rejected') ? 'Pending Review' : 'Select ' + plan.name) }}
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
        <SubscriptionPaymentModal 
            :open="showPaymentModal" 
            @update:open="showPaymentModal = $event"
            :plan="selectedPlan"
            :billing-cycle="billingCycle"
            @success="handlePaymentSuccess"
        />
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
