<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { 
    ShieldCheck, 
    Plus, 
    Shield, 
    Settings, 
    CheckCircle2, 
    LayoutGrid, 
    Trash2, 
    X, 
    Layers,
    Save
} from 'lucide-vue-next'
import { ref } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
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
import { Textarea } from '@/components/ui/textarea'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
    roles: Array<{
        id: number
        name: string
        display_name: string
        description: string
        level: number
        permissions: Array<{ id: number, name: string }>
        created_at: string
    }>
    permissions: Record<string, Array<{ id: number, name: string, display_name: string }>>
}>()

const showModal = ref(false)
const editingRole = ref<any>(null)

const form = useForm({
    name: '',
    display_name: '',
    description: '',
    level: 1,
    permissions: [] as number[],
})

const openAdd = () => {
    editingRole.value = null
    form.reset()
    form.permissions = []
    showModal.value = true
}

const openEdit = (role: any) => {
    editingRole.value = role
    form.name = role.name
    form.display_name = role.display_name
    form.description = role.description
    form.level = role.level
    form.permissions = role.permissions.map((p: any) => p.id)
    showModal.value = true
}

const submit = () => {
    if (editingRole.value) {
        form.put(`/admin/roles/${editingRole.value.id}`, {
            onSuccess: () => closeModal()
        })
    } else {
        form.post('/admin/roles', {
            onSuccess: () => closeModal()
        })
    }
}

const closeModal = () => {
    showModal.value = false
    editingRole.value = null
    form.reset()
}

const togglePermission = (id: number) => {
    const index = form.permissions.indexOf(id)
    if (index > -1) {
        form.permissions.splice(index, 1)
    } else {
        form.permissions.push(id)
    }
}

const deleteRole = (id: number) => {
    if (confirm('Are you sure you want to delete this role definition? This cannot be undone if not assigned.')) {
        useForm({}).delete(`/admin/roles/${id}`)
    }
}
</script>

<template>
    <Head title="Global Governance: Roles & Hierarchy" />

    <AdminLayout>
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight flex items-center gap-3">
                         <div class="p-2 bg-red-600 rounded-lg text-white">
                             <ShieldCheck class="h-6 w-6" />
                         </div>
                         Platform Role Governance
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">Define structural staff hierarchies and system capabilities for all businesses.</p>
                </div>
                <Button @click="openAdd" class="bg-slate-900 hover:bg-black font-bold shadow-lg">
                    <Plus class="mr-2 h-4 w-4" />
                    New Structural Role
                </Button>
            </div>

            <!-- Role Creation/Editing Modal Style Card -->
            <Card v-if="showModal" class="border-none shadow-2xl bg-white overflow-hidden border-t-4 border-red-600 animate-in fade-in slide-in-from-top-4">
                <CardHeader class="border-b border-slate-50 bg-slate-50/50 flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-lg font-bold flex items-center gap-2">
                             <Layers class="h-4 w-4 text-red-600" />
                             {{ editingRole ? 'Edit Role Architecture' : 'Define New Hierarchy Level' }}
                        </CardTitle>
                        <CardDescription>Setup the core identification and clearance level for this role.</CardDescription>
                    </div>
                    <Button variant="ghost" size="sm" @click="closeModal" class="h-8 w-8 p-0">
                        <X class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent class="pt-8">
                    <form @submit.prevent="submit" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="space-y-2">
                                <Label class="font-black text-[10px] uppercase tracking-widest text-slate-400">Internal Slug (Permanent)</Label>
                                <Input v-model="form.name" :disabled="!!editingRole" placeholder="e.g. general-manager" class="border-slate-200 focus:ring-red-500 font-mono text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label class="font-black text-[10px] uppercase tracking-widest text-slate-400">Public Title (English)</Label>
                                <Input v-model="form.display_name" placeholder="e.g. General Manager" class="border-slate-200 font-bold focus:ring-red-500" />
                            </div>
                            <div class="space-y-2">
                                <Label class="font-black text-[10px] uppercase tracking-widest text-slate-400">Clearance Level (0-100)</Label>
                                <Input type="number" v-model="form.level" placeholder="75" class="border-slate-200 font-black focus:ring-red-500" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="font-black text-[10px] uppercase tracking-widest text-slate-400">Operational Mandate</Label>
                            <Textarea v-model="form.description" placeholder="Describe the responsibilities and scope of this role..." class="border-slate-200 min-h-[80px]" />
                        </div>

                        <!-- Permissions Engine -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                                <Settings class="h-4 w-4 text-red-600" />
                                <Label class="font-bold text-slate-900">System Capabilities (RBAC Matrix)</Label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                <div v-for="(groupPerms, group) in props.permissions" :key="group" class="space-y-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-2">
                                        <LayoutGrid class="h-3 w-3" />
                                        {{ group }} MODULE
                                    </h3>
                                    <div class="space-y-2">
                                        <button 
                                            v-for="perm in groupPerms" 
                                            :key="perm.id"
                                            type="button"
                                            @click="togglePermission(perm.id)"
                                            :class="form.permissions.includes(perm.id) ? 'bg-white border-red-600 text-red-700 shadow-sm ring-1 ring-red-600' : 'bg-white/50 border-slate-200 text-slate-500 hover:bg-white hover:border-slate-300'"
                                            class="w-full text-left p-2.5 rounded-lg border flex items-center justify-between transition-all group"
                                        >
                                            <span class="text-xs font-bold">{{ perm.display_name }}</span>
                                            <CheckCircle2 v-if="form.permissions.includes(perm.id)" class="h-4 w-4 text-red-600" />
                                            <div v-else class="h-4 w-4 rounded-full border border-slate-200 group-hover:border-slate-400"></div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 flex justify-end gap-4 border-t border-slate-50">
                            <Button type="button" variant="ghost" @click="closeModal" class="font-bold text-slate-500">Acknowledge & Close</Button>
                            <Button type="submit" class="bg-red-600 hover:bg-red-700 font-bold px-10 shadow-lg shadow-red-600/20" :disabled="form.processing">
                                <Save class="mr-2 h-4 w-4" />
                                {{ editingRole ? 'Commit Updates' : 'Publish Role Definition' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Roles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="role in props.roles" :key="role.id" class="border-none shadow-sm bg-white hover:shadow-md transition-all group overflow-hidden relative">
                    <!-- Progress Bar for Level -->
                    <div class="absolute top-0 left-0 h-1 bg-red-600 transition-all opacity-20 group-hover:opacity-100" :style="{ width: role.level + '%' }"></div>
                    
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="p-2 bg-slate-100 rounded-lg text-slate-600 group-hover:bg-red-600 group-hover:text-white transition-all transform group-hover:rotate-6 shadow-sm">
                                <Shield class="h-5 w-5" />
                            </div>
                            <Badge variant="outline" class="text-[10px] font-black text-slate-400 bg-slate-50">RANK: {{ role.level }}</Badge>
                        </div>
                        <CardTitle class="text-xl font-black mt-4 text-slate-900 tracking-tight">{{ role.display_name }}</CardTitle>
                        <div class="flex items-center gap-2 mt-1">
                            <Badge variant="secondary" class="text-[9px] font-mono tracking-tighter bg-indigo-50 text-indigo-700 border-indigo-100 hover:bg-indigo-100 transition-colors uppercase">
                                system.{{ role.name }}
                            </Badge>
                        </div>
                        <p class="text-slate-500 text-xs mt-3 line-clamp-2 min-h-[32px] font-medium leading-relaxed">{{ role.description }}</p>
                    </CardHeader>
                    <CardContent>
                        <div class="mt-4 pt-4 border-t border-slate-50">
                             <div class="flex items-center gap-2 mb-3">
                                <Settings class="h-3 w-3 text-slate-300" />
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Clearance & Scope</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="perm in role.permissions.slice(0, 3)" :key="perm.id" variant="secondary" class="text-[8px] font-bold uppercase tracking-tight bg-slate-50 text-slate-500 border-none px-2 py-1">
                                    {{ perm.name.replace('_', ' ') }}
                                </Badge>
                                <span v-if="role.permissions.length > 3" class="text-[9px] font-bold text-slate-300 px-2 py-1">
                                    +{{ role.permissions.length - 3 }} more
                                </span>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-50 flex justify-between items-center opacity-0 group-hover:opacity-100 transition-all transform translate-y-2 group-hover:translate-y-0">
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="openEdit(role)" class="h-8 text-[10px] font-bold border-slate-100 hover:bg-slate-900 hover:text-white transition-all">
                                    <Layers class="h-3 w-3 mr-1.5" /> Configure
                                </Button>
                                <Button v-if="role.name !== 'admin'" variant="ghost" size="sm" @click="deleteRole(role.id)" class="h-8 text-[10px] font-bold text-slate-400 hover:text-red-600">
                                    <Trash2 class="h-3 w-3" />
                                </Button>
                            </div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest tabular-nums">ID #{{ role.id }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
            
            <div v-if="props.roles.length === 0" class="py-32 text-center bg-white rounded-xl border-4 border-dashed border-slate-100">
                <Shield class="h-20 w-20 text-slate-100 mx-auto mb-6" />
                <h3 class="text-xl font-bold text-slate-900">No Global Jurisdictions Defined</h3>
                <p class="text-slate-500 max-w-sm mx-auto mt-2">Roles are the backbone of platform security. Create your first architectural role to begin defining hierarchies.</p>
                <Button @click="openAdd" class="bg-red-600 hover:bg-red-700 font-bold mt-8 h-12 px-8 shadow-xl">
                    Define Framework Role
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>
