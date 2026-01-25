<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Building2,
    Users,
    DollarSign,
    TrendingUp,
    ArrowUpRight,
    History,
    CheckCircle2,
    XCircle,
    CreditCard,
    MessageSquare,
    Monitor,
    ShieldCheck
} from 'lucide-vue-next'

const props = defineProps<{
    stats: {
        total_businesses: number
        active_businesses: number
        total_revenue: number
        total_users: number
        total_subscribers: number
    }
    recent_subscriptions: Array<{
        id: number
        plan_name: string
        amount: number
        status: string
        created_at: string
        business?: {
            name: string
        }
    }>
}>()

const recentSubs = computed(() => props.recent_subscriptions ?? [])

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(amount)
}
</script>

<template>
    <Head title="Platform Dashboard" />

    <AdminLayout>
        <div class="space-y-8">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="border-none shadow-sm bg-white hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Total Revenue</CardTitle>
                        <div class="p-2 bg-emerald-100 rounded-lg text-emerald-600">
                            <DollarSign class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-slate-900">{{ formatCurrency(props.stats.total_revenue) }}</div>
                        <p class="text-xs text-slate-500 mt-1 flex items-center">
                            <TrendingUp class="h-3 w-3 mr-1 text-emerald-500" />
                            From {{ props.stats.total_subscribers }} active plans
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Businesses</CardTitle>
                        <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                            <Building2 class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-slate-900">{{ props.stats.total_businesses }}</div>
                        <p class="text-xs text-slate-500 mt-1 italic">
                            {{ props.stats.active_businesses }} currently active
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Total Users</CardTitle>
                        <div class="p-2 bg-indigo-100 rounded-lg text-indigo-600">
                            <Users class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-slate-900">{{ props.stats.total_users }}</div>
                        <p class="text-xs text-slate-500 mt-1">Across all business units</p>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-indigo-600 text-white">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2 text-white/80">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider">Active Subscription Rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-white">
                            {{ props.stats.total_businesses > 0 ? ((props.stats.total_subscribers / props.stats.total_businesses) * 100).toFixed(1) : 0 }}%
                        </div>
                        <p class="text-xs text-white/60 mt-1 italic">Conversion to paid plans</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Subscriptions -->
                <Card class="lg:col-span-2 border-none shadow-sm bg-white">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-lg font-bold flex items-center gap-2 text-slate-900">
                                    <History class="h-5 w-5 text-indigo-600" />
                                    Recent Payments
                                </CardTitle>
                                <CardDescription>Latest subscription activity across the platform</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" class="text-indigo-600 font-bold hover:bg-indigo-50">
                                View History
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!recentSubs.length" class="py-12 text-center">
                            <CreditCard class="h-12 w-12 text-slate-300 mx-auto mb-4" />
                            <p class="text-slate-500 font-medium">No subscription payments recorded yet.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="sub in recentSubs" :key="sub.id" class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 hover:border-indigo-100 transition-colors">
                                <div class="flex items-center gap-4">
                                     <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border-2 border-indigo-200">
                                        {{ (sub.business?.name || '??').substring(0, 2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ sub.business?.name || 'Deleted Business' }}</div>
                                        <div class="text-xs text-slate-500 font-medium">{{ sub.plan_name }} • {{ formatCurrency(sub.amount) }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <Badge
                                        :class="(sub.status || '') === 'active' ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20' : 'bg-red-500/10 text-red-600'"
                                        variant="secondary"
                                        class="px-3 py-1 font-bold border-none"
                                    >
                                        {{ (sub.status || '').toUpperCase() }}
                                    </Badge>
                                    <div class="text-[10px] text-slate-400 font-bold mt-1 uppercase">{{ new Date(sub.created_at).toLocaleDateString() }}</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quick Actions -->
                <Card class="border-none shadow-sm bg-white">
                    <CardHeader>
                        <CardTitle class="text-lg font-bold text-slate-900 leading-tight">Platform Actions</CardTitle>
                        <CardDescription>Common administrator tasks</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Link href="/admin/cms">
                            <Button variant="outline" class="w-full justify-start py-6 border-slate-200 hover:border-indigo-600 hover:bg-indigo-50 group">
                                <div class="w-8 h-8 rounded bg-slate-100 group-hover:bg-indigo-100 mr-3 flex items-center justify-center transition-colors">
                                    <MessageSquare class="h-4 w-4 text-slate-600 group-hover:text-indigo-600" />
                                </div>
                                <div class="text-left">
                                    <span class="block text-sm font-bold text-slate-900 leading-none">Global Broadcast</span>
                                    <span class="text-[10px] text-slate-500">Alert all businesses</span>
                                </div>
                            </Button>
                        </Link>
                        <Link href="/admin/cms">
                            <Button variant="outline" class="w-full justify-start py-6 border-slate-200 hover:border-blue-600 hover:bg-blue-50 group">
                                <div class="w-8 h-8 rounded bg-slate-100 group-hover:bg-blue-100 mr-3 flex items-center justify-center transition-colors">
                                    <Monitor class="h-4 w-4 text-slate-600 group-hover:text-blue-600" />
                                </div>
                                <div class="text-left">
                                    <span class="block text-sm font-bold text-slate-900 leading-none">CMS Editor</span>
                                    <span class="text-[10px] text-slate-500">Manage landing page</span>
                                </div>
                            </Button>
                        </Link>
                        <Link href="/admin/audit-logs">
                            <Button variant="outline" class="w-full justify-start py-6 border-slate-200 hover:border-red-600 hover:bg-red-50 group">
                                <div class="w-8 h-8 rounded bg-slate-100 group-hover:bg-red-100 mr-3 flex items-center justify-center transition-colors">
                                    <ShieldCheck class="h-4 w-4 text-slate-600 group-hover:text-red-600" />
                                </div>
                                <div class="text-left">
                                    <span class="block text-sm font-bold text-slate-900 leading-none">Security Log</span>
                                    <span class="text-[10px] text-slate-500">View platform audits</span>
                                </div>
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
