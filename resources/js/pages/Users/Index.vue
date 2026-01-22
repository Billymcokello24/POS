<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Search, Filter, Edit, Trash2, UserCheck, UserX, Shield, Users, Eye } from 'lucide-vue-next'

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
  role: 'cashier',
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
    case 'cashier': return Users
    default: return Users
  }
}

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
  form.role = 'cashier'
  form.is_active = true
  showModal.value = true
}

const openEditModal = (user: any) => {
  editingUser.value = user
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.password_confirmation = ''
  form.role = user.role
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
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Users class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">User Management</h1>
                  <p class="text-blue-100 text-lg mt-1">{{ usersData.total }} users in the system</p>
                </div>
              </div>
            </div>
            <Button @click="openCreateModal" class="bg-white text-blue-600 hover:bg-blue-50 gap-2 h-12 px-6">
              <Plus class="h-5 w-5" />
              Add User
            </Button>
          </div>
        </div>

        <!-- Filters -->
        <Card class="border-0 shadow-xl bg-white/90 backdrop-blur">
          <CardContent class="pt-6">
            <div class="flex flex-wrap gap-4">
              <div class="flex-1 min-w-[300px]">
                <div class="relative">
                  <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                  <Input
                    v-model="search"
                    placeholder="Search by name or email..."
                    @keyup.enter="applyFilters"
                    class="pl-12 h-12 border-2 focus:border-blue-500"
                  />
                </div>
              </div>
              <Select v-model="roleFilter">
                <SelectTrigger class="h-12 w-48">
                  <SelectValue placeholder="Filter by role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Roles</SelectItem>
                  <SelectItem value="admin">Admin</SelectItem>
                  <SelectItem value="cashier">Cashier</SelectItem>
                  <SelectItem value="auditor">Auditor</SelectItem>
                </SelectContent>
              </Select>
              <Select v-model="statusFilter">
                <SelectTrigger class="h-12 w-48">
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

        <!-- Users Table -->
        <Card class="border-0 shadow-2xl bg-white/90 backdrop-blur">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <div class="flex items-center justify-between">
              <CardTitle class="text-2xl flex items-center gap-2">
                <Shield class="h-6 w-6 text-blue-600" />
                Users
              </CardTitle>
              <div class="text-sm text-slate-600">
                Showing {{ usersData.data.length }} of {{ usersData.total }}
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow class="bg-slate-50/50">
                  <TableHead class="font-semibold">User</TableHead>
                  <TableHead class="font-semibold">Role</TableHead>
                  <TableHead class="font-semibold">Status</TableHead>
                  <TableHead class="font-semibold">Last Login</TableHead>
                  <TableHead class="font-semibold">Created</TableHead>
                  <TableHead class="text-right font-semibold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="user in usersData.data"
                  :key="user.id"
                  class="hover:bg-blue-50/50 transition-colors"
                >
                  <TableCell>
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                        {{ user.name.charAt(0).toUpperCase() }}
                      </div>
                      <div>
                        <div class="font-semibold text-slate-900">{{ user.name }}</div>
                        <div class="text-sm text-slate-500">{{ user.email }}</div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="roleBadgeVariant(user.role)" class="flex items-center gap-1">
                      <component :is="roleIcon(user.role)" class="h-3 w-3" />
                      {{ user.role.charAt(0).toUpperCase() + user.role.slice(1) }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="user.is_active ? 'default' : 'secondary'">
                      {{ user.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="text-sm">
                      {{ user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never' }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="text-sm">
                      {{ new Date(user.created_at).toLocaleDateString() }}
                    </div>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="viewUser(user)"
                        class="hover:bg-blue-100 hover:text-blue-600"
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

            <!-- Pagination -->
            <div v-if="usersData.last_page > 1" class="flex justify-center gap-2 p-6 bg-slate-50/50 border-t">
              <Button
                v-for="page in usersData.last_page"
                :key="page"
                :variant="page === usersData.current_page ? 'default' : 'outline'"
                size="sm"
                @click="router.get(`/users?page=${page}`)"
                :class="page === usersData.current_page ? 'bg-gradient-to-r from-blue-600 to-indigo-600' : ''"
              >
                {{ page }}
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Create/Edit User Modal -->
        <Dialog v-model:open="showModal">
          <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle class="text-2xl flex items-center gap-2">
                <Users class="h-6 w-6 text-blue-600" />
                {{ editingUser ? 'Edit User' : 'Add New User' }}
              </DialogTitle>
              <DialogDescription>
                {{ editingUser ? 'Update user information and permissions' : 'Create a new user account with appropriate role' }}
              </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-6 py-4">
              <!-- Basic Information -->
              <div class="space-y-4">
                <div class="space-y-2">
                  <Label for="user-name" class="text-base font-semibold flex items-center gap-2">
                    <Users class="h-4 w-4" />
                    Full Name *
                  </Label>
                  <Input
                    id="user-name"
                    v-model="form.name"
                    placeholder="John Doe"
                    required
                    class="h-12"
                    :class="form.errors.name ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                  <Label for="user-email" class="text-base">Email Address *</Label>
                  <Input
                    id="user-email"
                    v-model="form.email"
                    type="email"
                    placeholder="john@example.com"
                    required
                    class="h-12"
                    :class="form.errors.email ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
              </div>

              <!-- Password (only for new users or when changing) -->
              <div v-if="!editingUser" class="space-y-4">
                <div class="space-y-2">
                  <Label for="user-password" class="text-base">Password *</Label>
                  <Input
                    id="user-password"
                    v-model="form.password"
                    type="password"
                    placeholder="Enter password"
                    required
                    class="h-12"
                    :class="form.errors.password ? 'border-red-500' : ''"
                  />
                  <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div class="space-y-2">
                  <Label for="user-password-confirmation" class="text-base">Confirm Password *</Label>
                  <Input
                    id="user-password-confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Confirm password"
                    required
                    class="h-12"
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
                <div class="space-y-3">
                  <div class="space-y-2">
                    <Label for="user-role" class="text-base">Role *</Label>
                    <Select v-model="form.role">
                      <SelectTrigger class="h-12">
                        <SelectValue placeholder="Select user role" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="cashier">
                          <div class="flex items-center gap-2">
                            <Users class="h-4 w-4" />
                            <div>
                              <div class="font-medium">Cashier</div>
                              <div class="text-xs text-slate-500">Can process sales, view own sales</div>
                            </div>
                          </div>
                        </SelectItem>
                        <SelectItem value="auditor">
                          <div class="flex items-center gap-2">
                            <Eye class="h-4 w-4" />
                            <div>
                              <div class="font-medium">Auditor</div>
                              <div class="text-xs text-slate-500">Can view all sales and reports</div>
                            </div>
                          </div>
                        </SelectItem>
                        <SelectItem value="admin">
                          <div class="flex items-center gap-2">
                            <Shield class="h-4 w-4" />
                            <div>
                              <div class="font-medium">Admin</div>
                              <div class="text-xs text-slate-500">Full access to all features</div>
                            </div>
                          </div>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <!-- Role Description -->
                  <div class="p-3 bg-white rounded border">
                    <div class="text-sm">
                      <strong>{{ form.role.charAt(0).toUpperCase() + form.role.slice(1) }} Permissions:</strong>
                      <ul class="mt-1 ml-4 list-disc text-xs text-slate-600">
                        <li v-if="form.role === 'admin'">Manage users, view all sales, full system access</li>
                        <li v-if="form.role === 'auditor'">View all sales and reports, no editing permissions</li>
                        <li v-if="form.role === 'cashier'">Process sales, view own sales only</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Account Status -->
              <div class="space-y-3">
                <div class="flex items-center justify-between rounded-lg border-2 border-green-100 bg-green-50/50 p-4">
                  <div>
                    <Label class="text-base font-semibold">Account Active</Label>
                    <p class="text-sm text-slate-600">User can log in and access the system</p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="form.is_active"
                    class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                  />
                </div>
              </div>

              <DialogFooter class="gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  @click="closeModal"
                  class="h-12 px-6"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                >
                  <Plus v-if="!editingUser" class="h-4 w-4 mr-2" />
                  <Edit v-else class="h-4 w-4 mr-2" />
                  {{ form.processing ? 'Saving...' : (editingUser ? 'Update User' : 'Create User') }}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <!-- View User Modal -->
        <Dialog v-model:open="showUserModal">
          <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle class="text-2xl flex items-center gap-2">
                <Eye class="h-6 w-6 text-blue-600" />
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
