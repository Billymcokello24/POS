<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    CreditCard,
    Plus,
    Calendar,
    DollarSign,
    History,
    TrendingUp,
    Users,
    Zap,
    Search,
    Filter,
    ArrowUpRight,
    ArrowDownRight,
    Loader2,
    Building2,
    Crown,
    Info,
    CheckCircle2,
    XCircle
} from 'lucide-vue-next'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

const props = defineProps<{
    subscriptions: {
        data: Array<{
            id: number
            plan_name: string
            amount: number
            currency: string
            status: string
            transaction_id: string
            payment_method: string
            starts_at: string
            ends_at: string | null
            created_at: string
            business: {
                name: string
            }
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

const showAddModal = ref(false)
const processingId = ref<number | null>(null)

const form = useForm({
    business_id: '',
    plan_id: '',
    amount: '',
    currency: 'KES',
    starts_at: new Date().toISOString().split('T')[0],
    ends_at: '',
})

const submitPayment = () => {
    form.post('/admin/subscriptions', {
        onSuccess: () => {
            form.reset()
            showAddModal.value = false
        }
    })
}

const approveSubscription = (id: number) => {
    processingId.value = id
    router.post(`/admin/subscriptions/${id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null
        }
    })
}

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
                    <Button variant="outline" class="h-12 px-6 rounded-xl border-slate-200 font-black uppercase tracking-widest text-[10px] gap-2 hover:bg-slate-50">
                        <TrendingUp class="size-4" />
                        Export Ledger
                    </Button>
                    <Button @click="showAddModal = true" class="h-12 px-6 rounded-xl bg-slate-900 hover:bg-black text-white font-black uppercase tracking-widest text-[10px] gap-2 shadow-xl shadow-slate-200/50">
                        <Plus class="size-4" />
                        Record Transaction
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
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">Annual Run Rate</span>
                            <TrendingUp class="size-4" />
                        </div>
                        <div class="text-3xl font-black tracking-tight">{{ formatCurrency(props.revenue.this_year) }}</div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs text-white/80 font-bold uppercase tracking-tight text-white">
                            Year-to-Date Growth
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
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px] pl-10 py-5">Origin (Business)</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Subscription Tier</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Method & Status</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Amount</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px]">Billing Window</TableHead>
                                <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[9px] text-right pr-10">Timeline / Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="sub in props.subscriptions.data" :key="sub.id" class="group hover:bg-slate-50/50 transition-colors border-b border-slate-50">
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
                                <TableCell>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5 font-black text-[10px] uppercase">
                                            <div class="size-1.5 rounded-full" :class="sub.status === 'active' ? 'bg-emerald-500 animate-pulse' : (sub.status === 'pending' ? 'bg-amber-500 animate-bounce' : 'bg-slate-300')"></div>
                                            <span :class="sub.status === 'active' ? 'text-emerald-700' : (sub.status === 'pending' ? 'text-amber-700' : 'text-slate-500')">{{ sub.status }}</span>
                                        </div>
                                        <div class="text-[9px] text-slate-400 font-mono tracking-tighter uppercase">{{ sub.payment_method || 'MPESA' }}: {{ sub.transaction_id || 'N/A' }}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-black text-slate-900">{{ formatCurrency(sub.amount) }}</div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Revenue Ledger</div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2 text-xs font-bold text-slate-600">
                                        <span>{{ sub.starts_at ? formatDate(sub.starts_at) : '...' }}</span>
                                        <TrendingUp class="size-3 text-slate-300" />
                                        <span>{{ sub.ends_at ? formatDate(sub.ends_at) : '...' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right pr-10">
                                    <div v-if="sub.status === 'pending'" class="flex items-center justify-end gap-2">
                                        <Button
                                            @click="approveSubscription(sub.id)"
                                            :disabled="processingId === sub.id"
                                            class="h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest gap-2 disabled:opacity-70"
                                        >
                                            <Loader2 v-if="processingId === sub.id" class="size-4 animate-spin" />
                                            <CheckCircle2 v-else class="size-4" />
                                            {{ processingId === sub.id ? 'Processing...' : 'Verify & Activate' }}
                                        </Button>
                                    </div>
                                    <div v-else class="space-y-0.5">
                                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ formatDate(sub.created_at) }}</div>
                                        <div class="text-[9px] text-slate-300 font-medium">Recorded by System</div>
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

        <Dialog v-model:open="showAddModal">
            <DialogContent class="sm:max-w-[500px] p-0 border-none shadow-3xl bg-white overflow-hidden rounded-[2.5rem]">
                <div class="max-h-[90vh] overflow-y-auto custom-scrollbar">
                    <div class="p-10 space-y-8 border-b border-slate-50 bg-[#f8fafc]">
                    <div class="space-y-1">
                        <Badge variant="outline" class="bg-indigo-50 text-indigo-700 border-indigo-100 px-3 py-1 font-black uppercase text-[9px] tracking-widest">Admin Intervention</Badge>
                        <DialogTitle class="text-3xl font-black text-slate-900 tracking-tighter">Record Payment</DialogTitle>
                        <DialogDescription class="text-slate-500 font-medium pt-1">Manually authorize a subscription for a tenant business.</DialogDescription>
                    </div>

                    <div class="space-y-5">
                        <div class="space-y-2">
                            <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Participating Business</Label>
                            <select v-model="form.business_id" class="h-12 w-full px-4 rounded-xl border border-slate-200 bg-white font-bold text-sm">
                                <option value="" disabled>Select business...</option>
                                <option v-for="b in props.businesses" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Subscription Tier</Label>
                                <select v-model="form.plan_id" class="h-12 w-full px-4 rounded-xl border border-slate-200 bg-white font-bold text-sm">
                                    <option value="" disabled>Select plan...</option>
                                    <option v-for="p in props.plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Amount (KES)</Label>
                                <Input v-model="form.amount" type="number" class="h-12 rounded-xl border-slate-200 font-bold" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Start Date</Label>
                                <Input v-model="form.starts_at" type="date" class="h-12 rounded-xl border-slate-200 font-bold" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Expiry Date</Label>
                                <Input v-model="form.ends_at" type="date" class="h-12 rounded-xl border-slate-200 font-bold" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-10 flex gap-4">
                    <Button @click="showAddModal = false" variant="ghost" class="flex-1 h-14 rounded-2xl font-black uppercase tracking-widest text-[10px] text-slate-400 border-none">Cancel</Button>
                    <Button @click="submitPayment" :disabled="form.processing" class="flex-1 h-14 rounded-2xl bg-slate-900 text-white font-black uppercase tracking-widest text-[10px] shadow-xl shadow-slate-200 transition-all">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin mr-2" />
                        Authorize Sub
                    </Button>
                </div>
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
