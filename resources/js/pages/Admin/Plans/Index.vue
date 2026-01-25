<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Plus,
    Pencil,
    Trash2,
    Check,
    Building2,
    Crown,
    Zap,
    ShieldCheck,
    Smartphone,
    Info,
    Loader2
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
  DialogTitle,
} from '@/components/ui/dialog'
import { Checkbox } from '@/components/ui/checkbox'

const props = defineProps<{
    plans: Array<any>
    features: Array<any>
}>()

const showForm = ref(false)
const editingPlan = ref<any>(null)

const form = useForm({
    name: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    max_users: 0,
    max_employees: 0,
    max_products: 0,
    size_category: 'Small',
    is_active: true,
    feature_ids: [] as number[],
})

const openCreateModal = () => {
    editingPlan.value = null
    form.reset()
    form.clearErrors()
    showForm.value = true
}

const openEditModal = (plan: any) => {
    editingPlan.value = plan
    form.name = plan.name
    form.description = plan.description
    form.price_monthly = plan.price_monthly
    form.price_yearly = plan.price_yearly
    form.max_users = plan.max_users
    form.max_employees = plan.max_employees
    form.max_products = plan.max_products
    form.size_category = plan.size_category || 'Small'
    form.is_active = !!plan.is_active
    form.feature_ids = plan.features.map((f: any) => f.id)
    form.clearErrors()
    showForm.value = true
}

const submit = () => {
    // Resolve route helper safely for environments where Ziggy/route() isn't injected
    const r = (globalThis as any).route
    if (editingPlan.value) {
        const url = (typeof r !== 'undefined') ? r('admin.plans.update', editingPlan.value.id) : `/admin/plans/${editingPlan.value.id}`
        form.put(url, {
            onSuccess: () => {
                showForm.value = false
                form.reset()
            },
            onError: (errors) => {
                console.warn('Plan update failed', errors)
            }
        })
    } else {
        const url = (typeof r !== 'undefined') ? r('admin.plans.store') : '/admin/plans'
        form.post(url, {
            onSuccess: () => {
                showForm.value = false
                form.reset()
            },
            onError: (errors) => {
                console.warn('Plan create failed', errors)
            }
        })
    }
}

const deletePlan = (id: number) => {
    if (confirm('Are you sure you want to delete this plan? This action cannot be undone if no businesses are using it.')) {
        const r = (globalThis as any).route
        const url = (typeof r !== 'undefined') ? r('admin.plans.destroy', id) : `/admin/plans/${id}`
        form.delete(url)
    }
}

const toggleFeature = (featureId: number) => {
    const index = form.feature_ids.indexOf(featureId)
    if (index > -1) {
        form.feature_ids.splice(index, 1)
    } else {
        form.feature_ids.push(featureId)
    }
}
</script>

<template>
    <Head title="Subscription Plans Management" />

    <AdminLayout>
        <div class="space-y-8 animate-in fade-in duration-500">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 mb-1">
                        <div class="p-1.5 bg-indigo-100 rounded-lg">
                            <Crown class="size-4 text-indigo-600" />
                        </div>
                        <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">Growth Control</span>
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Subscription Tiers</h1>
                    <p class="text-slate-500 font-medium">Define and manage the pricing levels for all tenants on the platform.</p>
                </div>
                <Button @click="openCreateModal" class="h-12 px-6 rounded-xl bg-slate-900 hover:bg-black text-white font-black uppercase tracking-widest text-xs gap-2 shadow-lg">
                    <Plus class="size-4" />
                    New Subscription Plan
                </Button>
            </div>

            <!-- Plans Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <Table>
                    <TableHeader class="bg-slate-50 border-b border-slate-100">
                        <TableRow>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px] pl-8 py-5">Tier Identity</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px]">Business Size</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px]">Pricing (Monthly/Yearly)</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px]">Allocated Limits</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px]">Modules Enabled</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px] text-center">Visibility</TableHead>
                            <TableHead class="font-black text-slate-900 uppercase tracking-widest text-[10px] text-right pr-8">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="plan in props.plans" :key="plan.id" class="group hover:bg-slate-50/50 transition-all border-b border-slate-50">
                            <TableCell class="pl-8 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm transition-transform group-hover:scale-105">
                                        <Zap v-if="plan.name.toLowerCase().includes('medium')" class="size-5" />
                                        <Crown v-else-if="plan.name.toLowerCase().includes('enterprise')" class="size-5" />
                                        <Building2 v-else class="size-5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="font-black text-slate-900 uppercase tracking-tight">{{ plan.name }}</div>
                                        <div class="text-xs text-slate-400 font-medium truncate max-w-[200px]">{{ plan.description || 'No description provided' }}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="bg-slate-50 text-[10px] font-black uppercase border-slate-200 text-slate-600 px-3">
                                    {{ plan.size_category || 'N/A' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="space-y-1">
                                    <div class="font-black text-slate-900">
                                        KES {{ Number(plan.price_monthly).toLocaleString() }} <span class="text-[10px] text-slate-400 uppercase">/ mo</span>
                                    </div>
                                    <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest bg-emerald-50 px-2 py-0.5 rounded inline-block">
                                        KES {{ Number(plan.price_yearly).toLocaleString() }} / yr
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-4">
                                    <div class="text-center">
                                        <div class="text-xs font-black text-slate-900">{{ plan.max_users || '∞' }}</div>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Users</div>
                                    </div>
                                    <div class="w-px h-6 bg-slate-100"></div>
                                    <div class="text-center">
                                        <div class="text-xs font-black text-slate-900">{{ plan.max_employees || '∞' }}</div>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Staff</div>
                                    </div>
                                    <div class="w-px h-6 bg-slate-100"></div>
                                    <div class="text-center">
                                        <div class="text-xs font-black text-slate-900">{{ plan.max_products || '∞' }}</div>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Items</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1 max-w-[200px]">
                                    <Badge v-for="feature in plan.features" :key="feature.id" variant="outline" class="bg-indigo-50/50 text-[9px] font-bold uppercase border-indigo-100 text-indigo-700 h-5">
                                        {{ feature.name }}
                                    </Badge>
                                    <span v-if="plan.features.length === 0" class="text-[10px] text-slate-400 font-medium italic">Basic Features Only</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge :class="plan.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="px-2.5 py-1 rounded-lg font-black uppercase text-[9px] tracking-widest border-none">
                                    {{ plan.is_active ? 'Public' : 'Hidden' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right pr-8">
                                <div class="flex items-center justify-end gap-2">
                                    <Button @click="openEditModal(plan)" variant="ghost" class="h-9 w-9 p-0 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button @click="deletePlan(plan.id)" variant="ghost" class="h-9 w-9 p-0 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors border-none">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="props.plans.length === 0">
                            <TableCell colspan="6" class="h-64 text-center">
                                <div class="flex flex-col items-center justify-center space-y-4">
                                    <div class="p-6 bg-slate-50 rounded-full">
                                        <ShieldCheck class="size-12 text-slate-200" />
                                    </div>
                                    <div>
                                        <p class="text-slate-900 font-black uppercase text-sm tracking-widest">No plans defined</p>
                                        <p class="text-slate-400 text-xs mt-1">Start by creating your first subscription tier.</p>
                                    </div>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <Dialog v-model:open="showForm">
            <DialogContent class="sm:max-w-[700px] p-0 border-none shadow-3xl bg-[#f8fafc] overflow-hidden rounded-[2.5rem] animate-in slide-in-from-bottom-4 duration-500">
                <div class="max-h-[90vh] overflow-y-auto custom-scrollbar">
                    <div class="p-10 space-y-8 bg-white border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <Badge variant="outline" class="bg-indigo-50 text-indigo-700 border-indigo-100 px-3 py-1 font-black uppercase text-[9px] tracking-widest">Configuration Engine</Badge>
                            <DialogTitle class="text-4xl font-black text-slate-900 tracking-tighter">{{ editingPlan ? 'Edit Sub Tier' : 'New Sub Tier' }}</DialogTitle>
                            <DialogDescription class="text-slate-500 font-medium pt-1">Define the boundaries and revenue model for this subscription level.</DialogDescription>
                        </div>
                        <div class="p-4 bg-indigo-50 rounded-3xl text-indigo-600 shadow-inner">
                            <Smartphone class="size-8" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-8">
                        <div class="space-y-2.5">
                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Plan Title</Label>
                            <Input v-model="form.name" placeholder="e.g. Enterprise Pro" class="h-14 rounded-2xl border-slate-200 focus-visible:ring-indigo-600 bg-white font-black text-slate-900 px-5 text-lg" />
                            <div v-if="form.errors.name" class="text-[9px] text-red-600 font-black uppercase tracking-tight px-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="space-y-2.5">
                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Target Business Size</Label>
                            <select v-model="form.size_category" class="h-14 w-full rounded-2xl border border-slate-200 px-5 bg-white font-black text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 transition-all appearance-none cursor-pointer">
                                <option value="Small">Small Business</option>
                                <option value="Medium">Medium-Sized</option>
                                <option value="Large">Large Enterprise</option>
                                <option value="Enterprise">Infinite / Scale</option>
                            </select>
                        </div>
                        <div class="space-y-2.5">
                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Plan Visibility</Label>
                            <div class="flex items-center h-14 bg-slate-50 px-6 rounded-2xl border border-slate-200/50">
                                <Checkbox :checked="form.is_active" @update:checked="form.is_active = $event" id="is_active" class="size-5 rounded-md border-slate-300 data-[state=checked]:bg-indigo-600 data-[state=checked]:border-indigo-600" />
                                <Label for="is_active" class="ml-3 font-bold text-slate-700 text-sm cursor-pointer select-none">Make plan public in dashboard</Label>
                            </div>
                        </div>
                        <div class="col-span-2 space-y-2.5">
                            <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Strategic Description</Label>
                            <textarea v-model="form.description" class="w-full min-h-[80px] rounded-2xl border border-slate-200 p-5 focus:outline-none focus:ring-2 focus:ring-indigo-600 bg-white font-medium text-slate-700 text-sm placeholder:text-slate-400 resize-none transition-shadow" placeholder="What high-level value does this tier offer?"></textarea>
                        </div>
                    </div>
                </div>

                <div class="p-10 space-y-10">
                    <!-- Pricing & Limits Grid -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="space-y-2 col-span-1 lg:col-span-2">
                            <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Monthly (KES)</Label>
                            <Input v-model="form.price_monthly" type="number" class="h-12 rounded-xl border-slate-200 font-black text-slate-900" />
                        </div>
                        <div class="space-y-2 col-span-1 lg:col-span-2">
                             <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Yearly (KES)</Label>
                            <Input v-model="form.price_yearly" type="number" class="h-12 rounded-xl border-slate-200 font-black text-slate-900" />
                        </div>
                        <div class="hidden lg:block"></div> <!-- Spacer -->

                        <div class="space-y-2">
                            <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Max Users</Label>
                            <Input v-model="form.max_users" type="number" class="h-12 rounded-xl border-slate-200 font-black text-slate-900 text-center" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Max Staff</Label>
                            <Input v-model="form.max_employees" type="number" class="h-12 rounded-xl border-slate-200 font-black text-slate-900 text-center" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Max items</Label>
                            <Input v-model="form.max_products" type="number" class="h-12 rounded-xl border-slate-200 font-black text-slate-900 text-center" />
                        </div>
                        <div class="col-span-2 text-[9px] text-slate-400 font-bold uppercase py-1 border-t border-slate-100 flex items-center gap-2">
                            <Info class="size-3" /> Set to 0 for unlimited scaling
                        </div>
                    </div>

                    <!-- Module Toggling -->
                    <div class="space-y-6 bg-indigo-50/30 p-8 rounded-3xl border border-indigo-100/50">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-indigo-700">Module Access Matrix</h3>
                            <Badge class="bg-indigo-600 text-white border-none text-[8px] font-black uppercase">{{ form.feature_ids.length }} Modules Active</Badge>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-6">
                            <div v-for="feature in props.features" :key="feature.id" class="flex items-center group cursor-pointer" @click="toggleFeature(feature.id)">
                                <div class="size-6 rounded-lg border-2 flex items-center justify-center transition-all duration-300 mr-3" :class="form.feature_ids.includes(feature.id) ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-200 group-hover:border-indigo-300'">
                                    <Check v-if="form.feature_ids.includes(feature.id)" class="size-4" />
                                </div>
                                <span class="text-sm font-bold transition-colors" :class="form.feature_ids.includes(feature.id) ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'">{{ feature.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button @click="showForm = false" variant="ghost" class="flex-1 h-14 rounded-2xl font-black uppercase tracking-[0.2em] text-[10px] text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition-all border-none">
                            Discard Configuration
                        </Button>
                        <Button @click="submit" :disabled="form.processing" class="flex-[2] h-14 rounded-2xl bg-slate-900 hover:bg-black text-white font-black uppercase tracking-[0.2em] text-[10px] shadow-xl transition-all gap-2">
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            {{ editingPlan ? 'Commit Tier Updates' : 'Deploy Subscription Tier' }}
                        </Button>
                    </div>
                </div>
                </div>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<style scoped>
.shadow-3xl {
    box-shadow: 0 50px 100px -20px rgba(15, 23, 42, 0.25);
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
