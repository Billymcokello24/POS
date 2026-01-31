<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import {
    Plus,
    Calendar,
    DollarSign,
    TrendingUp,
    Zap,
    Search,
    Filter,
    ArrowUpRight,
    Loader2,
    CheckCircle2,
    Trash2,
    MoreVertical,
    CheckCircle,
    XCircle,
    Clock
} from 'lucide-vue-next'
import { ref, watch, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
    subscriptions: {
        data: Array<{
            id: number
            subscription_id: number | null
            payment_id: number | null
            plan_name: string
            amount: number
            currency: string
            status: string
            subscription_status?: string
            approval_status?: string
            billing_cycle?: string
            mpesa_receipt?: string
            transaction_id: string
            payment_method: string
            payment_details?: any
            starts_at: string
            ends_at: string | null
            created_at: string
            business: {
                name: string
            }
            auto_verified?: boolean
        }>
        links: any
    }
    businesses: Array<{
        id: number
        name: string
    }>
    plans: Array<any>
    revenue: {
        today: number
        this_month: number
        this_year: number
        total: number
        conversion_rate: number
        monthly_trend: Array<any>
    }
    filters: {
        search?: string
        plan?: string
        date_from?: string
        date_to?: string
    }
}>()

const search = ref(props.filters.search || '')
const planFilter = ref(props.filters.plan || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')

watch([search, planFilter, dateFrom, dateTo], debounce(() => {
    router.get(
        '/admin/subscriptions',
        {
            search: search.value,
            plan: planFilter.value,
            date_from: dateFrom.value,
            date_to: dateTo.value
        },
        { preserveState: true, replace: true }
    )
}, 500))

// Removed: Manual activation functions (violates payment-as-truth)
// const showAddModal = ref(false)
// const processingId = ref<number | null>(null)
// const approveSubscription = () => { ... }

const reconcileLoading = ref(false)
const reconcileResult = ref<any | null>(null)
const showReconcileModal = ref(false)

// Removed: Manual subscription creation (violates payment-as-truth)
// const form = useForm({ ... })
// const submitPayment = () => { ... }


const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0
    }).format(amount)
}

const formatDate = (date: string | null) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const runReconcile = async () => {
    reconcileLoading.value = true
    reconcileResult.value = null
    showReconcileModal.value = true

    try {
        const resp = await fetch('/admin/subscriptions/reconcile', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({})
        })

        reconcileResult.value = await resp.json()
    } catch (err) {
        reconcileResult.value = { success: false, message: (err as any).message }
    } finally {
        reconcileLoading.value = false
    }
}

const getMpesaStk = (sub: any) => {
    try {
        // Prefer explicit mpesa_receipt field
        if (sub.mpesa_receipt) return sub.mpesa_receipt
        // Some APIs use mpesa_stk or payment_details.mpesa_receipt
        if (sub.mpesa_stk) return sub.mpesa_stk
        const pd = sub.payment_details || sub.paymentDetails || null
        if (pd && pd.mpesa_receipt) return pd.mpesa_receipt
        // Fallback to transaction id
        if (sub.transaction_id) return sub.transaction_id
    } catch { /* ignore */ }
    return null
}

// Selection state for bulk delete
const selectedIds = ref<number[]>([])
const isSelected = (id: number) => selectedIds.value.includes(id)
const toggleSelect = (id: number) => {
    if (isSelected(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id)
    } else {
        selectedIds.value = [...selectedIds.value, id]
    }
}

// Computed property for select all checkbox
const allSelected = computed({
    get() {
        return props.subscriptions.data.length > 0 && 
               selectedIds.value.length === props.subscriptions.data.length
    },
    set(value: boolean) {
        if (value) {
            selectedIds.value = props.subscriptions.data.map(s => s.id)
        } else {
            selectedIds.value = []
        }
    }
})

const deletePayment = async (id: number, businessName: string, planName: string) => {
    if (!confirm(`⚠️ Delete subscription payment for "${businessName}" - ${planName}?\n\nThis will permanently delete the payment record and related subscription.\n\nThis action CANNOT be undone!`)) return
    
    await router.delete(`/admin/subscriptions/payments/${id}`)
    router.reload()
}

const bulkDelete = async () => {
    if (selectedIds.value.length === 0) return
    if (!confirm(`⚠️ DANGER: Permanently delete ${selectedIds.value.length} subscription payment(s)?\n\nThis will delete:\n• All selected payment records\n• Related subscription records\n• All associated data\n\nThis action CANNOT be undone!`)) return
    
    try {
        await router.post('/admin/subscriptions/bulk-delete', {
            ids: selectedIds.value
        })
        selectedIds.value = []
        router.reload()
    } catch (e) {
        console.error('Bulk delete failed:', e)
        alert('Bulk delete failed')
    }
}

// Approve subscription
const approveSubscription = async (paymentId: number, businessName: string, planName: string) => {
    if (!confirm(`✅ Approve subscription for "${businessName}" - ${planName}?\n\nThis will:\n• Activate the subscription\n• Unlock plan features\n• Set billing dates\n\nContinue?`)) return
    
    try {
        await router.post(`/admin/subscriptions/payments/${paymentId}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload()
            }
        })
    } catch (e) {
        console.error('Approval failed:', e)
        alert('Approval failed')
    }
}

// Reject subscription
const rejectReason = ref('')
const showRejectModal = ref(false)
const rejectingPaymentId = ref<number | null>(null)

const openRejectModal = (paymentId: number) => {
    rejectingPaymentId.value = paymentId
    rejectReason.value = ''
    showRejectModal.value = true
}

const confirmReject = async () => {
    if (!rejectReason.value.trim()) {
        alert('Please provide a rejection reason')
        return
    }
    
    if (!rejectingPaymentId.value) return
    
    try {
        await router.post(`/admin/subscriptions/payments/${rejectingPaymentId.value}/reject`, {
            reason: rejectReason.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showRejectModal.value = false
                rejectingPaymentId.value = null
                rejectReason.value = ''
                router.reload()
            }
        })
    } catch (e) {
        console.error('Rejection failed:', e)
        alert('Rejection failed')
    }
}
</script>

<template>
    <Head title="Revenue Intelligence" />

    <AdminLayout>
        <div class="space-y-10 animate-in fade-in duration-700">

            <!-- Elite Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl -mr-32 -mt-32"></div>
                <div class="space-y-2 relative z-10">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="p-1.5 bg-emerald-100 rounded-lg">
                            <TrendingUp class="size-4 text-emerald-600" />
                        </div>
                        <span class="text-xs font-black text-emerald-600 uppercase tracking-widest">Financial Oversight</span>
                    </div>
                    <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight leading-none">Revenue Intelligence</h1>
                    <p class="text-slate-500 font-medium">Subscription performance, revenue flow, and tenant billing lifecycle.</p>
                </div>
                <div class="flex gap-3 relative z-10">
                    <Button 
                        v-if="selectedIds.length > 0" 
                        @click="bulkDelete" 
                        variant="destructive" 
                        class="h-12 px-6 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black uppercase tracking-widest text-[10px] gap-2"
                    >
                        <Trash2 class="size-4" />
                        Delete {{ selectedIds.length }} Selected
                    </Button>
                    <Button variant="outline" class="h-12 px-6 rounded-xl border-slate-200 font-black uppercase tracking-widest text-[10px] gap-2 hover:bg-slate-50">
                        <TrendingUp class="size-4" />
                        Export Ledger
                    </Button>
                    <Button 
                        disabled 
                        class="h-12 px-6 rounded-xl bg-slate-300 text-slate-500 font-black uppercase tracking-widest text-[10px] gap-2 cursor-not-allowed opacity-60"
                        title="Manual subscription creation disabled - all subscriptions must originate from confirmed payments"
                    >
                        <Plus class="size-4" />
                        Manual Entry Disabled
                    </Button>
                </div>
            </div>

            <!-- Strategic Revenue Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <Card class="border-none shadow-sm bg-slate-900 text-white rounded-3xl relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 to-indigo-950"></div>
                    <div class="absolute -bottom-4 -right-4 size-32 bg-indigo-500/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000"></div>
                    <CardContent class="pt-8 relative z-10">
                        <div class="flex items-center justify-between opacity-60 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">Gross Collection</span>
                            <DollarSign class="size-4" />
                        </div>
                        <div class="text-3xl font-black tracking-tight">{{ formatCurrency(props.revenue.total) }}</div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs text-indigo-400 font-bold uppercase tracking-tight">
                            Platform Lifetime Value
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white rounded-3xl group hover:shadow-xl transition-all duration-500">
                    <CardContent class="pt-8">
                        <div class="flex items-center justify-between text-slate-400 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">Collected Today</span>
                            <Zap class="size-4 text-amber-500" />
                        </div>
                        <div class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(props.revenue.today) }}</div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs text-emerald-600 font-bold uppercase tracking-tight">
                            <ArrowUpRight class="size-3.5" />
                            Active Inflow
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white rounded-3xl group hover:shadow-xl transition-all duration-500">
                    <CardContent class="pt-8">
                        <div class="flex items-center justify-between text-slate-400 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">Monthly Volume</span>
                            <Calendar class="size-4 text-indigo-500" />
                        </div>
                        <div class="text-3xl font-black text-slate-900 tracking-tight">{{ formatCurrency(props.revenue.this_month) }}</div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs text-slate-500 font-bold uppercase tracking-tight">
                            Cycle Performance
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-emerald-500 text-white rounded-3xl relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500 to-emerald-600"></div>
                    <CardContent class="pt-8 relative z-10">
                        <div class="flex items-center justify-between opacity-70 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">Conversion Efficiency</span>
                            <TrendingUp class="size-4" />
                        </div>
                        <div class="text-3xl font-black tracking-tight">{{ props.revenue.conversion_rate }}%</div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs text-white/80 font-bold uppercase tracking-tight text-white">
                            Transaction Success Rate
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Intelligent Filters & Table -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-slate-900 text-white rounded-xl">
                                <Filter class="size-4" />
                            </div>
                            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Transaction Ledger</h2>
                        </div>
                        <Badge variant="outline" class="bg-slate-50 text-slate-500 border-slate-200 px-3 font-bold uppercase text-[9px] tracking-widest">
                            Showing {{ props.subscriptions.data.length }} Records
                        </Badge>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="relative">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                            <Input v-model="search" placeholder="Business or Ref..." class="h-12 pl-12 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white font-bold" />
                        </div>
                        <select v-model="planFilter" class="h-12 px-4 rounded-xl border border-slate-200 bg-slate-50/50 font-bold text-sm focus:bg-white transition-all appearance-none cursor-pointer">
                            <option value="">All Plan Tiers</option>
                            <option v-for="plan in props.plans" :key="plan.id" :value="plan.name">{{ plan.name }}</option>
                        </select>
                        <Input v-model="dateFrom" type="date" class="h-12 px-4 rounded-xl border-slate-200 bg-slate-50/50 font-bold" />
                        <Input v-model="dateTo" type="date" class="h-12 px-4 rounded-xl border-slate-200 bg-slate-50/50 font-bold" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50/50">
                            <TableRow>
                                <TableHead class="w-12 px-4">
                                    <input type="checkbox" v-model="allSelected" class="h-4 w-4" />
                                </TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px] pl-10 py-5">Origin (Business)</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Subscription Tier</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Billing Cycle</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">M-Pesa Ref</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Amount</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Approval Status</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px] text-right pr-10">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="sub in props.subscriptions.data" :key="sub.id" class="group hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <TableCell class="py-4 px-4">
                                    <input type="checkbox" :checked="isSelected(sub.id)" @change="toggleSelect(sub.id)" class="h-4 w-4" />
                                </TableCell>
                                <TableCell class="pl-10 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="size-10 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-400 border border-slate-200 uppercase group-hover:bg-slate-900 group-hover:text-white group-hover:border-slate-900 transition-all">
                                            {{ sub.business.name.substring(0, 2) }}
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="font-black text-slate-900 tracking-tight">{{ sub.business.name }}</div>
                                            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">TENANT-ID: #{{ sub.id.toString().padStart(4, '0') }}</div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge :class="sub.plan_name.toLowerCase().includes('enterprise') ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'" class="px-3 py-1 font-black uppercase text-[9px] tracking-widest border-none">
                                        {{ sub.plan_name }}
                                    </Badge>
                                </TableCell>
                                
                                <!-- Billing Cycle -->
                                <TableCell>
                                    <Badge class="bg-blue-50 text-blue-700 px-3 py-1 font-black uppercase text-[9px] tracking-widest border-none">
                                        {{ sub.billing_cycle || 'monthly' }}
                                    </Badge>
                                </TableCell>
                                
                                <!-- M-Pesa Receipt -->
                                <TableCell>
                                    <div class="font-mono text-xs font-bold text-slate-700">{{ sub.mpesa_receipt || 'N/A' }}</div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">M-Pesa Ref</div>
                                </TableCell>
                                
                                <!-- Amount -->
                                <TableCell>
                                    <div class="font-black text-slate-900">{{ formatCurrency(sub.amount) }}</div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Revenue</div>
                                </TableCell>
                                
                                <!-- Approval Status -->
                                <TableCell>
                                    <div v-if="sub.subscription_status === 'active'" class="flex items-center gap-1.5">
                                        <CheckCircle class="size-3.5 text-emerald-500" />
                                        <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Active</span>
                                    </div>
                                    <div v-else-if="sub.subscription_status === 'rejected' || sub.approval_status === 'rejected'" class="flex items-center gap-1.5">
                                        <XCircle class="size-3.5 text-red-500" />
                                        <span class="text-[10px] font-black text-red-700 uppercase tracking-widest">Rejected</span>
                                    </div>
                                    <div v-else-if="sub.approval_status === 'pending'" class="flex items-center gap-1.5">
                                        <Clock class="size-3.5 text-amber-500 animate-pulse" />
                                        <span class="text-[10px] font-black text-amber-700 uppercase tracking-widest">Pending</span>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ sub.subscription_status || sub.approval_status || 'N/A' }}</span>
                                    </div>
                                </TableCell>
                                
                                <!-- Actions -->
                                <TableCell class="text-right pr-10">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Approve/Reject buttons for pending subscriptions -->
                                        <div v-if="sub.approval_status === 'pending' && (sub.status === 'SUCCESS' || sub.status === 'PENDING')" class="flex gap-2">
                                            <Button 
                                                @click="approveSubscription(sub.id, sub.business.name, sub.plan_name)" 
                                                size="sm"
                                                class="h-8 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] uppercase tracking-widest rounded-lg"
                                            >
                                                <CheckCircle class="size-3 mr-1" />
                                                Approve
                                            </Button>
                                            <Button 
                                                @click="openRejectModal(sub.id)" 
                                                size="sm"
                                                variant="outline"
                                                class="h-8 px-3 border-red-200 text-red-600 hover:bg-red-50 font-bold text-[10px] uppercase tracking-widest rounded-lg"
                                            >
                                                <XCircle class="size-3 mr-1" />
                                                Reject
                                            </Button>
                                        </div>
                                        
                                        <!-- Status for approved/rejected -->
                                        <div v-else-if="sub.approval_status === 'approved'" class="flex items-center gap-1.5">
                                            <CheckCircle2 class="size-3.5 text-emerald-500" />
                                            <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Activated</span>
                                        </div>
                                        <div v-else-if="sub.approval_status === 'rejected'" class="flex items-center gap-1.5">
                                            <Badge variant="outline" class="border-red-200 text-red-600 bg-red-50 font-black text-[9px] uppercase tracking-widest px-2 py-1">
                                                Rejected
                                            </Badge>
                                        </div>
                                        
                                        <!-- Timestamp -->
                                        <div class="text-[9px] text-slate-400 font-medium">{{ formatDate(sub.created_at) }}</div>

                                        <!-- Actions dropdown -->
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" class="h-8 w-8 p-0 text-slate-400 hover:text-slate-900">
                                                    <MoreVertical class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem @click="deletePayment(sub.id, sub.business.name, sub.plan_name)" class="cursor-pointer text-red-600 font-bold">
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete Payment
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="props.subscriptions.data.length === 0">
                                <TableCell colspan="6" class="h-64 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-4">
                                        <Search class="size-12 text-slate-100" />
                                        <p class="text-slate-400 font-black uppercase text-xs tracking-[0.2em]">No financial data matches your query</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>


        <Dialog v-model:open="showReconcileModal">
            <DialogContent class="sm:max-w-[600px] p-0 border-none shadow-3xl bg-white overflow-hidden rounded-[2.5rem]">
                <div class="p-10 space-y-8">
                    <div class="space-y-1">
                        <DialogTitle class="text-3xl font-black text-slate-900 tracking-tighter">Run Reconciliation</DialogTitle>
                        <DialogDescription class="text-slate-500 font-medium pt-1">Fetch and display reconciliation results.</DialogDescription>
                    </div>

                    <div class="space-y-4">
                        <Button @click="runReconcile" :disabled="reconcileLoading" class="w-full h-12 rounded-xl bg-slate-900 text-white font-black uppercase tracking-widest text-[10px] shadow-xl shadow-slate-200 transition-all">
                            <Loader2 v-if="reconcileLoading" class="size-4 animate-spin mr-2" />
                            Run Reconcile
                        </Button>

                        <div v-if="reconcileResult" class="p-4 rounded-lg border" :class="reconcileResult.success ? 'border-emerald-500 bg-emerald-50' : 'border-red-500 bg-red-50'">
                            <div class="text-sm font-bold" v-html="reconcileResult.success ? 'Reconciliation Successful' : 'Reconciliation Failed'"></div>
                            <div class="text-xs text-slate-500" v-if="reconcileResult.data">
                                <pre class="whitespace-pre-wrap">{{ JSON.stringify(reconcileResult.data, null, 2) }}</pre>
                            </div>
                            <div class="text-xs text-slate-500" v-else>
                                {{ reconcileResult.message }}
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog :open="showRejectModal" @update:open="showRejectModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogTitle class="text-xl font-bold text-slate-900">Reject Subscription</DialogTitle>
                <DialogDescription class="text-slate-600">
                    Please provide a reason for rejecting this subscription payment.
                </DialogDescription>
                
                <div class="space-y-4 py-4">
                    <div>
                        <Label class="text-sm font-bold text-slate-700">Rejection Reason</Label>
                        <textarea 
                            v-model="rejectReason" 
                            placeholder="e.g., Duplicate payment, Invalid business details, etc."
                            class="w-full mt-2 p-3 border border-slate-200 rounded-lg focus:border-indigo-500 focus:ring-indigo-500 min-h-[100px] text-sm"
                        ></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 justify-end">
                    <Button 
                        variant="outline" 
                        @click="showRejectModal = false"
                        class="px-4 font-bold"
                    >
                        Cancel
                    </Button>
                    <Button 
                        @click="confirmReject"
                        class="px-4 bg-red-600 hover:bg-red-700 text-white font-bold"
                    >
                        Confirm Rejection
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

    </AdminLayout>
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
