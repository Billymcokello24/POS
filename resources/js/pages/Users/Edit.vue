<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Users, Shield, ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps<{
  user: {
    id: number
    name: string
    email: string
    role_id: number
    is_active: boolean
  }
  roles: Array<{
    id: number
    name: string
    display_name: string
    description: string
    level: number
  }>
}>()

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  password: '',
  password_confirmation: '',
  role_id: props.user.role_id,
  is_active: props.user.is_active,
})

const selectedRole = computed(() => {
  return props.roles.find(r => r.id == form.role_id)
})

const submitForm = () => {
  form.put(`/users/${props.user.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      router.visit('/users')
    }
  })
}

const goBack = () => {
  router.visit('/users')
}
</script>

<template>
  <Head :title="'Edit ' + user.name" />

  <AppLayout :title="'Edit ' + user.name">
    <div class="min-h-screen bg-slate-50 p-6">
      <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between">
           <Button @click="goBack" variant="ghost" class="gap-2">
            <ArrowLeft class="h-4 w-4" />
            Back to Team
          </Button>
          <div class="flex items-center gap-2">
            <Badge v-if="user.is_active" class="bg-emerald-500">ACTIVE</Badge>
            <Badge v-else variant="destructive">INACTIVE</Badge>
          </div>
        </div>

        <Card class="border-none shadow-xl bg-white">
          <CardHeader class="border-b bg-slate-50/50">
            <CardTitle class="text-xl flex items-center gap-2">
              <Users class="h-5 w-5 text-indigo-600" />
              Modify Team Member
            </CardTitle>
          </CardHeader>
          <CardContent class="p-8">
            <form @submit.prevent="submitForm" class="space-y-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                  <Label class="font-bold">Full Name</Label>
                  <Input v-model="form.name" required class="h-12 border-slate-200" />
                  <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-2">
                  <Label class="font-bold">Email Address</Label>
                  <Input v-model="form.email" type="email" required class="h-12 border-slate-200" />
                  <p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
                <div class="space-y-2">
                  <Label class="font-bold">New Password (Ignore to keep existing)</Label>
                  <Input v-model="form.password" type="password" class="h-12 border-slate-200" />
                </div>
                <div class="space-y-2">
                  <Label class="font-bold">Confirm New Password</Label>
                  <Input v-model="form.password_confirmation" type="password" class="h-12 border-slate-200" />
                </div>
              </div>

              <div class="space-y-4 pt-6">
                <div class="flex items-center gap-2 text-indigo-900 font-bold border-b pb-2">
                  <Shield class="h-5 w-5" />
                  Hierarchy & Authorization
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                  <div class="space-y-2">
                    <Label class="font-bold">Assigned Platform Role</Label>
                    <Select v-model="form.role_id">
                      <SelectTrigger class="h-12 border-slate-200">
                        <SelectValue placeholder="Select role" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="role in roles" :key="role.id" :value="role.id">
                           <div class="flex items-center justify-between w-full gap-8">
                              <span class="font-bold">{{ role.display_name }}</span>
                              <span class="text-[10px] text-slate-400">LVL {{ role.level }}</span>
                           </div>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div v-if="selectedRole" class="p-4 bg-slate-50 rounded-xl border border-slate-100 flex gap-4">
                    <div class="p-2 bg-white rounded-lg border h-fit shadow-sm">
                      <Shield class="h-5 w-5 text-indigo-600" />
                    </div>
                    <div>
                      <h4 class="font-black text-xs uppercase tracking-widest text-slate-400">Clearance Mandate</h4>
                      <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ selectedRole.description }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between p-6 bg-slate-50 rounded-2xl border border-slate-100 mt-8">
                 <div>
                    <Label class="font-bold text-slate-900">Account Authorization</Label>
                    <p class="text-xs text-slate-500">Enable or disable this user's ability to log in.</p>
                 </div>
                 <div class="flex items-center gap-3">
                    <span class="text-xs font-bold uppercase tracking-widest" :class="form.is_active ? 'text-emerald-600' : 'text-slate-400'">
                       {{ form.is_active ? 'Enabled' : 'Disabled' }}
                    </span>
                    <input type="checkbox" v-model="form.is_active" class="h-6 w-6 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                 </div>
              </div>

              <div class="flex justify-end gap-4 pt-8 border-t mt-8">
                <Button type="button" variant="outline" @click="goBack" class="px-8 h-12 font-bold text-slate-500 border-none">Cancel</Button>
                <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 h-12 px-10 font-bold shadow-lg shadow-indigo-600/20">
                  <Save class="h-4 w-4 mr-2" />
                  {{ form.processing ? 'Syncing...' : 'Update Member Credentials' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
