<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
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
    Lock
} from 'lucide-vue-next'
import { ref, watch } from 'vue'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
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
            ip_address: string
            created_at: string
            user: {
                name: string
                email: string
            }
        }>
        links: any
    }
    filters: {
        search: string
    }
}>()

const search = ref(props.filters.search || '')

watch(search, debounce((val) => {
    router.get('/admin/audit-logs', { search: val }, { preserveState: true, replace: true })
}, 300))

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
    if (event.includes('auth')) return Lock
    if (event.includes('business')) return Building2
    if (event.includes('plan')) return ShieldCheck
    return Zap
}

const getEventColor = (event: string) => {
    if (event.includes('auth')) return 'text-indigo-600'
    if (event.includes('business')) return 'text-blue-600'
    if (event.includes('plan')) return 'text-emerald-600'
    return 'text-slate-600'
}
</script>

<template>
    <Head title="System Audit Trails" />

    <AdminLayout>
        <div class="space-y-8">
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight flex items-center gap-2">
                         <Activity class="h-6 w-6 text-slate-700" />
                         System Audit Trails
                    </h1>
                    <p class="text-slate-500 text-sm">Chronological record of all critical administrative and security events.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/30">
                    <div class="relative max-w-md">
                        <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <Input
                            v-model="search"
                            placeholder="Search logs by event, description, or user..."
                            class="pl-10 h-10 border-slate-200 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <Table>
                    <TableHeader class="bg-slate-50">
                        <TableRow>
                            <TableHead class="font-bold text-slate-700">Timestamp</TableHead>
                            <TableHead class="font-bold text-slate-700">Initiator (User)</TableHead>
                            <TableHead class="font-bold text-slate-700">System Event</TableHead>
                            <TableHead class="font-bold text-slate-700">Description</TableHead>
                            <TableHead class="font-bold text-slate-700">Connectivity</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="log in props.logs.data" :key="log.id" class="hover:bg-slate-50/50 transition-colors">
                            <TableCell class="py-4">
                                <div class="flex items-center gap-2 text-slate-500 font-medium whitespace-nowrap">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ formatDate(log.created_at) }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center border border-slate-200">
                                        <UserIcon class="h-4 w-4 text-slate-500" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 text-xs">{{ log.user?.name || 'System' }}</div>
                                        <div class="text-[10px] text-slate-400 font-medium">{{ log.user?.email || '-' }}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <component :is="getEventIcon(log.event)" class="h-4 w-4" :class="getEventColor(log.event)" />
                                    <span class="font-black text-[10px] uppercase tracking-wider" :class="getEventColor(log.event)">
                                        {{ log.event.replace('.', ' ') }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="max-w-md">
                                <p class="text-xs text-slate-600 font-medium leading-relaxed">{{ log.description }}</p>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2 text-slate-400">
                                    <Globe class="h-3.5 w-3.5" />
                                    <span class="text-[10px] font-bold">{{ log.ip_address }}</span>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="props.logs.data.length === 0">
                            <TableCell colspan="5" class="h-64 text-center">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <Activity class="h-12 w-12 text-slate-100" />
                                    <p class="text-slate-400 font-medium">No system events logged matching your criteria.</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AdminLayout>
</template>
