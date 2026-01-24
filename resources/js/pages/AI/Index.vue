<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Search, BarChart3, Clock, Zap } from 'lucide-vue-next'
import { ref, onMounted } from 'vue'
import axios from 'axios'

import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardHeader, CardContent, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

// Configure axios to send CSRF token and cookies for same-origin session-protected routes
onMounted(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
    }
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
    axios.defaults.withCredentials = true
})

const query = ref('')
const range = ref('last_30_days')
const results = ref<any>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function search() {
    loading.value = true
    error.value = null
    try {
        const res = await axios.post('/api/ai/search', { query: query.value, limit: 50 })
        results.value = res.data
    } catch (e: any) {
        error.value = e?.response?.data?.message || e.message || 'Request failed'
    } finally {
        loading.value = false
    }
}

async function report() {
    loading.value = true
    error.value = null
    try {
        const res = await axios.post('/api/ai/report', { range: range.value })
        results.value = res.data
    } catch (e: any) {
        error.value = e?.response?.data?.message || e.message || 'Request failed'
    } finally {
        loading.value = false
    }
}

async function slowMoving() {
    loading.value = true
    error.value = null
    try {
        const res = await axios.get('/api/ai/slow-moving?days=60&limit=20')
        results.value = res.data
    } catch (e: any) {
        error.value = e?.response?.data?.message || e.message || 'Request failed'
    } finally {
        loading.value = false
    }
}

async function availability() {
    loading.value = true
    error.value = null
    try {
        const res = await axios.get(`/api/ai/availability?sku=${encodeURIComponent(query.value)}`)
        results.value = res.data
    } catch (e: any) {
        error.value = e?.response?.data?.message || e.message || 'Request failed'
    } finally {
        loading.value = false
    }
}
</script>

<template>
  <Head title="AI Agent" />

  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
      <div class="mx-auto w-[90%] px-6 py-8 space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">AI Agent</h1>
            <p class="text-sm text-gray-600">Use the AI assistant to search inventory, generate reports, and find slow moving items.</p>
          </div>
          <div class="flex items-center gap-3">
            <Button class="bg-gradient-to-r from-blue-600 to-indigo-600" @click="report" :disabled="loading">
              <BarChart3 class="mr-2 h-4 w-4" /> Generate Report
            </Button>
            <Button variant="outline" @click="slowMoving" :disabled="loading">
              <Clock class="mr-2 h-4 w-4" /> Slow Moving
            </Button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Controls Card -->
          <Card class="lg:col-span-1">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Search class="h-5 w-5 text-blue-600" />
                Search Inventory
              </CardTitle>
              <CardDescription>Search by name, SKU or barcode</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Query / SKU</label>
                  <input v-model="query" type="text" class="mt-1 block w-full border rounded p-2" placeholder="e.g. rice, ABC123" />
                </div>

                <div class="flex gap-2">
                  <Button @click.prevent="search" :disabled="loading" class="flex-1">
                    <Search class="mr-2 h-4 w-4" /> Search
                  </Button>
                  <Button @click.prevent="availability" :disabled="loading" variant="ghost">Availability</Button>
                </div>

                <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

              </div>
            </CardContent>
          </Card>

          <!-- Results Card -->
          <Card class="lg:col-span-2">
            <CardHeader>
              <CardTitle>Results</CardTitle>
              <CardDescription>JSON output from the AI agent and data returned by the service</CardDescription>
            </CardHeader>
            <CardContent>
              <div v-if="loading" class="py-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                  <Zap class="h-8 w-8 text-gray-400" />
                </div>
                <p class="text-gray-600">Thinking...</p>
              </div>

              <div v-else-if="results">
                <div class="mb-4">
                  <h3 class="font-semibold text-lg">AI Narrative</h3>
                  <p class="text-gray-700 mt-2">{{ results.data?.narrative || results.data?.summary || results.data?.explanation || results.note || results.summary || '' }}</p>
                </div>

                <div>
                  <h4 class="font-medium mb-2">Raw Response</h4>
                  <pre class="bg-gray-100 p-4 rounded overflow-auto max-h-96">{{ JSON.stringify(results, null, 2) }}</pre>
                </div>
              </div>

              <div v-else class="py-12 text-center text-gray-500">
                No results yet — run a search or generate a report.
              </div>
            </CardContent>
          </Card>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

