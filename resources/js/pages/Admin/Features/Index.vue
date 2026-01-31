<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { 
    Wrench, 
    Check, 
    X, 
    ShieldCheck, 
    HelpCircle, 
    Loader2, 
    Building2, 
    Settings2,
    Zap,
    Cpu,
    Activity
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
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
    features: Array<{
        id: number
        name: string
        key: string
        description: string
    }>
    businesses: {
        data: Array<{
            id: number
            name: string
            features: Array<{
                id: number
                pivot: { is_enabled: boolean }
            }>
        }>
        meta: any
        links: any
    }
}>()

const toggling = ref<string | null>(null)

const toggleFeature = (businessId: number, featureId: number, currentState: boolean) => {
    const key = `${businessId}-${featureId}`
    toggling.value = key
    
    router.post('/admin/features/toggle', {
        business_id: businessId,
        feature_id: featureId,
        is_enabled: !currentState
    }, {
        onFinish: () => { toggling.value = null },
        preserveScroll: true
    })
}

const isEnabled = (business: any, featureId: number) => {
    const f = business.features.find((feat: any) => feat.id === featureId)
    return f ? f.pivot.is_enabled : false
}

const stats = computed(() => {
    let totalEnabled = 0
    props.businesses.data.forEach(b => {
        b.features.forEach(f => {
            if (f.pivot.is_enabled) totalEnabled++
        })
    })
    
    return [
        { label: 'Platform Modules', count: props.features.length, icon: Cpu, color: 'indigo' },
        { label: 'Active Deployments', count: totalEnabled, icon: Activity, color: 'blue' },
        { label: 'Merchant Contexts', count: props.businesses.data.length, icon: Building2, color: 'emerald' }
    ]
})

const getBgClass = (color: string) => {
    const mapping: Record<string, string> = {
        indigo: 'bg-indigo-50 text-indigo-600 border-indigo-100/50',
        blue: 'bg-blue-50 text-blue-600 border-blue-100/50',
        emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100/50',
    }
    return mapping[color] || 'bg-slate-50 text-slate-600'
}
</script>

<template>
    <Head title="Module Management" />

    <AdminLayout>
        <div class="space-y-8 max-w-[90%] mx-auto pb-12">
            <!-- Premium Header -->
            <div class="relative overflow-hidden bg-slate-900 rounded-[2.5rem] p-8 md:p-12 shadow-2xl border border-slate-800">
                <div class="absolute top-0 right-0 p-12 opacity-10 blur-2xl flex gap-4 pointer-events-none">
                    <div class="w-32 h-32 bg-blue-500 rounded-full"></div>
                    <div class="w-48 h-48 bg-indigo-500 rounded-full translate-y-12"></div>
                </div>

                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[10px] font-black uppercase tracking-widest">
                            <Settings2 class="h-3 w-3" />
                            Granular Access
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight flex items-center gap-3">
                            Module Management
                        </h1>
                        <p class="text-slate-400 font-medium max-w-xl text-sm md:text-base leading-relaxed">
                            Fine-tune the platform experience by enabling or disabling specific functional modules for each business context.
                        </p>
                    </div>

                    <div class="shrink-0">
                         <div class="h-20 w-20 rounded-[2rem] bg-white/5 border border-white/10 flex items-center justify-center backdrop-blur-xl">
                            <Wrench class="h-10 w-10 text-blue-400" />
                         </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div v-for="stat in stats" :key="stat.label" class="bg-white p-6 rounded-[2rem] shadow-xl border border-slate-50 group hover:border-blue-100 transition-all active:scale-95 cursor-default">
                    <div class="flex items-center justify-between">
                        <div :class="['h-12 w-12 rounded-2xl flex items-center justify-center border', getBgClass(stat.color)]">
                            <component :is="stat.icon" class="h-6 w-6" />
                        </div>
                        <span class="text-3xl font-black text-slate-900 tracking-tighter">{{ stat.count }}</span>
                    </div>
                    <div class="mt-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">{{ stat.label }}</div>
                        <div class="text-xs text-slate-500 font-medium mt-1">Platform-wide statistics</div>
                    </div>
                </div>
            </div>

            <!-- Main Control Grid -->
            <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-100 bg-slate-50/10 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                        <Zap class="h-5 w-5 text-amber-500" />
                        Deployment Matrix
                    </h3>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-full border border-slate-100">
                        {{ props.businesses.data.length }} Merchants Loaded
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50/50">
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="font-black text-[10px] uppercase tracking-widest text-slate-400 h-16 px-8 min-w-[300px]">Merchant Identity</TableHead>
                                <TableHead v-for="feat in props.features" :key="feat.id" class="text-center font-black text-[10px] uppercase tracking-widest text-slate-400 h-16 px-4">
                                    <div class="flex flex-col items-center">
                                        <span>{{ feat.name }}</span>
                                        <Badge variant="outline" class="text-[8px] font-black tracking-tighter mt-1 bg-white border-slate-200">{{ feat.key }}</Badge>
                                    </div>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="business in props.businesses.data" :key="business.id" class="group transition-colors border-slate-50 hover:bg-slate-50/40">
                                <TableCell class="py-6 px-8">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-900 flex items-center justify-center text-white font-black border border-slate-800 shadow-lg group-hover:scale-110 transition-transform uppercase text-xs">
                                            {{ business.name.substring(0, 2) }}
                                        </div>
                                        <div>
                                            <div class="font-black text-slate-900 text-sm tracking-tight leading-none">{{ business.name }}</div>
                                            <div class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-tighter">Business Context #{{ business.id }}</div>
                                        </div>
                                    </div>
                                </TableCell>
                                
                                <TableCell v-for="feat in props.features" :key="feat.id" class="text-center border-l border-slate-50/50 p-0">
                                    <div class="flex justify-center items-center h-full min-h-[80px]">
                                        <button 
                                            @click="toggleFeature(business.id, feat.id, isEnabled(business, feat.id))"
                                            :disabled="toggling === `${business.id}-${feat.id}`"
                                            :class="[
                                                'relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-all duration-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 active:scale-90',
                                                isEnabled(business, feat.id) ? 'bg-blue-600 rotate-0' : 'bg-slate-200'
                                            ]"
                                        >
                                            <span class="sr-only">Toggle {{ feat.name }}</span>
                                            <span 
                                                :class="[
                                                    'pointer-events-none relative inline-block h-6 w-6 transform rounded-full bg-white shadow-xl ring-0 transition duration-300 ease-in-out',
                                                    isEnabled(business, feat.id) ? 'translate-x-5' : 'translate-x-0'
                                                ]"
                                            >
                                                <span v-if="toggling === `${business.id}-${feat.id}`" class="absolute inset-0 flex items-center justify-center">
                                                    <Loader2 class="h-3 w-3 animate-spin text-blue-600" />
                                                </span>
                                                <span v-else class="absolute inset-0 flex items-center justify-center">
                                                    <Check v-if="isEnabled(business, feat.id)" class="h-3.5 w-3.5 text-blue-600 font-black stroke-[3px]" />
                                                    <X v-else class="h-3.5 w-3.5 text-slate-400 font-black stroke-[3px]" />
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Module Legend -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="feat in props.features" :key="feat.id" class="border-none shadow-2xl bg-white rounded-[2rem] overflow-hidden group hover:ring-2 hover:ring-blue-100 transition-all">
                    <CardHeader class="p-8 pb-4">
                         <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <HelpCircle class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-sm font-black text-slate-900">{{ feat.name }}</CardTitle>
                                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">{{ feat.key }}</div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-8 pt-4">
                        <p class="text-xs text-slate-500 font-medium leading-relaxed italic border-l-2 border-blue-50 pl-4">
                            {{ feat.description }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
