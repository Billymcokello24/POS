<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import {
    Plus,
    Search,
    UserPlus,
    MoreVertical,
    Pencil,
    Trash2,
    ShieldCheck,
    Mail,
    User as UserIcon,
    Loader2
} from 'lucide-vue-next'
import { ref } from 'vue'

import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface User {
    id: number
    name: string
    email: string
    is_super_admin?: boolean
    created_at?: string
}

const props = defineProps<{
    users: User[]
}>()

const isAddOpen = ref(false)
const isEditOpen = ref(false)
const selectedUser = ref<{ id: number; name: string; email: string } | null>(null)

const addForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const createUser = () => {
    const r = (globalThis as any).route
    const url = (typeof r !== 'undefined') ? r('admin.users.store') : '/admin/admin-users'
    
    addForm.post(url, {
        onSuccess: () => {
            isAddOpen.value = false
            addForm.reset()
        },
        onError: (errors) => {
            console.warn('User create failed', errors)
        }
    })
}

const openEdit = (user: User) => {
    selectedUser.value = user
    editForm.name = user.name
    editForm.email = user.email
    editForm.password = ''
    editForm.password_confirmation = ''
    editForm.clearErrors()
    isEditOpen.value = true
}

const updateUser = () => {
    if (!selectedUser.value?.id) {
        return
    }
    
    const r = (globalThis as any).route
    const url = (typeof r !== 'undefined') 
        ? r('admin.users.update', selectedUser.value.id) 
        : `/admin/admin-users/${selectedUser.value.id}`
    
    editForm.put(url, {
        preserveScroll: true,
        onSuccess: () => {
            isEditOpen.value = false
            editForm.reset()
        },
        onError: (errors) => {
            console.warn('User update failed', errors)
        }
    })
}

const deleteUser = (id: number) => {
    if (confirm('Are you sure you want to remove this SuperAdmin?')) {
        const r = (globalThis as any).route
        const url = (typeof r !== 'undefined') ? r('admin.users.destroy', id) : `/admin/admin-users/${id}`
        useForm({}).delete(url)
    }
}
</script>

<template>
    <Head title="Platform Administrators" />

    <AdminLayout>
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Platform Administrators</h1>
                    <p class="text-slate-500 text-sm font-medium">Manage users with root-level access to the POS platform.</p>
                </div>

                <Dialog v-model:open="isAddOpen">
                    <DialogTrigger asChild>
                        <Button class="bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest px-6 py-6 rounded-2xl shadow-xl shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98]">
                            <UserPlus class="mr-2 h-5 w-5" />
                            Add Admin
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[425px] rounded-3xl border-none shadow-2xl">
                        <DialogHeader>
                            <DialogTitle class="text-2xl font-black text-slate-900">New Administrator</DialogTitle>
                            <DialogDescription class="font-medium text-slate-500 italic">
                                Create a new user with full platform privileges.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-4 py-4">
                            <div class="space-y-2">
                                <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Full Name</Label>
                                <Input v-model="addForm.name" placeholder="Enter name" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                <p v-if="addForm.errors.name" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ addForm.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Email Address</Label>
                                <Input v-model="addForm.email" type="email" placeholder="admin@digiprojects.ke" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                <p v-if="addForm.errors.email" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ addForm.errors.email }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Password</Label>
                                <Input v-model="addForm.password" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                <p v-if="addForm.errors.password" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ addForm.errors.password }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirm Password</Label>
                                <Input v-model="addForm.password_confirmation" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                            </div>
                            <DialogFooter class="pt-4">
                                <Button @click="createUser" :disabled="addForm.processing" class="w-full h-14 bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest rounded-2xl shadow-xl transition-all">
                                    <Loader2 v-if="addForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                    Confirm Creation
                                </Button>
                            </DialogFooter>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Content -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden ring-1 ring-black/5">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Administrator</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Security Access</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Added On</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in users" :key="user.id" class="group hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-5 hover:cursor-default">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-black text-lg shadow-inner">
                                            {{ user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().substring(0, 2) }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                                {{ user.name }}
                                                <ShieldCheck v-if="user.is_super_admin" class="h-3.5 w-3.5 text-indigo-500 fill-indigo-50/50" />
                                            </div>
                                            <div class="text-xs font-bold text-slate-400 flex items-center gap-1 mt-0.5">
                                                <Mail class="h-3 w-3" />
                                                {{ user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100 shadow-sm shadow-emerald-50">
                                        Super User
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <span class="text-xs font-bold text-slate-500 tabular-nums">
                                        {{ user.created_at ? new Date(user.created_at).toLocaleDateString() : '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Button @click="openEdit(user)" variant="ghost" size="icon" class="h-8 w-8 rounded-lg hover:bg-white hover:text-indigo-600 shadow-sm border border-transparent hover:border-slate-100">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button v-if="$page.props.auth.user.id !== user.id" @click="deleteUser(user.id)" variant="ghost" size="icon" class="h-8 w-8 rounded-lg hover:bg-white hover:text-red-600 shadow-sm border border-transparent hover:border-slate-100">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Dialog -->
        <Dialog v-model:open="isEditOpen">
            <DialogContent class="sm:max-w-[425px] rounded-3xl border-none shadow-2xl">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black text-slate-900">Update Administrator</DialogTitle>
                    <DialogDescription class="font-medium text-slate-500 italic">
                        Modify profile or security credentials.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Full Name</Label>
                        <Input v-model="editForm.name" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                        <p v-if="editForm.errors.name" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Email Address</Label>
                        <Input v-model="editForm.email" type="email" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                        <p v-if="editForm.errors.email" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ editForm.errors.email }}</p>
                    </div>
                    
                    <div class="relative py-2">
                        <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-slate-100"></span></div>
                        <div class="relative flex justify-center text-[9px] uppercase font-black text-slate-300">
                            <span class="bg-white px-2">Change Password (Optional)</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">New Password</Label>
                        <Input v-model="editForm.password" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                        <p v-if="editForm.errors.password" class="text-red-500 text-[10px] font-bold uppercase ml-1">{{ editForm.errors.password }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirm New Password</Label>
                        <Input v-model="editForm.password_confirmation" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                    </div>
                    <DialogFooter class="pt-4">
                        <Button @click="updateUser" :disabled="editForm.processing" class="w-full h-14 bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest rounded-2xl shadow-xl transition-all">
                            <Loader2 v-if="editForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Update Record
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

