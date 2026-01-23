<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Users, Shield, Eye, ArrowLeft } from 'lucide-vue-next'

const props = defineProps<{
  roles: Array<{
    id: number
    name: string
    display_name: string
    description: string
    level: number
  }>
}>()

// Form for creating users
const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: props.roles?.[0]?.id || '',
  is_active: true,
})

const selectedRole = computed(() => {
  return props.roles.find(r => r.id == form.role_id)
})

// Methods
const submitForm = () => {
  form.post('/users', {
    preserveScroll: true,
    onSuccess: () => {
      router.visit('/users')
    },
    onError: (errors) => {
      console.error('Create failed:', errors)
    }
  })
}

const goBack = () => {
  router.visit('/users')
}
</script>

<template>
  <Head title="Create User" />

  <AppLayout title="Create User">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Plus class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Create New User</h1>
                  <p class="text-blue-100 text-lg mt-1">Add a new team member to your business</p>
                </div>
              </div>
            </div>
            <Button @click="goBack" variant="outline" class="bg-white/10 border-white/20 text-white hover:bg-white/20 gap-2 h-12 px-6">
              <ArrowLeft class="h-5 w-5" />
              Back to Users
            </Button>
          </div>
        </div>

        <!-- Create User Form -->
        <Card class="border-0 shadow-2xl bg-white/90 backdrop-blur">
          <CardHeader class="border-b bg-gradient-to-r from-slate-50 to-slate-100">
            <CardTitle class="text-2xl flex items-center gap-2">
              <Users class="h-6 w-6 text-blue-600" />
              User Information
            </CardTitle>
          </CardHeader>
          <CardContent class="p-8">
            <form @submit.prevent="submitForm" class="space-y-8">
              <!-- Basic Information -->
              <div class="space-y-6">
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

              <!-- Password -->
              <div class="space-y-6">
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
              <div class="space-y-3 p-6 bg-blue-50 rounded-lg border-2 border-blue-200">
                <h3 class="font-semibold text-blue-900 flex items-center gap-2">
                  <Shield class="h-5 w-5" />
                  User Role & Permissions
                </h3>
                <div class="space-y-4">
                  <div class="space-y-2">
                    <Label for="user-role" class="text-base">Role *</Label>
                    <Select v-model="form.role_id">
                      <SelectTrigger class="h-12">
                        <SelectValue placeholder="Select user role" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="role in props.roles" :key="role.id" :value="role.id">
                          <div class="flex items-center gap-2 p-2">
                            <Shield class="h-4 w-4" />
                            <div>
                              <div class="font-medium">{{ role.display_name }}</div>
                              <div class="text-xs text-slate-500">Level {{ role.level }} Access</div>
                            </div>
                          </div>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <!-- Role Description -->
                  <div v-if="selectedRole" class="p-4 bg-white rounded border">
                    <div class="text-sm">
                      <strong>{{ selectedRole.display_name }} Mandate & Scope:</strong>
                      <p class="mt-2 text-xs text-slate-600 leading-relaxed italic">
                        {{ selectedRole.description }}
                      </p>
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

              <!-- Form Actions -->
              <div class="flex justify-end gap-4 pt-6 border-t">
                <Button
                  type="button"
                  variant="outline"
                  @click="goBack"
                  class="h-12 px-6"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                >
                  <Plus class="h-4 w-4 mr-2" />
                  {{ form.processing ? 'Creating...' : 'Create User' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
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
