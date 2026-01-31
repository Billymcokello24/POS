<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { 
    Activity, 
    Search, 
    Clock, 
    User as UserIcon, 
    Globe, 
    ShieldCheck, 
    Zap,
    Building2,
    Lock,
    SearchX,
    Server,
    Fingerprint,
    Calendar,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal
} from 'lucide-vue-next'
import { ref, watch, computed, onUnmounted } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
    logs: {
        data: Array<{
            id: number
            event: string
            description: string
            metadata: any
            ip_address: string
            created_at: string
            user: {
                name: string
                email: string
                is_super_admin: boolean
            }
        }>
        links: Array<{ url: string | null, label: string, active: boolean }>
        current_page: number
        last_page: number
        from: number
        to: number
        total: number
        per_page: number
    }
    filters: {
        search: string
        event: string
        user_id: string
        date_from: string
        date_to: string
    }
    eventTypes: string[]
    admins: Array<{ id: number, name: string, email: string }>
}>()

const filters = ref({
    search: props.filters.search || '',
    event: props.filters.event || '',
    user_id: props.filters.user_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
})

const debouncedFilter = debounce((val) => {
    // Sanitize _all placeholder values to empty strings for backend
    const cleanedFilters = Object.fromEntries(
        Object.entries(val).map(([k, v]) => [k, v === '_all' ? '' : v])
    ) as Record<string, string>
    router.get('/admin/audit-logs', cleanedFilters, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    })
}, 400)

// Monitor all navigation to kill filter updates if we're leaving
const stopNavigationGuard = router.on('before', (event) => {
    if (event.detail.visit.url.pathname !== '/admin/audit-logs') {
        debouncedFilter.cancel()
    }
})

watch(filters, (val) => {
    debouncedFilter(val)
}, { deep: true })

onUnmounted(() => {
    debouncedFilter.cancel()
    stopNavigationGuard()
})

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-KE', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEventIcon = (event: string) => {
    const e = event.toLowerCase()
    if (e.includes('auth') || e.includes('login') || e.includes('password')) return Lock
    if (e.includes('business') || e.includes('company')) return Building2
    if (e.includes('plan') || e.includes('subscription') || e.includes('payment')) return ShieldCheck
    if (e.includes('user') || e.includes('admin')) return UserIcon
    return Zap
}

const getEventColor = (event: string) => {
    const e = event.toLowerCase()
    if (e.includes('deleted') || e.includes('removed') || e.includes('failed')) return 'red'
    if (e.includes('created') || e.includes('added') || e.includes('success')) return 'emerald'
    if (e.includes('updated') || e.includes('modified') || e.includes('reset')) return 'amber'
    if (e.includes('auth') || e.includes('login')) return 'indigo'
    return 'slate'
}

const getBgClass = (color: string) => {
    const mapping: Record<string, string> = {
        red: 'bg-red-50 border-red-100 text-red-600',
        emerald: 'bg-emerald-50 border-emerald-100 text-emerald-600',
        amber: 'bg-amber-50 border-amber-100 text-amber-600',
        indigo: 'bg-indigo-50 border-indigo-100 text-indigo-600',
        slate: 'bg-slate-50 border-slate-100 text-slate-600'
    }
    return mapping[color] || mapping.slate
}

const getTextClass = (color: string) => {
    const mapping: Record<string, string> = {
        red: 'text-red-600',
        emerald: 'text-emerald-600',
        amber: 'text-amber-600',
        indigo: 'text-indigo-600',
        slate: 'text-slate-600'
    }
    return mapping[color] || mapping.slate
}

const stats = computed(() => {
    const data = props.logs.data
    return [
        { label: 'Security Events', count: data.filter(l => l.event.includes('auth')).length, icon: ShieldCheck, color: 'indigo' },
        { label: 'Active Traces', count: props.logs.total, icon: Activity, color: 'emerald' },
        { label: 'Admin Actions', count: data.filter(l => l.user?.is_super_admin).length, icon: Globe, color: 'amber' }
    ]
})

const clearFilters = () => {
    Object.assign(filters.value, {
        search: '',
        event: '',
        user_id: '',
        date_from: '',
        date_to: '',
    })
}
</script>

<template>
    <Head title="System Audit Trails" />

    <AdminLayout>
        <div class="space-y-8 max-w-[90%] mx-auto pb-12">
            <!-- Premium Header -->
            <div class="relative overflow-hidden bg-white rounded-[2.5rem] p-8 md:p-10 shadow-2xl border border-slate-100 mb-8">
                <div class="absolute top-0 right-0 p-12 opacity-5 blur-3xl flex gap-4 pointer-events-none">
                    <div class="w-48 h-48 bg-slate-900 rounded-full"></div>
                </div>

                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">
                            <Fingerprint class="h-3 w-3" />
                            Authority Context
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            <Activity class="h-8 w-8 text-slate-900" />
                            System Audit Trails
                        </h1>
                        <p class="text-slate-500 font-medium max-w-xl text-sm md:text-base leading-relaxed">
                            A secure, immutable record of administrative actions and security-critical system events across the platform.
                        </p>
                    </div>

                    <div class="shrink-0 flex items-center gap-4">
                        <div class="h-20 w-20 rounded-[2.5rem] bg-slate-900 flex items-center justify-center shadow-2xl rotate-3">
                            <Activity class="h-10 w-10 text-white" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div v-for="stat in stats" :key="stat.label" class="bg-white p-6 rounded-[2.5rem] shadow-xl border border-slate-50 group hover:border-slate-200 transition-all cursor-default">
                    <div class="flex items-center justify-between">
                        <div :class="['h-12 w-12 rounded-2xl flex items-center justify-center border', getBgClass(stat.color)]">
                            <component :is="stat.icon" class="h-6 w-6" />
                        </div>
                        <span class="text-3xl font-black text-slate-900 tracking-tighter">{{ stat.count }}</span>
                    </div>
                    <div class="mt-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">{{ stat.label }}</div>
                        <div class="text-xs text-slate-500 font-medium mt-1 italic">Real-time telemetry</div>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters Bar -->
            <div class="bg-white rounded-[2.5rem] p-6 shadow-xl border border-slate-100 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[300px] space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Search Traces</Label>
                    <div class="relative group">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-slate-900 transition-colors" />
                        <Input 
                            v-model="filters.search"
                            placeholder="Event, description, or initiator..."
                            class="h-12 w-full pl-10 bg-slate-50 border-slate-100 rounded-2xl font-bold focus:ring-2 focus:ring-slate-900 transition-all"
                        />
                    </div>
                </div>

                <div class="w-full md:w-48 space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Event Type</Label>
                    <Select v-model="filters.event">
                        <SelectTrigger class="h-12 bg-slate-50 border-slate-100 rounded-2xl font-bold">
                            <SelectValue placeholder="All Events" />
                        </SelectTrigger>
                        <SelectContent class="rounded-2xl border-none shadow-2xl">
                            <SelectItem value="_all">All Events</SelectItem>
                            <SelectItem v-for="type in eventTypes" :key="type" :value="type" class="font-bold">
                                {{ type.split('.').pop()?.replace('_', ' ')?.toUpperCase() }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full md:w-48 space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Initiator</Label>
                    <Select v-model="filters.user_id">
                        <SelectTrigger class="h-12 bg-slate-50 border-slate-100 rounded-2xl font-bold">
                            <SelectValue placeholder="All Admins" />
                        </SelectTrigger>
                        <SelectContent class="rounded-2xl border-none shadow-2xl">
                            <SelectItem value="_all">All Admins</SelectItem>
                            <SelectItem v-for="admin in admins" :key="admin.id" :value="String(admin.id)" class="font-bold">
                                {{ admin.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full md:w-40 space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">From Date</Label>
                    <Input type="date" v-model="filters.date_from" class="h-12 bg-slate-50 border-slate-100 rounded-2xl font-bold" />
                </div>

                <div class="w-full md:w-40 space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">To Date</Label>
                    <Input type="date" v-model="filters.date_to" class="h-12 bg-slate-50 border-slate-100 rounded-2xl font-bold" />
                </div>

                <Button 
                    variant="ghost" 
                    class="h-12 px-4 rounded-2xl text-slate-400 hover:text-red-500 font-bold text-xs uppercase tracking-widest shrink-0"
                    @click="clearFilters"
                >
                    Clear
                </Button>
            </div>

            <!-- Enhanced Audit Table -->
            <div class="space-y-6">
                <div class="bg-white rounded-[3rem] shadow-2xl border border-slate-50 overflow-hidden">
                    <Table>
                        <TableHeader class="bg-white">
                            <TableRow class="hover:bg-transparent border-slate-100 h-20">
                                <TableHead class="font-black text-[10px] uppercase tracking-widest text-slate-400 px-8">Initiator Identity</TableHead>
                                <TableHead class="font-black text-[10px] uppercase tracking-widest text-slate-400 px-4">Event Type</TableHead>
                                <TableHead class="font-black text-[10px] uppercase tracking-widest text-slate-400 px-4">Action Log</TableHead>
                                <TableHead class="font-black text-[10px] uppercase tracking-widest text-slate-400 text-right pr-8">Temporal Signature</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="props.logs.data.length > 0">
                                <TableRow v-for="log in props.logs.data" :key="log.id" class="group border-b border-slate-50 hover:bg-slate-50/10">
                                    <TableCell class="py-6 px-8">
                                        <div class="flex items-center gap-3">
                                            <div :class="['w-10 h-10 rounded-2xl flex items-center justify-center border transition-colors', log.user?.is_super_admin ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200 group-hover:bg-white']">
                                                <UserIcon :class="['h-5 w-5', log.user?.is_super_admin ? 'text-white' : 'text-slate-500']" />
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <div class="font-black text-slate-900 text-sm tracking-tight leading-none">{{ log.user?.name || 'System Authority' }}</div>
                                                    <Badge v-if="log.user?.is_super_admin" variant="outline" class="text-[7px] font-black uppercase tracking-tighter bg-slate-900 text-white border-none py-0 h-3">SuperAdmin</Badge>
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-tighter">{{ log.user?.email || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2.5">
                                            <div :class="['h-8 w-8 rounded-lg flex items-center justify-center border', getBgClass(getEventColor(log.event))]">
                                                <component :is="getEventIcon(log.event)" class="h-4 w-4" />
                                            </div>
                                            <span class="font-black text-[10px] uppercase tracking-widest whitespace-nowrap" :class="getTextClass(getEventColor(log.event))">
                                                {{ log.event.split('.').pop()?.replace('_', ' ') }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-6 px-4 min-w-[300px] max-w-xl">
                                        <div class="space-y-3">
                                            <p class="text-xs text-slate-600 font-bold leading-relaxed break-words">
                                                {{ log.description }}
                                            </p>
                                            <!-- @ts-ignore -->
                                            <div v-if="log.metadata && Object.keys(log.metadata).length > 0" class="flex flex-wrap gap-1.5">
                                                <!-- @ts-ignore -->
                                                <div v-for="(val, key) in log.metadata" :key="key" class="flex items-center gap-1.5 px-2 py-1 bg-slate-50 rounded-lg border border-slate-100 text-[8px]">
                                                    <span class="font-black text-slate-400 uppercase tracking-widest">{{ key }}:</span>
                                                    <span class="font-bold text-slate-900">{{ typeof val === 'object' ? '...' : val }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right pr-8">
                                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100 group-hover:bg-white transition-colors">
                                            <Clock class="h-3 w-3 text-slate-400" />
                                            <span class="text-[10px] font-black text-slate-900 tracking-tight tabular-nums whitespace-nowrap">{{ formatDate(log.created_at) }}</span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else>
                                <TableCell colspan="4" class="h-64 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="h-16 w-16 rounded-[2.5rem] bg-slate-50 flex items-center justify-center border-2 border-dashed border-slate-200">
                                            <SearchX class="h-8 w-8 text-slate-200" />
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-slate-900 font-black text-lg">No trails found</p>
                                            <p class="text-slate-400 font-medium text-sm">Refine your search parameters to locate specific logs.</p>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Premium Pagination -->
                <div v-if="props.logs.last_page > 1" class="flex flex-col md:flex-row items-center justify-between gap-6 px-10 py-8 bg-white rounded-[2.5rem] shadow-xl border border-slate-50">
                    <div class="flex items-center gap-4">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Journal Volume</span>
                            <span class="text-sm font-black text-slate-900 mt-1">
                                Showing {{ props.logs.from }}-{{ props.logs.to }} of {{ props.logs.total }} entries
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <template v-for="(link, key) in props.logs.links" :key="key">
                            <div v-if="link.url === null" 
                                class="h-10 w-10 flex items-center justify-center text-slate-300 pointer-events-none"
                            >
                                <ChevronLeft v-if="link.label.includes('Previous')" class="h-4 w-4" />
                                <ChevronRight v-if="link.label.includes('Next')" class="h-4 w-4" />
                                <span v-else v-html="link.label"></span>
                            </div>

                            <Link
                                v-else
                                :href="link.url"
                                class="h-10 min-w-[40px] px-2 flex items-center justify-center rounded-xl text-xs font-black transition-all"
                                :class="[
                                    link.active 
                                        ? 'bg-slate-900 text-white shadow-lg scale-110' 
                                        : 'bg-slate-50 text-slate-500 hover:bg-slate-200 hover:text-slate-900'
                                ]"
                            >
                                <span v-if="!link.label.includes('Previous') && !link.label.includes('Next')" v-html="link.label"></span>
                                <ChevronLeft v-if="link.label.includes('Previous')" class="h-4 w-4" />
                                <ChevronRight v-if="link.label.includes('Next')" class="h-4 w-4" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
