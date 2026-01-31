<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Building2,
    Users,
    ShoppingBag,
    CreditCard,
    ShieldAlert,
    KeyRound,
    Wrench,
    ArrowLeft,
    Mail,
    Phone,
    MapPin,
    Calendar,
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

const props = defineProps<{
    business: {
        id: number
        name: string
        email: string
        phone: string
        address: string
        is_active: boolean
        created_at: string
        plan_id: number
        plan?: {
            name: string
            price_monthly: number
        }
        users: Array<{
            id: number
            name: string
            email: string
            created_at: string
            role_name?: string
            pivot?: {
                role_name?: string
            }
        }>
        subscriptions: Array<{
            id: number
            plan_name: string
            status: string
            starts_at: string
            ends_at: string
        }>
    }
    payments: Array<{
        id: number
        amount: number
        currency: string
        status: string
        mpesa_receipt: string
        created_at: string
        payment_method: string
    }>
    features: string[]
}>()

const formatDate = (date: string | null) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const formatCurrency = (amount: number, currency: string = 'KES') => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: currency,
    }).format(amount)
}

const toggleStatus = async () => {
    const isSuspending = props.business.is_active
    let reason = null
    
    if (isSuspending) {
        reason = prompt('Reason for suspension (Optional):', '')
        if (reason === null) return // User cancelled
    } else {
        if (!confirm('Are you sure you want to activate this business account?')) return
    }

    await router.post(`/admin/businesses/${props.business.id}/toggle-status`, { reason })
}

const resetPassword = async () => {
    if (!confirm('This will reset the main admin password to "Password123!". Continue?')) return
    await router.post(`/admin/businesses/${props.business.id}/reset-password`)
}

const impersonate = async () => {
    const reason = prompt('Reason for impersonation (Log for audit):', 'Customer support/debugging');
    if (reason === null) return; // User cancelled
    
    if (!confirm(`You are about to log in as an administrator for ${props.business.name}. This action will be notified to the business. Continue?`)) return;
    
    await router.post(`/admin/businesses/${props.business.id}/impersonate`, { reason });
}
</script>

<template>
    <Head :title="`Business: ${business.name}`" />

    <AdminLayout>
        <div class="space-y-8 max-w-[95%] mx-auto pb-12">
            <!-- Header & Actions -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex items-center gap-5">
                    <Link href="/admin/businesses" class="p-3 hover:bg-slate-100 rounded-2xl transition-all bg-white shadow-sm border border-slate-100 group">
                        <ArrowLeft class="h-6 w-6 text-slate-500 group-hover:-translate-x-1 transition-transform" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-4">
                            <h1 class="text-4xl font-black text-slate-900 tracking-tight">{{ business.name }}</h1>
                            <Badge :class="business.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100'" class="rounded-xl px-4 py-1.5 text-xs font-black uppercase tracking-widest border">
                                {{ business.is_active ? 'Active' : 'Suspended' }}
                            </Badge>
                        </div>
                        <div class="flex items-center gap-3 mt-2">
                             <div class="flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 rounded-lg border border-slate-200 shadow-sm">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">ID</span>
                                <span class="text-xs font-bold text-slate-700 leading-none">{{ business.id }}</span>
                             </div>
                             <span class="text-slate-300">/</span>
                             <div class="flex items-center gap-1.5 text-slate-500 font-semibold text-sm">
                                <Calendar class="h-4 w-4 text-slate-400" />
                                <span>Joined {{ formatDate(business.created_at) }}</span>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" @click="router.get('/admin/bulk-email', { ids: [business.id] })" class="h-12 px-6 border-blue-100 text-blue-700 hover:bg-blue-50 font-black rounded-2xl shadow-sm transition-all active:scale-95">
                        <Mail class="h-4 w-4 mr-2" />
                        Send Message
                    </Button>
                    <Button variant="outline" @click="impersonate" class="h-12 px-6 border-indigo-100 text-indigo-700 hover:bg-indigo-50 font-black rounded-2xl shadow-sm transition-all active:scale-95">
                        <Wrench class="h-4 w-4 mr-2" />
                        Impersonate
                    </Button>
                    <Button variant="outline" @click="resetPassword" class="h-12 px-6 border-amber-100 text-amber-700 hover:bg-amber-50 font-black rounded-2xl shadow-sm transition-all active:scale-95">
                        <KeyRound class="h-4 w-4 mr-2" />
                        Reset Admin
                    </Button>
                    <Button 
                        :class="business.is_active ? 'bg-red-600 hover:bg-red-700 shadow-red-200' : 'bg-slate-900 hover:bg-black shadow-slate-200'"
                        class="h-12 px-8 text-white font-black rounded-2xl shadow-xl transition-all active:scale-95"
                        @click="toggleStatus"
                    >
                        <ShieldAlert class="h-4 w-4 mr-2" />
                        {{ business.is_active ? 'Suspend Account' : 'Activate Account' }}
                    </Button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl hover:shadow-blue-500/5 transition-all">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <Building2 class="h-24 w-24 text-blue-600" />
                    </div>
                    <div class="relative flex flex-col gap-4">
                        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center border border-blue-100">
                            <Building2 class="h-7 w-7 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Current Plan</p>
                            <p class="text-2xl font-black text-slate-900">{{ business.plan?.name || 'No Plan Active' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl hover:shadow-emerald-500/5 transition-all">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <Users class="h-24 w-24 text-emerald-600" />
                    </div>
                    <div class="relative flex flex-col gap-4">
                        <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center border border-emerald-100">
                            <Users class="h-7 w-7 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Operational Users</p>
                            <p class="text-2xl font-black text-slate-900">{{ business.users.length }} Active Seats</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl hover:shadow-amber-500/5 transition-all">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <CreditCard class="h-24 w-24 text-amber-600" />
                    </div>
                    <div class="relative flex flex-col gap-4">
                        <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center border border-amber-100">
                            <CreditCard class="h-7 w-7 text-amber-600" />
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Lifetime Revenue</p>
                            <p class="text-2xl font-black text-slate-900">{{ formatCurrency(payments.reduce((acc, p) => acc + (p.status === 'completed' ? p.amount : 0), 0)) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl hover:shadow-indigo-500/5 transition-all">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <ShoppingBag class="h-24 w-24 text-indigo-600" />
                    </div>
                    <div class="relative flex flex-col gap-4">
                        <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center border border-indigo-100">
                            <ShoppingBag class="h-7 w-7 text-indigo-600" />
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">SaaS Status</p>
                            <p class="text-2xl font-black text-slate-900">Provisioned</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Left Sidebar: Contact & Features -->
                <div class="lg:col-span-4 space-y-8">
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50">
                        <h3 class="text-lg font-black text-slate-900 mb-6 flex items-center gap-2">
                            <Phone class="h-5 w-5 text-blue-600" />
                            Business Identity
                        </h3>
                        <div class="space-y-6">
                            <div class="flex items-start gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <Mail class="h-5 w-5 text-slate-400 mt-1" />
                                <div class="min-w-0">
                                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Primary Email</p>
                                    <p class="text-slate-900 font-bold break-all">{{ business.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <Phone class="h-5 w-5 text-slate-400 mt-1" />
                                <div>
                                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Phone Line</p>
                                    <p class="text-slate-900 font-bold">{{ business.phone || 'Not Registered' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <MapPin class="h-5 w-5 text-slate-400 mt-1" />
                                <div>
                                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Home Office</p>
                                    <p class="text-slate-900 font-bold leading-relaxed">{{ business.address || 'Address not logged' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50">
                        <h3 class="text-lg font-black text-slate-900 mb-6 flex items-center gap-2">
                            <KeyRound class="h-5 w-5 text-emerald-600" />
                            Active Privileges
                        </h3>
                        <div class="flex flex-wrap gap-2.5">
                            <template v-if="features.length > 0">
                                <Badge v-for="f in features" :key="f" variant="secondary" class="bg-white text-slate-700 border border-slate-200 px-4 py-2 rounded-xl font-bold capitalize shadow-sm hover:border-blue-400 transition-colors">
                                    {{ f.replace('_', ' ') }}
                                </Badge>
                            </template>
                            <div v-else class="w-full py-8 flex flex-col items-center justify-center border-2 border-dashed border-slate-100 rounded-2xl text-slate-400">
                                <ShieldAlert class="h-8 w-8 mb-2 opacity-20" />
                                <p class="text-xs font-bold uppercase tracking-widest">Base Subscription Only</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Content Area -->
                <div class="lg:col-span-8 space-y-8">
                    <!-- Payments Section -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/30 flex justify-between items-center">
                            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                                <CreditCard class="h-5 w-5 text-amber-600" />
                                Financial Ledger
                            </h3>
                            <Badge variant="outline" class="bg-white border-slate-200 font-black text-[10px] uppercase tracking-widest px-3">
                                {{ payments.length }} Records
                            </Badge>
                        </div>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="pl-8 font-black uppercase text-[10px] tracking-widest">Transaction Date</TableHead>
                                        <TableHead class="font-black uppercase text-[10px] tracking-widest">Reference</TableHead>
                                        <TableHead class="font-black uppercase text-[10px] tracking-widest">Net Amount</TableHead>
                                        <TableHead class="font-black uppercase text-[10px] tracking-widest">Channel</TableHead>
                                        <TableHead class="text-right pr-8 font-black uppercase text-[10px] tracking-widest">State</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="payment in payments" :key="payment.id" class="hover:bg-slate-50/80 transition-colors">
                                        <TableCell class="pl-8 text-slate-900 font-bold py-5">{{ formatDate(payment.created_at) }}</TableCell>
                                        <TableCell class="font-mono text-xs font-bold text-slate-500">{{ payment.mpesa_receipt || 'SYS-TX-'+payment.id }}</TableCell>
                                        <TableCell class="font-black text-slate-900">{{ formatCurrency(payment.amount, payment.currency) }}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="capitalize font-bold text-[10px] bg-white text-slate-600 border-slate-200">
                                                {{ payment.payment_method }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right pr-8">
                                            <Badge 
                                                :class="payment.status === 'completed' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-amber-100 text-amber-700 border-amber-200'"
                                                class="rounded-xl text-[10px] font-black uppercase px-3 py-1 border"
                                            >
                                                {{ payment.status }}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="payments.length === 0">
                                        <TableCell colspan="5" class="py-24 text-center">
                                            <div class="flex flex-col items-center justify-center text-slate-300">
                                                <CreditCard class="h-12 w-12 mb-3 opacity-20" />
                                                <p class="text-sm font-black uppercase tracking-widest opacity-40 italic">No Financial Records Found</p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <!-- Platform Users Section -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/30 flex justify-between items-center">
                            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                                <Users class="h-5 w-5 text-indigo-600" />
                                Workspace Personnel
                            </h3>
                            <Badge variant="outline" class="bg-white border-slate-200 font-black text-[10px] uppercase tracking-widest px-3">
                                {{ business.users.length }} Staff
                            </Badge>
                        </div>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="pl-8 font-black uppercase text-[10px] tracking-widest py-4">Identity</TableHead>
                                        <TableHead class="font-black uppercase text-[10px] tracking-widest">Credential</TableHead>
                                        <TableHead class="font-black uppercase text-[10px] tracking-widest">Authority</TableHead>
                                        <TableHead class="text-right pr-8 font-black uppercase text-[10px] tracking-widest">Onboarded</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="user in business.users" :key="user.id" class="hover:bg-slate-50/80 transition-colors">
                                        <TableCell class="pl-8 py-5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center font-black text-indigo-700 text-xs">
                                                    {{ user.name.substring(0, 2).toUpperCase() }}
                                                </div>
                                                <p class="font-black text-slate-900 uppercase text-xs tracking-tight">{{ user.name }}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-slate-500 font-bold text-sm">{{ user.email }}</TableCell>
                                        <TableCell>
                                            <Badge :class="user.role_name === 'admin' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-700 border-slate-200'" class="font-black text-[10px] uppercase tracking-widest px-3 py-1 border rounded-xl">
                                                {{ user.role_name || 'Staff' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right pr-8 text-slate-400 font-bold text-xs">{{ formatDate(user.created_at) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
