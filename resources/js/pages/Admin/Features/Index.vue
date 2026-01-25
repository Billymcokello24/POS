<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Wrench, Check, X, ShieldCheck, HelpCircle, Loader2 } from 'lucide-vue-next'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Switch } from '@/components/ui/switch'

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
</script>

<template>
    <Head title="Feature Management" />

    <AdminLayout>
        <div class="space-y-8">
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight flex items-center gap-2">
                         <Wrench class="h-6 w-6 text-blue-600" />
                         Feature Toggling
                    </h1>
                    <p class="text-slate-500 text-sm">Control modular capabilities for each business on the platform.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                 <Table>
                    <TableHeader class="bg-slate-50 border-b border-slate-100">
                        <TableRow>
                            <TableHead class="font-bold text-slate-800 w-[250px]">Business Identity</TableHead>
                            <TableHead v-for="feat in props.features" :key="feat.id" class="text-center font-bold text-slate-700 min-w-[120px]">
                                <div class="flex flex-col items-center">
                                    <span class="text-xs uppercase tracking-tighter">{{ feat.name }}</span>
                                    <Badge variant="outline" class="text-[8px] font-bold text-slate-400 mt-1 border-slate-200">{{ feat.key }}</Badge>
                                </div>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="business in props.businesses.data" :key="business.id" class="hover:bg-slate-50/50 transition-colors">
                            <TableCell class="py-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 font-bold border border-slate-200 uppercase text-xs">
                                        {{ business.name.substring(0, 2) }}
                                    </div>
                                    <span class="font-bold text-slate-900 whitespace-nowrap">{{ business.name }}</span>
                                </div>
                            </TableCell>
                            
                            <TableCell v-for="feat in props.features" :key="feat.id" class="text-center border-l border-slate-50/50">
                                <div class="flex justify-center items-center h-full">
                                    <button 
                                        @click="toggleFeature(business.id, feat.id, isEnabled(business, feat.id))"
                                        :disabled="toggling === `${business.id}-${feat.id}`"
                                        :class="[
                                            'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-50',
                                            isEnabled(business, feat.id) ? 'bg-blue-600' : 'bg-slate-200'
                                        ]"
                                    >
                                        <span class="sr-only">Toggle {{ feat.name }}</span>
                                        <span 
                                            :class="[
                                                'pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                isEnabled(business, feat.id) ? 'translate-x-5' : 'translate-x-0'
                                            ]"
                                        >
                                            <span v-if="toggling === `${business.id}-${feat.id}`" class="absolute inset-0 flex items-center justify-center">
                                                <Loader2 class="h-3 w-3 animate-spin text-blue-600" />
                                            </span>
                                            <span v-else class="absolute inset-0 flex items-center justify-center">
                                                <Check v-if="isEnabled(business, feat.id)" class="h-3 w-3 text-blue-600 font-bold" />
                                                <X v-else class="h-3 w-3 text-slate-400 font-bold" />
                                            </span>
                                        </span>
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Feature Explainer -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="feat in props.features" :key="feat.id" class="border-none shadow-sm bg-slate-50/50 border border-slate-100">
                    <CardHeader class="pb-2">
                         <div class="flex items-center gap-2">
                            <HelpCircle class="h-4 w-4 text-slate-400" />
                            <CardTitle class="text-sm font-bold text-slate-800">{{ feat.name }}</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-600 leading-relaxed">{{ feat.description }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
