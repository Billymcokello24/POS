<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import {
  Search,
  BarChart3,
  Clock,
  Zap,
  Sparkles,
  Package,
  TrendingDown,
  ShoppingCart,
  Brain,
  MessageSquare,
  CheckCircle2,
  AlertCircle,
  FileText,
  Download,
  TrendingUp,
  DollarSign,
  ChevronRight
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import axios from '@/axios'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'

// axios is centrally configured (baseURL, withCredentials, CSRF token & 419 retry)
// no per-page axios configuration required

const query = ref('')
const range = ref('last_30_days')
const results = ref<any>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const showRawData = ref(false)
const pdfPeriod = ref('month') // today, week, month, year

// Format results into professional sections
const formattedReport = computed(() => {
  if (!results.value) return null

  const data = results.value.data || results.value
  const report: any = {
    title: '',
    summary: '',
    sections: []
  }

  // Determine report type and structure
  if (data.products || data.items) {
    report.title = 'Inventory Analysis Report'
    report.summary = data.narrative || data.summary || 'Product inventory analysis'

    const items = data.products || data.items || []
    if (items.length > 0) {
      report.sections.push({
        title: 'Product Inventory',
        type: 'table',
        data: items,
        columns: ['Name', 'SKU', 'Stock', 'Price']
      })
    }
  } else if (data.sales || data.revenue) {
    report.title = 'Sales Performance Report'
    report.summary = data.narrative || data.summary || 'Sales and revenue analysis'

    if (data.total_sales || data.revenue) {
      report.sections.push({
        title: 'Financial Summary',
        type: 'metrics',
        data: {
          'Total Revenue': data.total_sales || data.revenue,
          'Total Orders': data.total_orders || data.orders,
          'Average Order Value': data.average_order_value,
          'Profit Margin': data.profit_margin
        }
      })
    }
  } else if (data.slow_moving || data.slow_movers) {
    report.title = 'Slow Moving Inventory Report'
    report.summary = data.narrative || data.summary || 'Analysis of underperforming products'

    const slowItems = data.slow_moving || data.slow_movers || []
    if (slowItems.length > 0) {
      report.sections.push({
        title: 'Slow Moving Products',
        type: 'list',
        data: slowItems
      })
    }
  } else {
    report.title = 'AI Analysis Report'
    report.summary = data.narrative || data.summary || data.explanation || 'General business analysis'
  }

  // Add insights section if available
  if (data.insights || data.recommendations) {
    report.sections.push({
      title: 'Key Insights & Recommendations',
      type: 'insights',
      data: data.insights || data.recommendations
    })
  }

  // Add raw data section
  if (data.note || data.message) {
    report.sections.push({
      title: 'Additional Information',
      type: 'text',
      data: data.note || data.message
    })
  }

  return report
})

async function generatePDF() {
  if (loading.value) return

  try {
    loading.value = true
    error.value = null

    // Create form and submit to download PDF
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = '/api/ai/generate-pdf'
    form.style.display = 'none'

    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (csrfToken) {
      const csrfInput = document.createElement('input')
      csrfInput.type = 'hidden'
      csrfInput.name = '_token'
      csrfInput.value = csrfToken
      form.appendChild(csrfInput)
    }

    // Add period parameter
    const periodInput = document.createElement('input')
    periodInput.type = 'hidden'
    periodInput.name = 'period'
    periodInput.value = pdfPeriod.value
    form.appendChild(periodInput)

    document.body.appendChild(form)
    form.submit()
    document.body.removeChild(form)

    loading.value = false
  } catch (e) {
    console.error('PDF generation failed:', e)
    error.value = 'Failed to generate PDF. Please try again.'
    loading.value = false
  }
}

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
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
      <div class="mx-auto w-[90%] px-6 py-8 space-y-8">

        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-600 via-pink-600 to-blue-600 p-10 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-48 -mt-48 animate-pulse"></div>
          <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/5 rounded-full -ml-32 -mb-32"></div>

          <div class="relative z-10 flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="rounded-2xl bg-white/20 backdrop-blur p-4 shadow-xl">
                <Brain class="h-12 w-12" />
              </div>
              <div>
                <div class="flex items-center gap-2 mb-2">
                  <Sparkles class="h-5 w-5 animate-pulse" />
                  <span class="text-sm font-semibold uppercase tracking-wider text-purple-100">Powered by AI</span>
                </div>
                <h1 class="text-5xl font-black mb-2">AI Business Assistant</h1>
                <p class="text-xl text-purple-100">Intelligent insights for smarter business decisions</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Search Card -->
          <Card class="border-0 shadow-2xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white hover:scale-105 transition-transform cursor-pointer" @click="() => {}">
            <CardHeader>
              <div class="flex items-center justify-between mb-2">
                <div class="p-3 bg-white/20 rounded-xl backdrop-blur">
                  <Search class="h-8 w-8" />
                </div>
                <CheckCircle2 class="h-5 w-5 text-white/60" />
              </div>
              <CardTitle class="text-white text-2xl">Smart Search</CardTitle>
              <CardDescription class="text-blue-100">
                Find any product instantly using natural language
              </CardDescription>
            </CardHeader>
          </Card>

          <!-- Analytics Card -->
          <Card class="border-0 shadow-2xl bg-gradient-to-br from-purple-500 to-pink-600 text-white hover:scale-105 transition-transform cursor-pointer" @click="report">
            <CardHeader>
              <div class="flex items-center justify-between mb-2">
                <div class="p-3 bg-white/20 rounded-xl backdrop-blur">
                  <BarChart3 class="h-8 w-8" />
                </div>
                <Zap class="h-5 w-5 text-white/60" />
              </div>
              <CardTitle class="text-white text-2xl">AI Reports</CardTitle>
              <CardDescription class="text-purple-100">
                Auto-generated insights and analytics
              </CardDescription>
            </CardHeader>
          </Card>

          <!-- Slow Moving Card -->
          <Card class="border-0 shadow-2xl bg-gradient-to-br from-orange-500 to-red-600 text-white hover:scale-105 transition-transform cursor-pointer" @click="slowMoving">
            <CardHeader>
              <div class="flex items-center justify-between mb-2">
                <div class="p-3 bg-white/20 rounded-xl backdrop-blur">
                  <TrendingDown class="h-8 w-8" />
                </div>
                <Clock class="h-5 w-5 text-white/60" />
              </div>
              <CardTitle class="text-white text-2xl">Slow Movers</CardTitle>
              <CardDescription class="text-orange-100">
                Identify underperforming inventory
              </CardDescription>
            </CardHeader>
          </Card>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Controls Panel -->
          <Card class="lg:col-span-1 border-0 shadow-2xl bg-white/95 backdrop-blur">
            <CardHeader class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-t-xl">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg">
                  <MessageSquare class="h-5 w-5 text-white" />
                </div>
                <div>
                  <CardTitle class="text-xl">Ask the AI</CardTitle>
                  <CardDescription>Search, analyze, and get insights</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-6 pt-6">

              <!-- Search Input -->
              <div class="space-y-3">
                <label class="block text-sm font-bold text-slate-900 uppercase tracking-wide">
                  <Search class="inline h-4 w-4 mr-1" />
                  Search Query
                </label>
                <input
                  v-model="query"
                  type="text"
                  class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                  placeholder="Try: 'rice', 'ABC123', 'low stock items'"
                  @keyup.enter="search"
                />
              </div>

              <!-- Range Selector -->
              <div class="space-y-3">
                <label class="block text-sm font-bold text-slate-900 uppercase tracking-wide">
                  <Clock class="inline h-4 w-4 mr-1" />
                  Time Range
                </label>
                <select
                  v-model="range"
                  class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                >
                  <option value="last_7_days">Last 7 Days</option>
                  <option value="last_30_days">Last 30 Days</option>
                  <option value="last_90_days">Last 90 Days</option>
                </select>
              </div>

              <!-- Action Buttons -->
              <div class="space-y-3">
                <Button
                  @click="search"
                  :disabled="loading"
                  class="w-full h-12 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all"
                >
                  <Search class="mr-2 h-5 w-5" />
                  {{ loading ? 'Searching...' : 'Search Inventory' }}
                </Button>

                <Button
                  @click="availability"
                  :disabled="loading || !query"
                  variant="outline"
                  class="w-full h-12 border-2 border-purple-200 hover:bg-purple-50 font-semibold transition-all"
                >
                  <Package class="mr-2 h-5 w-5" />
                  Check Availability
                </Button>

                <Button
                  @click="report"
                  :disabled="loading"
                  variant="outline"
                  class="w-full h-12 border-2 border-pink-200 hover:bg-pink-50 font-semibold transition-all"
                >
                  <BarChart3 class="mr-2 h-5 w-5" />
                  Generate Report
                </Button>

                <Button
                  @click="slowMoving"
                  :disabled="loading"
                  variant="outline"
                  class="w-full h-12 border-2 border-orange-200 hover:bg-orange-50 font-semibold transition-all"
                >
                  <TrendingDown class="mr-2 h-5 w-5" />
                  Slow Moving Items
                </Button>
              </div>

              <!-- Error Display -->
              <div v-if="error" class="p-4 bg-red-50 border-2 border-red-200 rounded-xl flex items-start gap-3">
                <AlertCircle class="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-semibold text-red-900">Error</p>
                  <p class="text-sm text-red-700">{{ error }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Results Panel -->
          <Card class="lg:col-span-2 border-0 shadow-2xl bg-white/95 backdrop-blur">
            <CardHeader class="bg-gradient-to-r from-slate-50 to-blue-50 rounded-t-xl">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg">
                    <Sparkles class="h-5 w-5 text-white" />
                  </div>
                  <div>
                    <CardTitle class="text-xl">AI Response</CardTitle>
                    <CardDescription>Intelligent analysis and insights</CardDescription>
                  </div>
                </div>
                <div v-if="loading" class="flex items-center gap-2 text-purple-600">
                  <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce"></div>
                  <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                  <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
              </div>
            </CardHeader>
            <CardContent class="pt-6">

              <!-- Loading State -->
              <div v-if="loading" class="py-20 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-purple-100 to-pink-100 mb-6 animate-pulse">
                  <Brain class="h-12 w-12 text-purple-600" />
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-2">AI is thinking...</h3>
                <p class="text-slate-600">Analyzing your request and generating insights</p>
              </div>

              <!-- Results Display -->
              <div v-else-if="results" class="space-y-6">

                <!-- Report Header -->
                <div class="flex items-center justify-between p-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl text-white">
                  <div class="flex items-center gap-4">
                    <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                      <FileText class="h-8 w-8" />
                    </div>
                    <div>
                      <h2 class="text-2xl font-bold">Business Intelligence Report</h2>
                      <p class="text-purple-100 text-sm">Comprehensive analysis with real data</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <select
                      v-model="pdfPeriod"
                      class="px-4 py-2 bg-white/20 border-2 border-white/40 text-white rounded-lg hover:bg-white/30 transition-all"
                    >
                      <option value="today" class="text-slate-900">Today</option>
                      <option value="week" class="text-slate-900">This Week</option>
                      <option value="month" class="text-slate-900">This Month</option>
                      <option value="year" class="text-slate-900">This Year</option>
                    </select>
                    <Button
                      @click="generatePDF"
                      variant="outline"
                      class="bg-white/20 border-white/40 text-white hover:bg-white/30"
                      :disabled="loading"
                    >
                      <Download class="mr-2 h-4 w-4" />
                      Export PDF
                    </Button>
                  </div>
                </div>

                <!-- Executive Summary -->
                <div v-if="formattedReport?.summary" class="p-6 bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 rounded-2xl border-2 border-purple-200">
                  <div class="flex items-start gap-4">
                    <div class="p-3 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex-shrink-0">
                      <Brain class="h-6 w-6 text-white" />
                    </div>
                    <div class="flex-1">
                      <h3 class="text-xl font-bold text-slate-900 mb-3 flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-purple-600" />
                        Executive Summary
                      </h3>
                      <p class="text-slate-700 leading-relaxed text-lg">
                        {{ formattedReport.summary }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Report Sections -->
                <div v-for="(section, index) in formattedReport?.sections" :key="index" class="bg-white rounded-2xl border-2 border-slate-200 shadow-lg overflow-hidden">
                  <div class="bg-gradient-to-r from-slate-50 to-blue-50 px-6 py-4 border-b-2 border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                      <ChevronRight class="h-5 w-5 text-purple-600" />
                      {{ section.title }}
                    </h3>
                  </div>
                  <div class="p-6">

                    <!-- Metrics Display -->
                    <div v-if="section.type === 'metrics'" class="grid grid-cols-2 gap-4">
                      <div
                        v-for="(value, key) in section.data"
                        :key="key"
                        class="p-4 bg-gradient-to-br from-slate-50 to-blue-50 rounded-xl border-2 border-slate-200"
                      >
                        <div class="text-sm text-slate-600 font-semibold uppercase tracking-wide mb-1">{{ key }}</div>
                        <div class="text-2xl font-bold text-slate-900">
                          {{ typeof value === 'number' ? value.toLocaleString() : value || 'N/A' }}
                        </div>
                      </div>
                    </div>

                    <!-- Table Display -->
                    <div v-else-if="section.type === 'table'" class="overflow-x-auto">
                      <table class="w-full">
                        <thead>
                          <tr class="bg-gradient-to-r from-purple-50 to-pink-50">
                            <th
                              v-for="col in section.columns"
                              :key="col"
                              class="px-4 py-3 text-left text-sm font-bold text-slate-900 uppercase tracking-wide"
                            >
                              {{ col }}
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="(item, idx) in section.data.slice(0, 10)"
                            :key="idx"
                            class="border-b border-slate-200 hover:bg-slate-50"
                          >
                            <td class="px-4 py-3 text-slate-900">{{ item.name || item.product_name || item.title }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ item.sku || item.code || '-' }}</td>
                            <td class="px-4 py-3 text-slate-900 font-semibold">{{ item.quantity || item.stock || item.qty || '-' }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ item.price || item.unit_price || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                      <div v-if="section.data.length > 10" class="text-center py-3 text-sm text-slate-600">
                        Showing 10 of {{ section.data.length }} items
                      </div>
                    </div>

                    <!-- List Display -->
                    <div v-else-if="section.type === 'list'" class="space-y-3">
                      <div
                        v-for="(item, idx) in section.data"
                        :key="idx"
                        class="p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border-2 border-orange-200 flex items-center gap-4"
                      >
                        <div class="p-2 bg-orange-500 rounded-lg">
                          <AlertCircle class="h-5 w-5 text-white" />
                        </div>
                        <div class="flex-1">
                          <h4 class="font-bold text-slate-900">{{ item.name || item.product_name }}</h4>
                          <p class="text-sm text-slate-600">
                            {{ item.days_since_last_sale ? `${item.days_since_last_sale} days since last sale` : 'Low activity' }}
                          </p>
                        </div>
                        <div class="text-right">
                          <div class="text-sm text-slate-600">Stock</div>
                          <div class="text-xl font-bold text-slate-900">{{ item.quantity || item.stock || 0 }}</div>
                        </div>
                      </div>
                    </div>

                    <!-- Insights Display -->
                    <div v-else-if="section.type === 'insights'" class="space-y-4">
                      <div
                        v-for="(insight, idx) in (Array.isArray(section.data) ? section.data : [section.data])"
                        :key="idx"
                        class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border-2 border-green-200 flex items-start gap-3"
                      >
                        <CheckCircle2 class="h-5 w-5 text-green-600 mt-0.5 flex-shrink-0" />
                        <p class="text-slate-700">{{ typeof insight === 'string' ? insight : insight.text || insight.message }}</p>
                      </div>
                    </div>

                    <!-- Text Display -->
                    <div v-else-if="section.type === 'text'" class="prose max-w-none">
                      <p class="text-slate-700 leading-relaxed">{{ section.data }}</p>
                    </div>

                  </div>
                </div>

                <!-- Analytics Summary -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div class="p-6 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl text-white shadow-xl">
                    <DollarSign class="h-8 w-8 mb-3 opacity-80" />
                    <div class="text-sm font-semibold opacity-80 mb-1">TOTAL VALUE</div>
                    <div class="text-3xl font-bold">
                      {{ results.data?.total_value || results.data?.total_sales || 'N/A' }}
                    </div>
                  </div>

                  <div class="p-6 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl text-white shadow-xl">
                    <Package class="h-8 w-8 mb-3 opacity-80" />
                    <div class="text-sm font-semibold opacity-80 mb-1">TOTAL ITEMS</div>
                    <div class="text-3xl font-bold">
                      {{ results.data?.total_items || results.data?.count || 'N/A' }}
                    </div>
                  </div>

                  <div class="p-6 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl text-white shadow-xl">
                    <TrendingUp class="h-8 w-8 mb-3 opacity-80" />
                    <div class="text-sm font-semibold opacity-80 mb-1">PERFORMANCE</div>
                    <div class="text-3xl font-bold">
                      {{ results.data?.performance || results.data?.status || 'Good' }}
                    </div>
                  </div>

                  <div class="p-6 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl text-white shadow-xl">
                    <BarChart3 class="h-8 w-8 mb-3 opacity-80" />
                    <div class="text-sm font-semibold opacity-80 mb-1">INSIGHTS</div>
                    <div class="text-3xl font-bold">
                      {{ formattedReport?.sections?.length || 0 }}
                    </div>
                  </div>
                </div>

                <!-- Toggle Raw Data -->
                <div class="text-center">
                  <Button
                    variant="ghost"
                    @click="showRawData = !showRawData"
                    class="text-slate-600 hover:text-slate-900"
                  >
                    {{ showRawData ? 'Hide' : 'Show' }} Raw Data
                  </Button>
                </div>

                <!-- Raw Data (Collapsed by Default) -->
                <div v-if="showRawData" class="space-y-3">
                  <div class="flex items-center justify-between">
                    <h4 class="font-bold text-slate-900 uppercase tracking-wide text-sm flex items-center gap-2">
                      <ShoppingCart class="h-4 w-4" />
                      Raw Response Data
                    </h4>
                    <Button variant="ghost" size="sm" class="text-xs" @click="() => navigator.clipboard.writeText(JSON.stringify(results, null, 2))">
                      Copy JSON
                    </Button>
                  </div>
                  <div class="bg-slate-900 rounded-2xl p-6 overflow-auto max-h-[400px] shadow-inner">
                    <pre class="text-green-400 text-sm font-mono leading-relaxed">{{ JSON.stringify(results, null, 2) }}</pre>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="py-20 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 mb-6">
                  <MessageSquare class="h-12 w-12 text-slate-400" />
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-2">Ready to assist</h3>
                <p class="text-slate-600 max-w-md mx-auto">
                  Ask me anything about your inventory, sales, or business analytics. I'm here to help!
                </p>
              </div>

            </CardContent>
          </Card>

        </div>

        <!-- Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="p-6 bg-white/10 backdrop-blur rounded-2xl border border-white/20 text-white hover:bg-white/20 transition-all">
            <Sparkles class="h-8 w-8 mb-3 text-yellow-300" />
            <h4 class="font-bold mb-2">Smart Insights</h4>
            <p class="text-sm text-white/80">AI-powered business intelligence</p>
          </div>

          <div class="p-6 bg-white/10 backdrop-blur rounded-2xl border border-white/20 text-white hover:bg-white/20 transition-all">
            <Zap class="h-8 w-8 mb-3 text-blue-300" />
            <h4 class="font-bold mb-2">Instant Analysis</h4>
            <p class="text-sm text-white/80">Get answers in seconds</p>
          </div>

          <div class="p-6 bg-white/10 backdrop-blur rounded-2xl border border-white/20 text-white hover:bg-white/20 transition-all">
            <Package class="h-8 w-8 mb-3 text-green-300" />
            <h4 class="font-bold mb-2">Inventory Mastery</h4>
            <p class="text-sm text-white/80">Optimize stock levels automatically</p>
          </div>

          <div class="p-6 bg-white/10 backdrop-blur rounded-2xl border border-white/20 text-white hover:bg-white/20 transition-all">
            <TrendingDown class="h-8 w-8 mb-3 text-red-300" />
            <h4 class="font-bold mb-2">Trend Detection</h4>
            <p class="text-sm text-white/80">Identify patterns and opportunities</p>
          </div>
        </div>

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

@keyframes bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-pulse {
  animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animate-bounce {
  animation: bounce 1s infinite;
}

/* Print styles for PDF generation */
@media print {
  @page {
    size: A4;
    margin: 2cm;
  }

  /* Hide UI elements */
  .no-print,
  button,
  nav,
  aside,
  .sidebar {
    display: none !important;
  }

  /* Reset backgrounds for print */
  body {
    background: white !important;
  }

  /* Ensure proper page breaks */
  .page-break {
    page-break-before: always;
  }

  /* Prevent breaking inside elements */
  .no-break {
    page-break-inside: avoid;
  }

  /* Optimize colors for print */
  .bg-gradient-to-br,
  .bg-gradient-to-r {
    background: white !important;
    border: 2px solid #e5e7eb !important;
  }

  /* Make text readable */
  .text-white {
    color: #1f2937 !important;
  }

  /* Print-specific headers */
  .print-header {
    display: block !important;
    text-align: center;
    margin-bottom: 2rem;
    border-bottom: 2px solid #000;
    padding-bottom: 1rem;
  }
}
</style>
