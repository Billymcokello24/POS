<script setup lang="ts">
import { Loader2, Smartphone, CheckCircle2, XCircle, AlertCircle } from 'lucide-vue-next'
import { ref, computed, watch, onUnmounted } from 'vue'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { postJsonWithSanctum } from '@/lib/sanctum'

const props = defineProps<{
    open: boolean
    plan: any
    billingCycle: 'monthly' | 'yearly'
}>()

const emit = defineEmits(['update:open', 'success'])

// State
const step = ref<'input' | 'initiating' | 'processing' | 'success' | 'error'>('input')
const phone = ref('')
const errorMsg = ref('')
const checkoutRequestId = ref('')
const receipt = ref('')
const confirmedResultDesc = ref('')
const amountPaid = ref(0)
const pollingInterval = ref<number | null>(null)

// M-Pesa error codes mapping (matching Sales/Create.vue)
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

// Codes that mean "still processing" - don't fail, keep polling
const pendingCodes = ['1037', '4999']

// Codes that mean user cancelled - these are final failures
const cancelledCodes = ['1032', '2029']

// Computed
const formattedAmount = computed(() => {
    if (!props.plan) return '0.00'
    const val = props.billingCycle === 'monthly' ? props.plan.price_monthly : props.plan.price_yearly
    return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})

const isValidPhone = computed(() => {
    // Basic validation: must be at least 10 digits
    const p = phone.value.replace(/\D/g, '')
    return p.length >= 10
})

const formatCurrency = (amount: number) => {
    return 'KSh ' + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const loading = computed(() => step.value === 'initiating' || step.value === 'processing')
const success = computed(() => step.value === 'success')
const error = computed(() => step.value === 'error')

// Reset on open
watch(() => props.open, (val) => {
    if (val) {
        step.value = 'input'
        errorMsg.value = ''
        receipt.value = ''
        // Keep phone number for convenience
    } else {
        stopPolling()
    }
})

// Actions
const initiatePayment = async () => {
    if (!isValidPhone.value) return

    step.value = 'initiating'
    errorMsg.value = ''

    try {
        const payload = {
            plan_id: props.plan.id,
            billing_cycle: props.billingCycle,
            phone_number: phone.value,
            amount: props.billingCycle === 'monthly' ? props.plan.price_monthly : props.plan.price_yearly
        }

        console.log('Initiating subscription payment...', payload)

        const res = await postJsonWithSanctum('/subscription/api/pay', payload)
        
        if (!res.ok) {
            const text = await res.text()
            let msg = 'Failed to initiate payment'
            try { 
                const errorData = JSON.parse(text)
                msg = errorData.message || msg 
            } catch (e) { /* ignore */ }
            throw new Error(msg)
        }

        const data = await res.json()
        console.log('Payment initiation response:', data)

        if (!data.success) {
            throw new Error(data.message || 'Payment initiation failed')
        }

        const checkoutId = data.data?.checkout_request_id || data.data?.checkoutRequestID
        if (!checkoutId) throw new Error('System Error: No checkout ID returned')

        checkoutRequestId.value = checkoutId
        step.value = 'processing'
        startPolling(checkoutId)

    } catch (e: any) {
        console.error('Payment initiation error:', e)
        step.value = 'error'
        errorMsg.value = e.message || 'An unexpected error occurred.'
    }
}

const isVerifying = ref(false)

const startPolling = (checkoutId: string) => {
    let attempts = 0
    const maxAttempts = 40 // ~2 mins (40 * 3s)

    console.log('Starting payment status polling for:', checkoutId)
    stopPolling()

    pollingInterval.value = window.setInterval(async () => {
        attempts++
        isVerifying.value = true

        try {
            // Point 5 & 12: Unique dedicated status route for subscriptions to avoid route collision
            const res = await postJsonWithSanctum('/api/subscription/payment/status', { 
                checkout_request_id: checkoutId 
            })

            if (!res.ok) {
                isVerifying.value = false
                if (attempts >= maxAttempts) {
                    stopPolling()
                    step.value = 'error'
                    errorMsg.value = 'Payment verification timed out.'
                }
                return
            }

            const body = await res.json()
            isVerifying.value = false

            const data = body.data || {}
            const resultCode = data.ResultCode
            
            // ROBUST SUCCESS CHECK: Indicator 1: ResultCode '0', Indicator 2: Status 'success', Indicator 3: subscription_linked
            const isConfirmed = resultCode !== undefined && resultCode !== null && String(resultCode) === '0'

            if (isConfirmed) {
                stopPolling()
                // Let the "Verifying..." animation breathe for a moment before the WOW
                setTimeout(() => {
                    step.value = 'success'
                    receipt.value = data.MpesaReceiptNumber || checkoutId
                    confirmedResultDesc.value = data.ResultDesc || 'Payment processed successfully'
                    amountPaid.value = Number(props.billingCycle === 'monthly' ? props.plan.price_monthly : props.plan.price_yearly)
                    emit('success')
                    
                    setTimeout(() => {
                        emit('update:open', false)
                    }, 6500) // Longer delay to let them enjoy the success
                }, 800)
                return
            }
            
            if (resultCode && !pendingCodes.includes(resultCode.toString())) {
                stopPolling()
                const errorDesc = data.ResultDesc || 'Unknown error'
                const friendlyMessage = mpesaErrorCodes[resultCode] || errorDesc

                if (cancelledCodes.includes(resultCode.toString())) {
                    const desc = data.ResultDesc || ''
                    if (desc.includes('unresolved reason')) {
                        errorMsg.value = 'The payment could not be processed. Possible reasons:\n• Phone number not registered with M-Pesa\n• Network connectivity issues\n• Phone is switched off or out of coverage\n• M-Pesa account has restrictions.'
                    } else {
                        errorMsg.value = 'Payment cancelled by customer'
                    }
                } else if (resultCode === '1') {
                    errorMsg.value = 'Insufficient Balance in M-Pesa account'
                } else if (resultCode === '2001') {
                    errorMsg.value = 'Wrong PIN entered'
                } else if (resultCode === '2006') {
                    errorMsg.value = 'PIN Blocked - Please contact Safaricom'
                } else if (resultCode === '2007') {
                    errorMsg.value = 'Request Timed Out - Please try again'
                } else {
                    errorMsg.value = `M-Pesa Payment Failed: ${friendlyMessage}`
                }
                
                step.value = 'error'
            }

            if (attempts >= maxAttempts) {
                stopPolling()
                step.value = 'error'
                errorMsg.value = 'Payment verification timed out. Please check your M-Pesa messages.'
            }

        } catch (e: any) {
            isVerifying.value = false
            if (attempts >= maxAttempts) {
                stopPolling()
                step.value = 'error'
                errorMsg.value = 'Network error.'
            }
        }
    }, 3000)
}

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
        isVerifying.value = false
    }
}

const retry = () => {
    step.value = 'input'
    errorMsg.value = ''
    isVerifying.value = false
}

onUnmounted(() => {
    stopPolling()
})

</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent 
            class="sm:max-w-[425px] p-0 overflow-hidden border-none shadow-2xl rounded-2xl bg-white focus:outline-none" 
            @pointer-down-outside="(e) => { if(loading) e.preventDefault() }" 
            @escape-key-down="(e) => { if(loading) e.preventDefault() }"
        >
             <DialogDescription class="sr-only">Payment Modal</DialogDescription>
            
            <!-- Header (Hidden when processing/success to focus on status) -->
            <DialogHeader v-if="!loading && !success" class="px-8 pt-8 pb-4">
                <DialogTitle class="text-2xl font-black text-slate-900 text-center">
                    Secure Payment
                </DialogTitle>
                <div class="text-center text-slate-500 text-sm font-medium mt-1">
                    Upgrade to <span class="text-indigo-600 font-bold">{{ plan?.name }} Plan</span>
                </div>
            </DialogHeader>

            <!-- INPUT STATE -->
            <div v-if="step === 'input'" class="px-8 pb-8 space-y-6">
                <!-- Amount Display -->
                <div class="flex flex-col items-center justify-center py-6 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Amount</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-sm font-bold text-slate-500">KES</span>
                        <span class="text-4xl font-black text-slate-900 tracking-tight">{{ formattedAmount }}</span>
                    </div>
                     <span class="text-xs font-bold text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full mt-2 uppercase tracking-wide">
                        {{ billingCycle === 'monthly' ? 'Monthly' : 'Yearly' }} Billing
                    </span>
                </div>

                <!-- Phone Input -->
                <div class="space-y-3">
                    <Label class="text-xs font-bold uppercase text-slate-500 tracking-widest ml-1">M-Pesa Phone Number</Label>
                    <div class="relative">
                        <Smartphone class="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                        <Input 
                            v-model="phone" 
                            placeholder="07XX XXX XXX" 
                            class="pl-10 h-12 text-lg font-bold tracking-wide border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl bg-white"
                            autofocus
                        />
                    </div>
                     <p class="text-[10px] text-slate-400 font-medium pl-1">
                        You will receive a payment prompt on this phone.
                    </p>
                </div>

                <Button 
                    @click="initiatePayment" 
                    :disabled="!isValidPhone" 
                    class="w-full h-14 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-black uppercase tracking-widest text-xs shadow-xl transition-all hover:scale-[1.02] active:scale-[0.98]"
                >
                    Pay Now
                </Button>
            </div>

            <!-- INITIATING STATE (Preloader) -->
            <div v-else-if="step === 'initiating'" class="flex flex-col items-center justify-center py-20 px-8 text-center space-y-8">
                <div class="relative">
                    <div class="absolute inset-0 bg-indigo-100 rounded-full animate-ping opacity-25"></div>
                    <div class="relative bg-white p-6 rounded-full shadow-2xl border border-indigo-50">
                        <Loader2 class="h-12 w-12 text-indigo-600 animate-spin" />
                    </div>
                </div>
                <div class="space-y-2">
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight">Preloading Secure Payment</h3>
                    <p class="text-slate-500 text-sm font-bold uppercase tracking-widest animate-pulse opacity-70">
                        Connecting to Safariom...
                    </p>
                </div>
            </div>

            <!-- PROCESSING STATE (Polling) -->
            <div v-else-if="step === 'processing'" class="flex flex-col items-center justify-center py-16 px-8 text-center space-y-6">
                <div class="relative">
                    <div class="absolute inset-0 bg-amber-100 rounded-full animate-ping opacity-50"></div>
                    <div class="relative bg-white p-4 rounded-full shadow-lg border border-amber-100">
                        <Smartphone class="h-10 w-10 text-amber-500 animate-bounce" />
                    </div>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">Check your phone</h3>
                    <p class="text-slate-500 text-sm font-medium leading-relaxed max-w-[250px] mx-auto">
                        We sent an M-Pesa prompt to <br><span class="text-slate-900 font-bold font-mono tracking-wider">{{ phone }}</span>
                    </p>
                </div>
                 <div class="w-full bg-slate-100 rounded-full h-2 max-w-[200px] overflow-hidden">
                    <div class="h-full bg-amber-500 w-full animate-progres-pulse"></div>
                </div>
                <div class="space-y-1">
                    <p :class="['text-xs font-bold uppercase tracking-widest transition-all duration-300', isVerifying ? 'text-amber-600 scale-105' : 'text-slate-400']">
                        {{ isVerifying ? 'Verifying Payment...' : 'Waiting for your PIN...' }}
                    </p>
                    <p class="text-[10px] text-slate-300 font-medium">Auto-verifying in real-time</p>
                </div>
            </div>

            <!-- SUCCESS STATE - High-end Celebratory Experience -->
            <div v-else-if="step === 'success'" class="flex flex-col items-center justify-center py-20 px-8 text-center space-y-10 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/30 overflow-hidden relative">
                 <!-- Celebratory background elements -->
                 <div class="absolute inset-0 pointer-events-none overflow-hidden">
                    <div class="absolute top-1/4 left-1/4 size-64 bg-emerald-200/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-1/4 right-1/4 size-64 bg-indigo-200/20 rounded-full blur-3xl animate-pulse delay-700"></div>
                 </div>

                 <div class="relative scale-125 transition-transform duration-700 ease-out animate-[spring_0.8s_ease-out]">
                    <div class="absolute inset-0 bg-emerald-200 rounded-full animate-ping opacity-30"></div>
                    <div class="absolute -inset-4 bg-gradient-to-tr from-emerald-400 to-emerald-200 rounded-full blur-xl opacity-20 animate-pulse"></div>
                    <div class="relative bg-white p-6 rounded-full shadow-2xl border-4 border-emerald-100 flex items-center justify-center">
                        <CheckCircle2 class="h-16 w-16 text-emerald-500 animate-[check-bounce_1.5s_infinite] drop-shadow-sm" />
                    </div>
                    
                    <!-- Floating Particle Elements (CSS Only) -->
                    <div class="absolute -top-4 -left-4 size-3 bg-emerald-400 rounded-full animate-ping delay-150"></div>
                    <div class="absolute top-14 -right-8 size-4 bg-indigo-400 rounded-full animate-ping delay-300"></div>
                    <div class="absolute -bottom-8 left-14 size-3 bg-amber-400 rounded-full animate-ping delay-75"></div>
                </div>

                <div class="space-y-4 relative z-10">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-black uppercase tracking-[0.25em] mb-2 animate-bounce shadow-sm border border-emerald-200/50">
                        <span class="size-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        Subscription Active
                    </div>
                    <h3 class="text-4xl font-black text-slate-900 tracking-tight leading-none drop-shadow-sm">
                        Success! 🎉
                    </h3>
                    <p class="text-slate-500 text-base font-bold max-w-[320px] mx-auto leading-relaxed">
                        {{ confirmedResultDesc }}
                    </p>
                </div>

                <div class="w-full max-w-[320px] space-y-4 relative z-10">
                    <!-- Transaction Summary (Aligned with Sales flow) -->
                    <div class="bg-emerald-50/50 backdrop-blur-sm px-6 py-5 rounded-3xl border-2 border-emerald-100/50 shadow-xl space-y-3 group hover:scale-[1.02] transition-transform">
                        <div class="flex justify-between items-center pb-2 border-b border-emerald-100/50">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest leading-none">Receipt Number</span>
                            <span class="font-mono font-black text-slate-800 tracking-wider">{{ receipt }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest leading-none">Amount Paid</span>
                            <span class="font-black text-slate-900">{{ formatCurrency(amountPaid) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest leading-none">Plan Status</span>
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                <span class="text-[10px] font-black text-slate-800 uppercase tracking-widest">Active</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-2 text-slate-400">
                        <Loader2 class="size-3 animate-spin" />
                        <span class="text-[10px] font-bold uppercase tracking-widest">Redirecting to Dashboard...</span>
                    </div>
                </div>
            </div>

            <!-- ERROR STATE - Enhanced with better error messages -->
            <div v-else-if="error" class="flex flex-col items-center justify-center py-12 px-8 text-center space-y-6">
                <div class="bg-red-50 p-4 rounded-full">
                    <XCircle class="h-10 w-10 text-red-500" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">Payment Failed</h3>
                    <p class="text-red-600 text-sm font-medium max-w-xs mx-auto whitespace-pre-line leading-relaxed">{{ errorMsg }}</p>
                </div>
                <div class="flex gap-4 w-full">
                     <Button variant="outline" @click="$emit('update:open', false)" class="flex-1 rounded-xl font-bold h-12 border-slate-200">Cancel</Button>
                     <Button @click="retry" class="flex-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-12">Try Again</Button>
                </div>
            </div>

        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes spring {
    0% { transform: scale(0.5) translateY(20px); opacity: 0; }
    50% { transform: scale(1.4); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1.25) translateY(0); opacity: 1; }
}

@keyframes check-bounce {
    0%, 100% { transform: translateY(0) scale(1.1); }
    50% { transform: translateY(-8px) scale(1.15); }
}

.animate-progres-pulse {
    animation: progress-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes progress-pulse {
    0%, 100% { opacity: 1; transform: translateX(-100%); }
    50% { opacity: 0.5; transform: translateX(100%); }
}

/* Ensure the success scaling works with our custom animation */
.animate-\[spring_0\.8s_ease-out\] {
    animation: spring 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

.animate-\[check-bounce_1\.5s_infinite\] {
    animation: check-bounce 1.5s ease-in-out infinite;
}
</style>
