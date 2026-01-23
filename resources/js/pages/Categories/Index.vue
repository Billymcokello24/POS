<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
  Box,
  Plus,
  Edit,
  Trash2,
  Sparkles,
  Package,
  X
} from 'lucide-vue-next'

interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  products_count: number
  is_active: boolean
}

const props = defineProps<{
  categories?: Category[]
}>()

const page = usePage()

const showModal = ref(false)
const editingCategory = ref<Category | null>(null)

const form = useForm({
  name: '',
  description: '',
  is_active: true,
})

const categories = ref(props.categories || [])

const openCreateModal = () => {
  editingCategory.value = null
  form.reset()
  form.is_active = true
  showModal.value = true
}

const openEditModal = (category: Category) => {
  editingCategory.value = category
  form.name = category.name
  form.description = category.description || ''
  form.is_active = category.is_active
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  form.reset()
  editingCategory.value = null
}

const submitForm = () => {
  if (editingCategory.value) {
    form.put(`/categories/${editingCategory.value.id}`, {
      onSuccess: () => {
        closeModal()
      }
    })
  } else {
    form.post('/categories', {
      onSuccess: () => {
        closeModal()
      }
    })
  }
}

const deleteCategory = (category: Category) => {
  if (confirm(`Delete category "${category.name}"?`)) {
    router.delete(`/categories/${category.id}`, {
      preserveScroll: true,
    })
  }
}
</script>

<template>
  <Head title="Categories" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto w-[90%] space-y-6">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-2">
                <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                  <Box class="h-8 w-8" />
                </div>
                <div>
                  <h1 class="text-4xl font-bold">Product Categories</h1>
                  <p class="text-purple-100 text-lg mt-1">Organize your inventory</p>
                </div>
              </div>
            </div>
            <Button
              v-if="(page.props.auth as any).permissions?.includes('create_categories')"
              @click="openCreateModal"
              class="bg-white text-purple-600 hover:bg-purple-50 gap-2 h-12 px-6"
            >
              <Plus class="h-5 w-5" />
              Add Category
            </Button>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-3">
          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Total Categories</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-purple-600">{{ categories.length }}</div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Total Products</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-pink-600">
                {{ categories.reduce((sum, cat) => sum + cat.products_count, 0) }}
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl">
            <CardHeader class="pb-3">
              <CardTitle class="text-sm text-slate-600">Active Categories</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-3xl font-bold text-orange-600">
                {{ categories.filter(cat => cat.is_active).length }}
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Categories Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="category in categories"
            :key="category.id"
            class="border-0 shadow-xl bg-white hover:shadow-2xl transition-all group"
          >
            <CardHeader>
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 p-3 text-white group-hover:scale-110 transition-transform">
                    <Box class="h-6 w-6" />
                  </div>
                  <div>
                    <CardTitle class="text-xl">{{ category.name }}</CardTitle>
                    <Badge
                      :class="category.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'"
                      class="mt-2"
                    >
                      {{ category.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                  </div>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <p class="text-sm text-slate-600">
                {{ category.description || 'No description' }}
              </p>

              <div class="flex items-center gap-2 text-slate-700">
                <Package class="h-4 w-4" />
                <span class="text-sm font-semibold">{{ category.products_count }} Products</span>
              </div>

              <div class="flex gap-2 pt-4 border-t">
                <Button
                  variant="outline"
                  size="sm"
                  class="flex-1 hover:bg-purple-50 hover:border-purple-300"
                  @click="router.visit(`/products?category_id=${category.id}`)"
                >
                  <Package class="h-4 w-4 mr-2" />
                  View Products
                </Button>
                <Button
                  v-if="(page.props.auth as any).permissions?.includes('edit_categories')"
                  variant="ghost"
                  size="sm"
                  class="hover:bg-blue-100"
                  @click="openEditModal(category)"
                >
                  <Edit class="h-4 w-4" />
                </Button>
                <Button
                  v-if="(page.props.auth as any).permissions?.includes('delete_categories')"
                  variant="ghost"
                  size="sm"
                  class="hover:bg-red-100"
                  @click="deleteCategory(category)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Add New Card -->
          <Card
            v-if="(page.props.auth as any).permissions?.includes('create_categories')"
            @click="openCreateModal"
            class="border-2 border-dashed border-purple-300 bg-purple-50/50 hover:bg-purple-100/50 hover:border-purple-500 transition-all cursor-pointer group"
          >
            <CardContent class="flex flex-col items-center justify-center h-full min-h-[250px]">
              <div class="rounded-full bg-purple-200 group-hover:bg-purple-300 p-6 mb-4 transition-colors">
                <Plus class="h-12 w-12 text-purple-600" />
              </div>
              <h3 class="text-xl font-bold text-purple-900 mb-2">Add New Category</h3>
              <p class="text-sm text-purple-600">Click to create a new product category</p>
            </CardContent>
          </Card>
        </div>

        <!-- Add/Edit Modal -->
        <Dialog v-model:open="showModal">
          <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle class="text-2xl flex items-center gap-2">
                <Box class="h-6 w-6 text-purple-600" />
                {{ editingCategory ? 'Edit Category' : 'Add New Category' }}
              </DialogTitle>
              <DialogDescription>
                {{ editingCategory ? 'Update the category details below' : 'Create a new category for organizing your products' }}
              </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-4 py-4">
              <div class="space-y-2">
                <Label for="name" class="text-base">Category Name *</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="e.g., Electronics, Clothing, Food"
                  required
                  class="h-12"
                  :class="form.errors.name ? 'border-red-500' : ''"
                />
                <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
              </div>

              <div class="space-y-2">
                <Label for="description" class="text-base">Description</Label>
                <Textarea
                  id="description"
                  v-model="form.description"
                  placeholder="Optional description of this category"
                  rows="3"
                  class="resize-none"
                />
                <p v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</p>
              </div>

              <div class="flex items-center justify-between rounded-lg border-2 border-purple-100 bg-purple-50/50 p-4">
                <div>
                  <Label class="text-base font-semibold">Active Status</Label>
                  <p class="text-sm text-slate-600">Category is available for use</p>
                </div>
                <input
                  type="checkbox"
                  v-model="form.is_active"
                  class="h-5 w-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                />
              </div>

              <DialogFooter class="gap-2 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  @click="closeModal"
                  class="h-11"
                >
                  <X class="h-4 w-4 mr-2" />
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="h-11 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700"
                >
                  <Plus v-if="!editingCategory" class="h-4 w-4 mr-2" />
                  <Edit v-else class="h-4 w-4 mr-2" />
                  {{ form.processing ? 'Saving...' : (editingCategory ? 'Update Category' : 'Create Category') }}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  </AppLayout>
</template>

