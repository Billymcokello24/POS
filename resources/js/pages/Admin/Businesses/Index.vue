<script setup lang="ts">
/* eslint-disable */
import { ref, watch, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
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
    Wrench
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
    if (!confirm('Are you sure you want to change the status of this business?')) return
    await router.post(`/admin/businesses/${id}/toggle-status`)
    // refresh
    router.reload()
}

const bulkToggleStatus = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Toggle status for ${selectedIds.value.length} selected business(es)?`)) return
  try {
    await Promise.all(selectedIds.value.map(id => router.post(`/admin/businesses/${id}/toggle-status`)))
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
    await Promise.all(selectedIds.value.map(id => router.post(`/admin/businesses/${id}/reset-password`)))
    clearSelection()
    router.reload()
  } catch (e) {
    console.error(e)
    alert('Bulk reset failed')
  }
}

const impersonate = async (id: number) => {
    if (!confirm('Log in as an administrator for this business? Your current session will be saved.')) return
    await router.post(`/admin/businesses/${id}/impersonate`)
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString()
}

// Export CSV of visible businesses
const exportCSV = () => {
  const rows = [['ID','Name','Email','Phone','Users','Sales','Status','Created']]
  props.businesses.data.forEach(b => rows.push([
    String(b.id), b.name, b.email, b.phone || '', String(b.users_count || 0), String(b.sales_count || 0), b.is_active ? 'Active' : 'Suspended', formatDate(b.created_at)
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

// Details modal
const showDetails = ref(false)
const detailsBusiness = ref<any>(null)
const openDetails = (b: any) => { detailsBusiness.value = b; showDetails.value = true }

// Safe pagination helpers (guard when meta or links are missing)
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
        <div class="space-y-6">
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Registered Businesses</h1>
                    <p class="text-slate-500 text-sm">Monitor and manage all entities on the platform.</p>
                </div>
                <!-- Future: Register New Business directly -->
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
                                class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-background placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-shadow appearance-none"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Suspended Only</option>
                            </select>
                        </div>

                        <div class="ml-auto flex gap-2">
                          <Button variant="outline" @click="exportCSV">Export CSV</Button>
                          <Button class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white" @click="bulkToggleStatus" :disabled="selectedCount===0">Toggle Status</Button>
                          <Button variant="ghost" @click="bulkResetPasswords" :disabled="selectedCount===0">Reset Passwords</Button>
                        </div>
                    </div>
                </div>

                <template v-if="props.businesses && props.businesses.data">
                <Table>
                    <TableHeader class="bg-slate-50">
                        <TableRow>
                            <TableHead class="w-12 px-4">
                              <input type="checkbox" v-model="allSelected" class="h-4 w-4" />
                            </TableHead>
                            <TableHead class="font-bold text-slate-700">Business Identity</TableHead>
                            <TableHead class="font-bold text-slate-700">Employees</TableHead>
                            <TableHead class="font-bold text-slate-700">Turnover (Sales)</TableHead>
                            <TableHead class="font-bold text-slate-700">Onboarding</TableHead>
                            <TableHead class="font-bold text-slate-700 text-center">Platform Status</TableHead>
                            <TableHead class="text-right font-bold text-slate-700 px-6">Management</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="business in props.businesses.data" :key="business.id" class="hover:bg-slate-50/50 transition-colors">
                            <TableCell class="py-4 px-4">
                                <input type="checkbox" :checked="isSelected(business.id)" @change="toggleSelect(business.id)" class="h-4 w-4" />
                            </TableCell>
                            <TableCell class="py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200 uppercase">
                                        {{ business.name.substring(0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ business.name }}</div>
                                        <div class="text-xs text-slate-500 font-medium">{{ business.email }}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Users class="h-4 w-4 text-slate-400" />
                                    <span class="font-bold text-slate-700">{{ business.users_count }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <ShoppingBag class="h-4 w-4 text-slate-400" />
                                    <span class="font-bold text-slate-700">{{ business.sales_count }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-sm text-slate-500 font-medium">
                                {{ formatDate(business.created_at) }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge
                                    :class="business.is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border-none' : 'bg-red-100 text-red-700 hover:bg-red-200 border-none'"
                                    class="px-2.5 py-1 font-bold rounded-full"
                                >
                                    {{ business.is_active ? 'Active' : 'Suspended' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right px-6">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" class="h-8 w-8 p-0 text-slate-400 hover:text-slate-900 border-none">
                                            <MoreVertical class="h-5 w-5" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48 shadow-xl border-slate-200">
                                        <DropdownMenuLabel>Control Menu</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem @click="toggleStatus(business.id)" class="cursor-pointer">
                                            <ShieldAlert class="mr-2 h-4 w-4" :class="business.is_active ? 'text-red-500' : 'text-emerald-500'" />
                                            {{ business.is_active ? 'Suspend Business' : 'Restore Business' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="resetPassword(business.id)" class="cursor-pointer">
                                            <KeyRound class="mr-2 h-4 w-4 text-amber-500" />
                                            Reset Credentials
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="openDetails(business)" class="cursor-pointer">
                                            <History class="mr-2 h-4 w-4 text-slate-400" />
                                            View Details
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem @click="impersonate(business.id)" class="cursor-pointer text-indigo-600 font-bold">
                                            <Wrench class="mr-2 h-4 w-4 bg-indigo-100 rounded p-0.5" />
                                            Impersonate Tenant
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="props.businesses.data.length === 0">
                            <TableCell colspan="7" class="h-48 text-center">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <Building2 class="h-12 w-12 text-slate-200" />
                                    <p class="text-slate-500 font-medium text-lg">No businesses found matching your filters.</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination -->
                <div class="p-4 border-t flex items-center justify-between">
                  <div class="text-sm text-slate-600">Showing {{ paginationFrom }} to {{ paginationTo }} of {{ paginationTotal }} results</div>
                  <div class="flex items-center gap-2">
                    <Button variant="outline" :disabled="!prevLink" @click="router.get(prevLink)">Prev</Button>
                    <Button variant="outline" :disabled="!nextLink" @click="router.get(nextLink)">Next</Button>
                  </div>
                </div>

                </template>

                <div v-else class="p-8 text-center text-slate-500">Loading businesses...</div>

            </div>
        </div>

        <!-- Details modal -->
        <div v-if="showDetails" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div class="bg-white rounded-lg shadow-xl w-[90%] max-w-2xl p-6">
            <div class="flex items-start justify-between">
              <h3 class="text-lg font-bold">Business Details</h3>
              <button @click="showDetails=false" class="text-slate-500">Close</button>
            </div>
            <div class="mt-4 space-y-2">
              <div><strong>Name:</strong> {{ detailsBusiness.name }}</div>
              <div><strong>Email:</strong> {{ detailsBusiness.email }}</div>
              <div><strong>Phone:</strong> {{ detailsBusiness.phone }}</div>
              <div><strong>Users:</strong> {{ detailsBusiness.users_count }}</div>
              <div><strong>Sales:</strong> {{ detailsBusiness.sales_count }}</div>
              <div><strong>Created:</strong> {{ formatDate(detailsBusiness.created_at) }}</div>
            </div>
            <div class="mt-6 flex justify-end">
              <Button @click="showDetails=false">Close</Button>
            </div>
          </div>
        </div>

    </AdminLayout>
</template>
