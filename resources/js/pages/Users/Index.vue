<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { Plus, Search, Filter, Edit, Trash2, UserCheck, UserX, Shield, Users, Eye } from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/AppLayout.vue'


// Get page props
const page = usePage()
const props = defineProps<{
  users?: {
    data: Array<{
      id: number
      name: string
      email: string
      role: string
      is_active: boolean
      email_verified_at: string | null
      created_at: string
      last_login: string | null
    }>
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: {
    search?: string
    role?: string
    status?: string
  }
  availableRoles: Array<{
    id: number
    name: string
    display_name: string
    level: number
  }>
}>()

// Reactive data
const search = ref(props.filters?.search || '')
const roleFilter = ref(props.filters?.role || '')
const statusFilter = ref(props.filters?.status || '')
const showModal = ref(false)
const editingUser = ref(null)
const showUserModal = ref(false)
const selectedUser = ref(null)

// Mock data for when backend doesn't return data
const mockUsers = {
  data: [
    {
      id: 1,
      name: 'John Admin',
      email: 'admin@example.com',
      role: 'admin',
      is_active: true,
      email_verified_at: '2024-01-01T00:00:00Z',
      created_at: '2024-01-01T00:00:00Z',
      last_login: '2024-01-22T10:30:00Z',
    },
    {
      id: 2,
      name: 'Jane Cashier',
      email: 'cashier@example.com',
      role: 'cashier',
      is_active: true,
      email_verified_at: '2024-01-02T00:00:00Z',
      created_at: '2024-01-02T00:00:00Z',
      last_login: '2024-01-22T09:15:00Z',
    },
    {
      id: 3,
      name: 'Bob Auditor',
      email: 'auditor@example.com',
      role: 'auditor',
      is_active: true,
      email_verified_at: '2024-01-03T00:00:00Z',
      created_at: '2024-01-03T00:00:00Z',
      last_login: '2024-01-21T16:45:00Z',
    },
  ],
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 3,
}

// Use props data if available, otherwise use mock data
const usersData = ref(props.users || mockUsers)

// Form for creating/editing users
const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: props.availableRoles?.[0]?.id || '',
  is_active: true,
})

// Computed properties
const roleBadgeVariant = (role: string) => {
  switch (role) {
    case 'admin': return 'destructive'
    case 'auditor': return 'secondary'
    case 'cashier': return 'default'
    default: return 'outline'
  }
}

const roleIcon = (role: string) => {
  switch (role) {
    case 'admin': return Shield
    case 'auditor': return Eye
    case 'cashier': return UserCheck
    default: return Users
  }
}

const selectedRole = computed(() => {
  return props.availableRoles.find(r => r.id == form.role_id)
})

// Methods
const applyFilters = () => {
  router.get('/users', {
    search: search.value,
    role: roleFilter.value,
    status: statusFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const openCreateModal = () => {
  editingUser.value = null
  form.reset()
  form.role_id = props.availableRoles?.[0]?.id || ''
  form.is_active = true
  showModal.value = true
}

const openEditModal = (user: any) => {
  editingUser.value = user
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.password_confirmation = ''
  form.role_id = user.role_id
  form.is_active = user.is_active
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  form.reset()
  editingUser.value = null
}

const submitForm = () => {
  if (editingUser.value) {
    form.put(`/users/${editingUser.value.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        closeModal()
        router.reload()
      },
      onError: (errors) => {
        console.error('Update failed:', errors)
      }
    })
  } else {
    form.post('/users', {
      preserveScroll: true,
      onSuccess: () => {
        closeModal()
        router.reload()
      },
      onError: (errors) => {
        console.error('Create failed:', errors)
      }
    })
  }
}

const deleteUser = (user: any) => {
  if (confirm(`Delete user ${user.name}? This action cannot be undone.`)) {
    router.delete(`/users/${user.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        router.reload()
      },
      onError: (errors) => {
        console.error('Delete failed:', errors)
      }
    })
  }
}

const toggleUserStatus = (user: any) => {
  const action = user.is_active ? 'deactivate' : 'activate'
  if (confirm(`Are you sure you want to ${action} user ${user.name}?`)) {
    router.post(`/users/${user.id}/toggle-status`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        router.reload()
      },
      onError: (errors) => {
        console.error('Status toggle failed:', errors)
      }
    })
  }
}

const viewUser = (user: any) => {
  selectedUser.value = user
  showUserModal.value = true
}

const closeUserModal = () => {
  showUserModal.value = false
  selectedUser.value = null
}
</script>

<template>
  <Head title="Users Management" />

  <AppLayout title="Users">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 p-3 sm:p-6">
      <div class="mx-auto w-full max-w-[1800px] space-y-4 sm:space-y-6">
        <!-- Header - Mobile Optimized -->
        <div class="relative overflow-hidden rounded-xl sm:rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-4 sm:p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -mr-16 sm:-mr-32 -mt-16 sm:-mt-32"></div>
          <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="rounded-lg sm:rounded-xl bg-white/20 backdrop-blur p-2 sm:p-3 flex-shrink-0">
                  <Users class="h-5 w-5 sm:h-8 sm:w-8" />
                </div>
                <div class="min-w-0 flex-1">
                  <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold truncate">User Management</h1>
                  <p class="text-blue-100 text-xs sm:text-base lg:text-lg mt-0.5 sm:mt-1 truncate">{{ usersData.total }} users in the system</p>
                </div>
              </div>
            </div>
            <Button @click="openCreateModal" class="bg-white text-blue-600 hover:bg-blue-50 gap-2 h-10 sm:h-12 px-3 sm:px-6 text-xs sm:text-sm w-full sm:w-auto flex-shrink-0">
              <Plus class="h-4 w-4 sm:h-5 sm:w-5" />
              <span class="hidden xs:inline">Add User</span>
              <span class="xs:hidden">Add</span>
            </Button>
          </div>
        </div>

        <!-- Filters - Mobile Optimized -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardContent class="p-3 sm:p-6 pt-3 sm:pt-6">
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
              <div class="flex-1">
                <div class="relative">
                  <Search class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-4 w-4 sm:h-5 sm:w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search by name..."
                    @keyup.enter="applyFilters"
                    class="pl-9 sm:pl-12 h-10 sm:h-12 border-2 focus:border-blue-500 text-xs sm:text-sm"
                  />
                </div>
              </div>
              <Select v-model="roleFilter">
                <SelectTrigger class="h-10 sm:h-12 text-xs sm:text-sm">
                  <SelectValue placeholder="Filter by role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Roles</SelectItem>
                  <SelectItem v-for="role in availableRoles" :key="role.id" :value="role.name">
                    {{ role.display_name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <Select v-model="statusFilter">
                <SelectTrigger class="h-10 sm:h-12 text-xs sm:text-sm">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Status</SelectItem>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>
              <Button @click="applyFilters" class="h-12 px-6 gap-2 bg-gradient-to-r from-blue-600 to-indigo-600">
                <Filter class="h-5 w-5" />
                Apply
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Users Table - Mobile Optimized -->
        <Card class="border-0 shadow-2xl bg-white/90 backdrop-blur">
          <CardHeader class="p-3 sm:p-6 border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
              <CardTitle class="text-lg sm:text-2xl flex items-center gap-2">
                <Shield class="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" />
                <span class="truncate">Users</span>
              </CardTitle>
              <div class="text-xs sm:text-sm text-slate-600 whitespace-nowrap">
                Showing {{ usersData.data.length }} of {{ usersData.total }}
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-0">
            <div class="overflow-x-auto">
              <Table class="min-w-full">
                <TableHeader>
                  <TableRow class="bg-slate-50/50">
                    <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3">User</TableHead>
                    <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">Role</TableHead>
                    <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3">Status</TableHead>
                    <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">Last Login</TableHead>
                    <TableHead class="font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">Created</TableHead>
                    <TableHead class="text-right font-semibold text-xs sm:text-sm px-2 sm:px-4 py-2 sm:py-3">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow
                    v-for="user in usersData.data"
                    :key="user.id"
                    class="hover:bg-blue-50/50 transition-colors"
                  >
                    <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-xs sm:text-sm flex-shrink-0">
                          {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                          <div class="font-semibold text-slate-900 text-xs sm:text-sm truncate">{{ user.name }}</div>
                          <div class="text-[10px] sm:text-xs text-slate-500 truncate hidden xs:block">{{ user.email }}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">
                      <Badge variant="outline" class="flex items-center gap-1 w-fit bg-slate-50 text-[10px] sm:text-xs">
                        <Shield class="h-3 w-3 text-indigo-600" />
                        {{ user.role.charAt(0).toUpperCase() + user.role.slice(1) }}
                      </Badge>
                    </TableCell>
                    <TableCell class="px-2 sm:px-4 py-2 sm:py-3">
                      <Badge :variant="user.is_active ? 'default' : 'secondary'" class="text-[10px] sm:text-xs">
                        {{ user.is_active ? 'Active' : 'Inactive' }}
                      </Badge>
                    </TableCell>
                    <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell text-xs sm:text-sm">
                      {{ user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never' }}
                    </TableCell>
                    <TableCell class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell text-xs sm:text-sm">
                      {{ new Date(user.created_at).toLocaleDateString() }}
                    </TableCell>
                    <TableCell class="text-right px-2 sm:px-4 py-2 sm:py-3">
                      <div class="flex justify-end gap-1 sm:gap-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          @click="viewUser(user)"
                          class="hover:bg-blue-100 hover:text-blue-600 h-8 sm:h-9 px-2 sm:px-3 text-xs"
                        >
                        <Eye class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="openEditModal(user)"
                        class="hover:bg-green-100 hover:text-green-600"
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="toggleUserStatus(user)"
                        :class="user.is_active ? 'hover:bg-orange-100 hover:text-orange-600' : 'hover:bg-green-100 hover:text-green-600'"
                      >
                        <component :is="user.is_active ? UserX : UserCheck" class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="deleteUser(user)"
                        class="hover:bg-red-100 hover:text-red-600"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>

            <!-- Pagination - Mobile Optimized -->
            <div v-if="usersData.last_page > 1" class="flex justify-center gap-1 sm:gap-2 p-3 sm:p-6 bg-slate-50/50 border-t overflow-x-auto">
              <Button
                v-for="page in usersData.last_page"
                :key="page"
                :variant="page === usersData.current_page ? 'default' : 'outline'"
                size="sm"
                @click="router.get(`/users?page=${page}`)"
                :class="page === usersData.current_page ? 'bg-gradient-to-r from-blue-600 to-indigo-600' : ''"
                class="h-8 sm:h-9 px-2 sm:px-3 text-xs sm:text-sm min-w-fit"
              >
                {{ page }}
              </Button>
            </div>
            </div>
          </CardContent>
        </Card>

        <!-- Create/Edit User Modal - Mobile Optimized -->
        <Dialog v-model:open="showModal">
          <DialogContent class="max-w-[95vw] sm:max-w-[600px] max-h-[90vh] overflow-y-auto p-3 sm:p-6">
            <DialogHeader>
              <DialogTitle class="text-lg sm:text-2xl flex items-center gap-2 mb-4">
                <Users class="h-6 w-6 text-blue-600" />
                {{ editingUser ? 'Edit User' : 'Add New User' }}
              </DialogTitle>
              <DialogDescription>
                {{ editingUser ? 'Update user information and permissions' : 'Create a new user account with appropriate role' }}
              </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-4 sm:space-y-6 py-3 sm:py-4">
              <!-- Basic Information -->
              <div class="space-y-3 sm:space-y-4">
                <div class="space-y-1.5 sm:space-y-2">
                  <Label for="user-name" class="text-sm sm:text-base font-semibold flex items-center gap-2">
                    <Users class="h-3 w-3 sm:h-4 sm:w-4" />
                    Full Name *
                  </Label>
                  <Input
                    id="user-name"
                    v-model="form.name"
                    placeholder="John Doe"
                    required
                    class="h-9 sm:h-10 text-sm sm:text-base"
                    :class="form.errors.name ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.name" class="text-xs sm:text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1.5 sm:space-y-2">
                  <Label for="user-email" class="text-sm sm:text-base font-semibold">Email Address *</Label>
                  <Input
                    id="user-email"
                    v-model="form.email"
                    type="email"
                    placeholder="john@example.com"
                    required
                    class="h-9 sm:h-10 text-sm sm:text-base"
                    :class="form.errors.email ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.email" class="text-xs sm:text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
              </div>

              <!-- Password (only for new users or when changing) -->
              <div v-if="!editingUser" class="space-y-3 sm:space-y-4">
                <div class="space-y-1.5 sm:space-y-2">
                  <Label for="user-password" class="text-sm sm:text-base font-semibold">Password *</Label>
                  <Input
                    id="user-password"
                    v-model="form.password"
                    type="password"
                    placeholder="Enter password"
                    required
                    class="h-9 sm:h-10 text-sm sm:text-base"
                    :class="form.errors.password ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.password" class="text-xs sm:text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div class="space-y-1.5 sm:space-y-2">
                  <Label for="user-password-confirmation" class="text-sm sm:text-base font-semibold">Confirm Password *</Label>
                  <Input
                    id="user-password-confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Confirm password"
                    required
                    class="h-9 sm:h-10 text-sm sm:text-base"
                    :class="form.errors.password_confirmation ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.password_confirmation" class="text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
                </div>
              </div>

              <!-- Role Selection -->
              <div class="space-y-3 p-4 bg-blue-50 rounded-lg border-2 border-blue-200">
                <h3 class="font-semibold text-blue-900 flex items-center gap-2">
                  <Shield class="h-5 w-5" />
                  User Role & Permissions
                </h3>
                <div class="space-y-2 sm:space-y-3">
                  <div class="space-y-1.5 sm:space-y-2">
                    <Label for="user-role" class="text-sm sm:text-base font-semibold">Structural Role *</Label>
                    <Select v-model="form.role_id">
                      <SelectTrigger class="h-9 sm:h-10 text-sm sm:text-base">
                        <SelectValue placeholder="Select platform role" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="role in availableRoles" :key="role.id" :value="role.id">
                           <div class="flex items-center gap-2">
                            <Shield class="h-3 w-3 sm:h-4 sm:w-4" />
                            <div>
                              <div class="font-medium text-xs sm:text-sm">{{ role.display_name }}</div>
                              <div class="text-[10px] sm:text-xs text-slate-500">Level {{ role.level }} Rank</div>
                            </div>
                          </div>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <!-- Role Description -->
                  <div v-if="selectedRole" class="p-2 sm:p-3 bg-white rounded border">
                    <div class="text-xs sm:text-sm">
                      <strong>{{ selectedRole.display_name }} Structural Mandate:</strong>
                      <p class="mt-1 text-[10px] sm:text-xs text-slate-600 italic leading-relaxed">
                        {{ selectedRole.description || "Refer to platform configuration for detailed capabilities." }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Account Status -->
              <div class="space-y-2 sm:space-y-3">
                <div class="flex items-center justify-between rounded-lg border-2 border-green-100 bg-green-50/50 p-2 sm:p-4">
                  <div>
                    <Label class="text-sm sm:text-base font-semibold">Account Active</Label>
                    <p class="text-xs sm:text-sm text-slate-600">User can log in and access the system</p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="form.is_active"
                    class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                  />
                </div>
              </div>

              <DialogFooter class="gap-2 pt-3 sm:pt-4 border-t flex flex-col-reverse sm:flex-row">
                <Button
                  type="button"
                  variant="outline"
                  @click="closeModal"
                  class="h-9 sm:h-10 px-3 sm:px-6 text-xs sm:text-sm"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="h-9 sm:h-10 px-3 sm:px-6 text-xs sm:text-sm bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                >
                  <Plus v-if="!editingUser" class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                  <Edit v-else class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                  {{ form.processing ? 'Saving...' : (editingUser ? 'Update User' : 'Create User') }}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <!-- View User Modal - Mobile Optimized -->
        <Dialog v-model:open="showUserModal">
          <DialogContent class="max-w-[95vw] sm:max-w-[500px] p-3 sm:p-6">
            <DialogHeader>
              <DialogTitle class="text-lg sm:text-2xl flex items-center gap-2">
                <Eye class="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" />
                User Details
              </DialogTitle>
            </DialogHeader>

            <div v-if="selectedUser" class="space-y-4 py-4">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-xl">
                  {{ selectedUser.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <h3 class="text-xl font-bold">{{ selectedUser.name }}</h3>
                  <p class="text-slate-600">{{ selectedUser.email }}</p>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label class="text-sm font-medium text-slate-500">Role</Label>
                  <Badge :variant="roleBadgeVariant(selectedUser.role)" class="flex items-center gap-1 w-fit">
                    <component :is="roleIcon(selectedUser.role)" class="h-3 w-3" />
                    {{ selectedUser.role.charAt(0).toUpperCase() + selectedUser.role.slice(1) }}
                  </Badge>
                </div>

                <div class="space-y-2">
                  <Label class="text-sm font-medium text-slate-500">Status</Label>
                  <Badge :variant="selectedUser.is_active ? 'default' : 'secondary'">
                    {{ selectedUser.is_active ? 'Active' : 'Inactive' }}
                  </Badge>
                </div>

                <div class="space-y-2">
                  <Label class="text-sm font-medium text-slate-500">Email Verified</Label>
                  <Badge :variant="selectedUser.email_verified_at ? 'default' : 'secondary'">
                    {{ selectedUser.email_verified_at ? 'Verified' : 'Unverified' }}
                  </Badge>
                </div>

                <div class="space-y-2">
                  <Label class="text-sm font-medium text-slate-500">Last Login</Label>
                  <div class="text-sm">
                    {{ selectedUser.last_login ? new Date(selectedUser.last_login).toLocaleString() : 'Never' }}
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <Label class="text-sm font-medium text-slate-500">Created</Label>
                <div class="text-sm">
                  {{ new Date(selectedUser.created_at).toLocaleString() }}
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button @click="closeUserModal" variant="outline">
                Close
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.backdrop-blur {
  backdrop-filter: blur(10px);
}
</style>
