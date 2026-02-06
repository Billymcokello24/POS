<script setup lang="ts">
/* eslint-disable */
import { ref, watch, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Search,
    MoreVertical,
    Building2,
    ShieldAlert,
    KeyRound,
    Users,
    ShoppingBag,
    History,
    Wrench,
    Trash2,
    Mail
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
    businesses: {
        data: Array<{
            id: number
            name: string
            email: string
            phone: string
            is_active: boolean
            users_count: number
            sales_count: number
            created_at: string
        }>
        links: any
        meta: any
    }
    filters: {
        search: string
        status: string
    }
}>()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')

watch([search, status], debounce(() => {
    router.get(
        '/admin/businesses',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    )
}, 300))

// Selection state
const selectedIds = ref<number[]>([])
const isSelected = (id: number) => selectedIds.value.includes(id)
const toggleSelect = (id: number) => {
  if (isSelected(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

const allSelected = computed<boolean>({
  get() {
    return !!(props.businesses && props.businesses.data && props.businesses.data.length > 0 && selectedIds.value.length === props.businesses.data.length)
  },
  set(value: boolean) {
    if (!props.businesses?.data) return
    selectedIds.value = value ? props.businesses.data.map(b => b.id) : []
  }
})

const selectedCount = computed(() => selectedIds.value.length)

const clearSelection = () => { selectedIds.value = [] }

const toggleStatus = async (id: number) => {
    const business = props.businesses.data.find(b => b.id === id)
    const isSuspending = business?.is_active

    let reason = null
    if (isSuspending) {
        reason = prompt('Reason for suspension (Optional):', '')
        if (reason === null) return // User cancelled
    } else {
        if (!confirm('Are you sure you want to restore this business account?')) return
    }

    await router.post(`/admin/businesses/${id}/toggle-status`, { reason })
    router.reload()
}

const bulkToggleStatus = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Toggle status for ${selectedIds.value.length} selected business(es)?`)) return
  try {
    await Promise.all(selectedIds.value.map((id) => router.post(`/admin/businesses/${id}/toggle-status`)));
    clearSelection()
    router.reload()
  } catch (e) {
    console.error(e)
    alert('Bulk toggle failed')
  }
}

const resetPassword = async (id: number) => {
    if (!confirm('This will reset the main admin password to "Password123!". Continue?')) return
    await router.post(`/admin/businesses/${id}/reset-password`)
    router.reload()
}

const bulkResetPasswords = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Reset passwords for ${selectedIds.value.length} selected business(es)?`)) return
  try {
    await Promise.all(selectedIds.value.map((id) => router.post(`/admin/businesses/${id}/reset-password`)));
    clearSelection()
    router.reload()
  } catch (e) {
    console.error(e)
    alert('Bulk reset failed')
  }
}

const impersonate = async (id: number) => {
    const reason = prompt('Reason for impersonation (Log for audit):', 'Customer support/debugging');
    if (reason === null) return; // User cancelled

    if (!confirm('Log in as an administrator for this business? Your current session will be saved and the business will be notified.')) return
    await router.post(`/admin/businesses/${id}/impersonate`, { reason })
}

// Delete state - simplified like subscriptions
const deleteBusiness = async (id: number, name: string) => {
    if (!confirm(`⚠️ Delete business "${name}"?\n\nThis will permanently delete:\n• The business and all its data\n• All sales and products\n• All subscriptions and payments\n• All users associated with this business only\n• All inventory and transactions\n\nThis action CANNOT be undone!`)) return

    await router.delete(`/admin/businesses/${id}`)
    router.reload()
}

const bulkDeleteBusinesses = async () => {
    if (selectedIds.value.length === 0) return
    if (!confirm(`⚠️ DANGER: Permanently delete ${selectedIds.value.length} business(es)?\n\nThis will delete:\n• All selected businesses\n• All their data, sales, products, subscriptions\n• All associated users\n\nThis action CANNOT be undone!`)) return

    try {
        // Delete each business sequentially
        for (const id of selectedIds.value) {
            await router.delete(`/admin/businesses/${id}`, {
                preserveScroll: true
            })
        }
        selectedIds.value = []
        router.reload()
    } catch (e) {
        console.error('Bulk delete failed:', e)
        alert('Bulk delete failed')
    }
}

const openBulkEmail = () => {
    const params: any = {}
    if (selectedIds.value.length > 0) {
        params.ids = selectedIds.value
    }
    router.get('/admin/bulk-email', params)
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

// Export CSV of visible businesses
const exportCSV = () => {
  const rows = [['ID','Name','Email','Phone','Users','Sales','Status','Created']]
  props.businesses.data.forEach(b => rows.push([
    String(b.id),
    b.name,
    b.email,
    b.phone || '',
    String(b.users_count || 0),
    String(b.sales_count || 0),
    b.is_active ? 'Active' : 'Suspended',
    formatDate(b.created_at)
  ]))
  const csv = rows.map(r => r.map(c => '"'+String(c).replace(/"/g,'""')+'"').join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `businesses-${new Date().toISOString().slice(0,10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

const openDetails = (id: number) => {
    router.visit(`/admin/businesses/${id}`)
}

const resetData = ref<any>(null)
const showResetModal = ref(false)

// Watch flash for new_password
watch(() => usePage().props.flash, (flash: any) => {
    if (flash?.new_password) {
        resetData.value = flash.new_password
        showResetModal.value = true
    }
}, { immediate: true, deep: true })

// Safe pagination helpers
const paginationFrom = computed(() => {
  return props.businesses?.meta?.from ?? (props.businesses?.data?.length ? 1 : 0)
})

const paginationTo = computed(() => {
  return props.businesses?.meta?.to ?? (props.businesses?.data?.length ?? 0)
})

const paginationTotal = computed(() => {
  return props.businesses?.meta?.total ?? (props.businesses?.data?.length ?? 0)
})


const prevLink = computed(() => props.businesses?.links?.prev ?? null)
const nextLink = computed(() => props.businesses?.links?.next ?? null)
</script>

<template>
    <Head title="Business Management" />

    <AdminLayout>
        <!-- Reset Password Success Modal -->
        <div v-if="showResetModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="bg-amber-50 p-6 flex flex-col items-center text-center">
                    <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
                        <KeyRound class="h-8 w-8 text-amber-600" />
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Credentials Reset Successful</h3>
                    <p class="text-slate-500 text-sm mt-2 font-medium">Please share these temporary credentials with the business administrator.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 space-y-3">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email Address</p>
                            <p class="text-slate-900 font-bold break-all">{{ resetData?.email }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temporary Password</p>
                            <p class="text-blue-700 font-mono font-bold text-lg tracking-tight">{{ resetData?.password }}</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg flex gap-3">
                        <ShieldAlert class="h-5 w-5 text-blue-600 shrink-0" />
                        <p class="text-xs text-blue-700 font-medium leading-relaxed">The user will be prompted to change their password upon their next successful login.</p>
                    </div>
                    <Button @click="showResetModal = false" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold h-12 rounded-xl">
                        I've Noted the credentials
                    </Button>
                </div>
            </div>
        </div>


        <div class="space-y-6">
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Registered Businesses</h1>
                    <p class="text-slate-500 text-sm font-medium">Monitor and manage all entities on the platform.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <Input
                                v-model="search"
                                placeholder="Search by name, email, or ID..."
                                class="pl-10 h-10 border-slate-200 focus:ring-blue-500"
                            />
                        </div>
                        <div class="w-full md:w-48">
                            <select
                                v-model="status"
                                class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-background placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-shadow appearance-none font-medium"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Suspended Only</option>
                            </select>
                        </div>

                        <div v-if="selectedCount > 0" class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2 flex items-center gap-2">
                            <span class="text-blue-700 font-bold text-sm">{{ selectedCount }} selected</span>
                            <Button @click="clearSelection" variant="ghost" class="h-6 px-2 text-xs text-blue-500 hover:text-blue-700">
                                Clear
                            </Button>
                        </div>

                        <div class="ml-auto flex gap-2">
                            <Button variant="outline" @click="exportCSV" class="font-medium">Export CSV</Button>
                            <Button variant="outline" @click="openBulkEmail" class="border-blue-200 text-blue-700 hover:bg-blue-50 font-bold">
                                Bulk Email
                            </Button>
                            <Button
                                class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold"
                                @click="bulkToggleStatus"
                                :disabled="selectedCount===0"
                            >
                                Toggle Status
                            </Button>
                            <Button
                                variant="ghost"
                                @click="bulkResetPasswords"
                                :disabled="selectedCount===0"
                                class="text-slate-600 font-medium"
                            >
                                Reset Passwords
                            </Button>
                            <Button
                                variant="destructive"
                                @click="bulkDeleteBusinesses"
                                :disabled="selectedCount===0"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold"
                            >
                                Bulk Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <template v-if="props.businesses && props.businesses.data">
                    <Table>
                        <TableHeader class="bg-slate-50/50">
                            <TableRow>
                                <TableHead class="w-12 px-4 shadow-none">
                                    <input
                                        type="checkbox"
                                        v-model="allSelected"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    />
                                </TableHead>
                                <TableHead class="font-bold text-slate-700 uppercase text-[10px] tracking-wider">Business Identity</TableHead>
                                <TableHead class="font-bold text-slate-700 uppercase text-[10px] tracking-wider">Employees</TableHead>
                                <TableHead class="font-bold text-slate-700 uppercase text-[10px] tracking-wider">Turnover (Sales)</TableHead>
                                <TableHead class="font-bold text-slate-700 uppercase text-[10px] tracking-wider">Onboarding</TableHead>
                                <TableHead class="font-bold text-slate-700 uppercase text-[10px] tracking-wider text-center">Status</TableHead>
                                <TableHead class="text-right font-bold text-slate-700 uppercase text-[10px] tracking-wider px-6">Management</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="business in props.businesses.data"
                                :key="business.id"
                                class="hover:bg-slate-50/50 transition-colors group"
                            >
                                <TableCell class="py-4 px-4">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(business.id)"
                                        @change="toggleSelect(business.id)"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    />
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center text-blue-700 font-extrabold border border-blue-100 uppercase text-lg shadow-sm group-hover:from-blue-100 group-hover:to-indigo-100 transition-all duration-300">
                                            {{ business.name.substring(0, 2) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 group-hover:text-blue-700 transition-colors">{{ business.name }}</div>
                                            <div class="text-[11px] text-slate-400 font-bold uppercase tracking-tight flex items-center gap-1.5 mt-0.5">
                                                <Mail class="h-3 w-3" />
                                                {{ business.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div class="p-1.5 bg-slate-50 rounded-lg group-hover:bg-blue-50 transition-colors">
                                            <Users class="h-3.5 w-3.5 text-slate-400 group-hover:text-blue-500" />
                                        </div>
                                        <span class="font-bold text-slate-700">{{ business.users_count }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div class="p-1.5 bg-slate-50 rounded-lg group-hover:bg-amber-50 transition-colors">
                                            <ShoppingBag class="h-3.5 w-3.5 text-slate-400 group-hover:text-amber-500" />
                                        </div>
                                        <span class="font-bold text-slate-700">{{ business.sales_count }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-xs text-slate-500 font-bold">
                                    {{ formatDate(business.created_at) }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :class="business.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100'"
                                        class="px-3 py-1 font-extrabold rounded-lg border text-[10px] uppercase tracking-wide"
                                    >
                                        {{ business.is_active ? 'Active' : 'Suspended' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right px-6">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" class="h-10 w-10 p-0 text-slate-400 hover:text-slate-900 border-none rounded-xl hover:bg-slate-100 transition-all">
                                                <MoreVertical class="h-5 w-5" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-56 p-2 rounded-2xl shadow-2xl border-slate-200 overflow-hidden">
                                            <div class="px-3 py-2 text-[10px] font-black uppercase text-slate-400 tracking-widest border-b border-slate-50 mb-1">Business Ops</div>

                                            <DropdownMenuItem @click="openDetails(business.id)" class="cursor-pointer rounded-xl h-10 hover:bg-slate-50">
                                                <div class="flex items-center w-full">
                                                    <div class="w-7 h-7 bg-slate-100 rounded-lg flex items-center justify-center mr-3">
                                                        <History class="h-4 w-4 text-slate-600" />
                                                    </div>
                                                    <span class="font-bold text-slate-700">View Full Details</span>
                                                </div>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem @click="router.get('/admin/bulk-email', { ids: [business.id] })" class="cursor-pointer rounded-xl h-10 hover:bg-slate-50 mt-1">
                                                <div class="flex items-center w-full">
                                                    <div class="w-7 h-7 bg-blue-50 rounded-lg flex items-center justify-center mr-3">
                                                        <Mail class="h-4 w-4 text-blue-600" />
                                                    </div>
                                                    <span class="font-bold text-blue-700">Direct Message</span>
                                                </div>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem @click="toggleStatus(business.id)" class="cursor-pointer rounded-xl h-10 hover:bg-slate-50 mt-1">
                                                <div class="flex items-center w-full">
                                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center mr-3" :class="business.is_active ? 'bg-red-50' : 'bg-emerald-50'">
                                                        <ShieldAlert class="h-4 w-4" :class="business.is_active ? 'text-red-600' : 'text-emerald-600'" />
                                                    </div>
                                                    <span class="font-bold" :class="business.is_active ? 'text-red-600' : 'text-emerald-600'">{{ business.is_active ? 'Suspend Account' : 'Restore Account' }}</span>
                                                </div>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem @click="resetPassword(business.id)" class="cursor-pointer rounded-xl h-10 hover:bg-slate-50 mt-1">
                                                <div class="flex items-center w-full">
                                                    <div class="w-7 h-7 bg-amber-50 rounded-lg flex items-center justify-center mr-3">
                                                        <KeyRound class="h-4 w-4 text-amber-600" />
                                                    </div>
                                                    <span class="font-bold text-amber-900">Reset Credentials</span>
                                                </div>
                                            </DropdownMenuItem>

                                            <DropdownMenuSeparator class="my-1 bg-slate-50" />

                                            <DropdownMenuItem @click="impersonate(business.id)" class="cursor-pointer rounded-xl h-10 hover:bg-indigo-50 mt-1 group-hover/item">
                                                <div class="flex items-center w-full">
                                                    <div class="w-7 h-7 bg-indigo-50 rounded-lg flex items-center justify-center mr-3">
                                                        <Wrench class="h-4 w-4 text-indigo-600" />
                                                    </div>
                                                    <span class="font-bold text-indigo-700">Impersonate</span>
                                                </div>
                                            </DropdownMenuItem>

                                            <DropdownMenuSeparator class="my-1 bg-slate-50" />

                                            <DropdownMenuItem
                                                @click="deleteBusiness(business.id, business.name)"
                                                class="cursor-pointer rounded-xl h-10 hover:bg-red-50 mt-1"
                                            >
                                                <div class="flex items-center w-full text-red-600">
                                                    <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                                        <Trash2 class="h-4 w-4" />
                                                    </div>
                                                    <span class="font-black text-[11px] uppercase tracking-tighter">Delete Business</span>
                                                </div>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="props.businesses.data.length === 0">
                                <TableCell colspan="7" class="h-64 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center">
                                            <Building2 class="h-8 w-8 text-slate-200" />
                                        </div>
                                        <p class="text-slate-400 font-medium text-lg">No businesses found matching your filters.</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div class="p-6 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                            Page Results {{ paginationFrom }}-{{ paginationTo }} of {{ paginationTotal }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                :disabled="!prevLink"
                                @click="router.get(prevLink)"
                                class="font-bold h-9 px-4 rounded-lg bg-white border-slate-200 text-slate-600 shadow-sm"
                            >
                                Prev
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="!nextLink"
                                @click="router.get(nextLink)"
                                class="font-bold h-9 px-4 rounded-lg bg-white border-slate-200 text-slate-600 shadow-sm"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </template>

                <div v-else class="p-12 text-center">
                    <div class="animate-pulse space-y-4">
                        <div class="h-4 bg-slate-100 rounded w-3/4 mx-auto"></div>
                        <div class="h-4 bg-slate-100 rounded w-1/2 mx-auto"></div>
                        <div class="h-4 bg-slate-100 rounded w-2/3 mx-auto"></div>
                    </div>
                    <p class="mt-4 text-slate-400 font-bold uppercase text-[10px] tracking-widest">Fetching Engine Data...</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
