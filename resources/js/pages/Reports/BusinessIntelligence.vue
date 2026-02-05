<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
} from 'chart.js'
import {
    Download,
    BarChart3,
    Sparkles,
    TrendingUp,
    DollarSign,
    ShoppingCart,
    PieChart,
    Lightbulb,
    Activity,
    Target,
    Zap,
    AlertCircle,
    CheckCircle,
    ArrowUpRight,
    ArrowDownRight,
    Calendar,
    FileText,
    Package,
    Eye,
    EyeOff,
    Users,
    Building2,
    TrendingDown,
    PercentIcon,
} from 'lucide-vue-next'
import { ref, computed, onMounted } from 'vue'
import { Line, Bar, Pie, Doughnut } from 'vue-chartjs'

import axios from '@/axios'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'


ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
)

interface TileStats {
    total_value: number
    total_items: number
    performance: {
        score: number
        label: string
        color: string
    }
    insights_count: number
    transactions: number
    net_profit: number
    net_margin: number
}

interface Insight {
    category: string
    type: 'positive' | 'negative' | 'warning'
    title: string
    description: string
}

interface Recommendation {
    priority: 'high' | 'medium' | 'low'
    title: string
    description: string
    expected_impact: string
}

interface Forecast {
    metric: string
    projection: number | null
    confidence: string
    description: string
}

interface Product {
    name: string
    units_sold: number
    revenue: number
    profit: number
    margin: number
}

interface HistoricalKPI {
    period: string
    period_short: string
    revenue: number
    cogs: number
    gross_profit: number
    transactions: number
}

interface ReportData {
    metrics: {
        business: { name: string; currency: string; type: string }
        period: { start_date: string; end_date: string; generated_at: string }
        current_kpis: Record<string, number>
        previous_kpis: Record<string, number>
        kpi_trends: Record<string, { change: number; direction: string }>
        historical_kpis: HistoricalKPI[]
        top_products: Product[]
        product_performance: Product[]
        profit_loss: Record<string, number>
    }
    analysis: {
        executive_summary: string
        insights: Insight[]
        recommendations: Recommendation[]
        forecasts: Forecast[]
        analysis_type: string
    }
    tile_stats: TileStats
}

const selectedPeriod = ref('month')
const reportData = ref<ReportData | null>(null)
const loading = ref(false)
const exporting = ref(false)
const showRawData = ref(false)

const periods = [
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'quarter', label: 'This Quarter' },
    { value: 'year', label: 'This Year' },
    { value: 'all', label: 'All Time' },
]

const formatCurrency = (amount: number, currency: string = 'KES') => {
    const symbols: Record<string, string> = { USD: '$', EUR: '€', GBP: '£', KES: 'KSh', TZS: 'TSh' }
    return `${symbols[currency] || currency + ' '}${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatNumber = (num: number) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toString()
}

const generateReport = async () => {
    loading.value = true
    try {
        const response = await axios.post('/api/bi/generate', { period: selectedPeriod.value })
        if (response.data.success) {
            reportData.value = response.data.data
        } else {
            alert('Failed to generate report: ' + response.data.message)
        }
    } catch (error: any) {
        console.error('Error:', error)
        alert('Failed to generate report: ' + (error.response?.data?.message || error.message))
    } finally {
        loading.value = false
    }
}

const exportPDF = async () => {
    exporting.value = true
    try {
        const response = await axios.post('/api/bi/export/pdf',
            { period: selectedPeriod.value },
            { responseType: 'blob' }
        )
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const a = document.createElement('a')
        a.href = url
        a.download = `Business_Intelligence_Report_${selectedPeriod.value}_${new Date().toISOString().split('T')[0]}.pdf`
        document.body.appendChild(a)
        a.click()
        window.URL.revokeObjectURL(url)
        a.remove()
    } catch (error: any) {
        console.error('Error:', error)
        alert('Failed to export PDF')
    } finally {
        exporting.value = false
    }
}

// Chart configurations
const revenueChartData = computed(() => {
    if (!reportData.value?.metrics.historical_kpis) return null

    const historical = reportData.value.metrics.historical_kpis
    const labels = historical.map(h => h.period_short)
    const actualData = historical.map(h => h.revenue)

    // Calculate forecast (simple 12% growth projection)
    const lastRevenue = actualData[actualData.length - 1] || 0
    const forecast = lastRevenue * 1.12

    return {
        labels: [...labels, 'Forecast'],
        datasets: [
            {
                label: 'Actual Revenue',
                data: [...actualData, null],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointBackgroundColor: '#3b82f6',
            },
            {
                label: 'Projected Revenue',
                data: [...Array(actualData.length).fill(null), lastRevenue, forecast],
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderDash: [10, 5],
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 6,
                pointBackgroundColor: '#ef4444',
            }
        ]
    }
})

const revenueChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 12, weight: 'bold' as const }
            }
        },
        title: {
            display: true,
            text: 'Revenue Trend & AI-Assisted Forecast',
            font: { size: 16, weight: 'bold' as const }
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 12 },
            callbacks: {
                label: function(context: any) {
                    return context.dataset.label + ': ' + formatCurrency(context.parsed.y, reportData.value?.metrics.business.currency || 'KES')
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value: any) {
                    return formatNumber(value)
                }
            }
        }
    }
}

const topProductsChartData = computed(() => {
    if (!reportData.value?.metrics.top_products) return null

    const top5 = reportData.value.metrics.top_products.slice(0, 5)
    return {
        labels: top5.map(p => p.name.length > 15 ? p.name.substring(0, 15) + '...' : p.name),
        datasets: [{
            label: 'Revenue',
            data: top5.map(p => p.revenue),
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(6, 182, 212, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
            ],
            borderColor: [
                'rgb(59, 130, 246)',
                'rgb(139, 92, 246)',
                'rgb(6, 182, 212)',
                'rgb(16, 185, 129)',
                'rgb(245, 158, 11)',
            ],
            borderWidth: 2
        }]
    }
})

const topProductsChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y' as const,
    plugins: {
        legend: { display: false },
        title: {
            display: true,
            text: 'Top 5 Products by Revenue',
            font: { size: 14, weight: 'bold' as const }
        },
        tooltip: {
            callbacks: {
                label: function(context: any) {
                    return formatCurrency(context.parsed.x, reportData.value?.metrics.business.currency || 'KES')
                }
            }
        }
    },
    scales: {
        x: {
            ticks: {
                callback: function(value: any) {
                    return formatNumber(value)
                }
            }
        }
    }
}

const profitDistributionChartData = computed(() => {
    if (!reportData.value?.metrics.profit_loss) return null

    const pnl = reportData.value.metrics.profit_loss
    return {
        labels: ['COGS', 'Expenses', 'Net Profit'],
        datasets: [{
            data: [
                pnl.cogs || 0,
                pnl.operating_expenses || 0,
                Math.max(pnl.net_profit || 0, 0)
            ],
            backgroundColor: [
                'rgba(239, 68, 68, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(16, 185, 129, 0.8)',
            ],
            borderColor: [
                'rgb(239, 68, 68)',
                'rgb(245, 158, 11)',
                'rgb(16, 185, 129)',
            ],
            borderWidth: 2
        }]
    }
})

const profitDistributionChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
            labels: {
                font: { size: 12 },
                padding: 15
            }
        },
        title: {
            display: true,
            text: 'Cost & Profit Distribution',
            font: { size: 14, weight: 'bold' as const }
        },
        tooltip: {
            callbacks: {
                label: function(context: any) {
                    const label = context.label || ''
                    const value = context.parsed || 0
                    const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0)
                    const percentage = ((value / total) * 100).toFixed(1)
                    return `${label}: ${formatCurrency(value, reportData.value?.metrics.business.currency || 'KES')} (${percentage}%)`
                }
            }
        }
    }
}

const getInsightClass = (type: string) => {
    switch (type) {
        case 'positive': return 'bg-green-50 border-l-4 border-green-500'
        case 'negative': return 'bg-red-50 border-l-4 border-red-500'
        case 'warning': return 'bg-yellow-50 border-l-4 border-yellow-500'
        default: return 'bg-gray-50 border-l-4 border-gray-500'
    }
}

const getInsightIcon = (type: string) => {
    switch (type) {
        case 'positive': return CheckCircle
        case 'negative': return AlertCircle
        case 'warning': return AlertCircle
        default: return Lightbulb
    }
}

const getPriorityClass = (priority: string) => {
    switch (priority) {
        case 'high': return 'bg-red-600 text-white'
        case 'medium': return 'bg-yellow-500 text-white'
        default: return 'bg-gray-500 text-white'
    }
}

const performanceGradient = computed(() => {
    if (!reportData.value) return 'from-gray-500 to-gray-600'
    const color = reportData.value.tile_stats.performance.color
    switch (color) {
        case 'green': return 'from-green-500 to-green-600'
        case 'yellow': return 'from-yellow-500 to-yellow-600'
        case 'red': return 'from-red-500 to-red-600'
        default: return 'from-gray-500 to-gray-600'
    }
})

// Auto-generate report on mount
onMounted(() => {
    generateReport()
})
</script>

<template>
    <Head title="Business Intelligence" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-4 sm:py-8">
            <div class="max-w-[95%] mx-auto px-3 sm:px-4 lg:px-8">

                <!-- Premium Header - Mobile Optimized -->
                <div class="relative overflow-hidden bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl sm:rounded-3xl shadow-2xl p-4 sm:p-8 mb-4 sm:mb-8 text-white">
                    <div class="absolute top-0 right-0 w-48 h-48 sm:w-96 sm:h-96 bg-white/10 rounded-full -mr-24 sm:-mr-48 -mt-24 sm:-mt-48"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/5 rounded-full -ml-16 sm:-ml-32 -mb-16 sm:-mb-32"></div>

                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6">
                        <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl sm:rounded-2xl p-3 sm:p-4 flex-shrink-0">
                                <BarChart3 class="h-6 w-6 sm:h-10 sm:w-10 text-white" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight truncate">Business Intelligence</h1>
                                <p class="text-white/90 text-xs sm:text-base lg:text-lg mt-1 flex items-center gap-1 sm:gap-2 truncate">
                                    <Sparkles class="h-4 w-4 sm:h-5 sm:w-5 flex-shrink-0" />
                                    <span class="hidden sm:inline">Data-Driven • AI-Assisted • Executive-Grade</span>
                                    <span class="sm:hidden truncate">AI-Powered Analysis</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                            <select
                                v-model="selectedPeriod"
                                @change="generateReport"
                                class="bg-white/20 backdrop-blur-sm text-white border-white/30 rounded-lg sm:rounded-xl px-3 sm:px-6 py-2 sm:py-3 text-sm sm:text-base lg:text-lg font-semibold focus:ring-2 focus:ring-white/50 cursor-pointer flex-1 sm:flex-initial"
                            >
                                <option v-for="p in periods" :key="p.value" :value="p.value" class="text-gray-900 font-semibold">
                                    {{ p.label }}
                                </option>
                            </select>
                            <Button
                                @click="generateReport"
                                :disabled="loading"
                                size="lg"
                                class="bg-white text-indigo-600 hover:bg-white/90 font-bold shadow-lg h-9 sm:h-11 px-3 sm:px-4 text-xs sm:text-sm flex-1 sm:flex-initial"
                            >
                                <Zap class="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" />
                                <span class="hidden xs:inline">{{ loading ? 'Generating...' : 'Refresh' }}</span>
                                <span class="xs:hidden">{{ loading ? 'Gen...' : 'Refresh' }}</span>
                            </Button>
                            <Button
                                v-if="reportData"
                                @click="exportPDF"
                                :disabled="exporting"
                                size="lg"
                                variant="outline"
                                class="bg-white/20 backdrop-blur-sm text-white border-white/30 hover:bg-white/30 font-bold h-9 sm:h-11 px-3 sm:px-4 text-xs sm:text-sm"
                            >
                                <Download class="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" />
                                <span class="hidden sm:inline">{{ exporting ? 'Exporting...' : 'Export PDF' }}</span>
                                <span class="sm:hidden">PDF</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center py-20">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-indigo-600 mx-auto mb-4"></div>
                        <p class="text-gray-600 text-lg font-semibold">Generating Business Intelligence Report...</p>
                        <p class="text-gray-500 text-sm mt-2">Analyzing data and computing insights</p>
                    </div>
                </div>

                <!-- Report Content -->
                <template v-if="reportData && !loading">
                    <!-- Executive Summary -->
                    <Card class="mb-8 border-0 shadow-xl bg-gradient-to-br from-amber-50 to-orange-50">
                        <CardHeader class="border-b-4 border-amber-500">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl p-3">
                                    <Sparkles class="h-8 w-8 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black text-gray-900">Executive Summary</CardTitle>
                                    <CardDescription class="text-base flex items-center gap-2 mt-1">
                                        <span class="px-3 py-1 bg-amber-200 text-amber-900 rounded-full text-xs font-bold">
                                            {{ reportData.analysis.analysis_type === 'data_driven' ? 'DATA-DRIVEN ANALYSIS' : 'AI-POWERED ANALYSIS' }}
                                        </span>
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <p class="text-gray-800 text-lg leading-relaxed font-medium">
                                {{ reportData.analysis.executive_summary }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- KPI Dashboard -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Revenue -->
                        <Card class="border-0 shadow-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white overflow-hidden">
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2 opacity-90">
                                            <DollarSign class="h-6 w-6" />
                                            <span class="text-sm font-bold uppercase tracking-wide">Total Revenue</span>
                                        </div>
                                        <p class="text-4xl font-black mb-2">
                                            {{ formatCurrency(reportData.metrics.current_kpis.revenue, reportData.metrics.business.currency) }}
                                        </p>
                                        <div class="flex items-center gap-2 text-sm">
                                            <component :is="reportData.metrics.kpi_trends.revenue?.direction === 'up' ? ArrowUpRight : ArrowDownRight" class="h-4 w-4" />
                                            <span class="font-semibold">
                                                {{ Math.abs(reportData.metrics.kpi_trends.revenue?.change || 0).toFixed(1) }}%
                                                {{ reportData.metrics.kpi_trends.revenue?.direction === 'up' ? 'growth' : 'decline' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-xl p-3">
                                        <TrendingUp class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Profit -->
                        <Card class="border-0 shadow-xl bg-gradient-to-br from-green-500 to-green-600 text-white overflow-hidden">
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2 opacity-90">
                                            <Target class="h-6 w-6" />
                                            <span class="text-sm font-bold uppercase tracking-wide">Net Profit</span>
                                        </div>
                                        <p class="text-4xl font-black mb-2">
                                            {{ formatCurrency(reportData.metrics.current_kpis.net_profit, reportData.metrics.business.currency) }}
                                        </p>
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="font-semibold">Margin: {{ reportData.metrics.current_kpis.net_margin?.toFixed(1) }}%</span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-xl p-3">
                                        <DollarSign class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Transactions -->
                        <Card class="border-0 shadow-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white overflow-hidden">
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2 opacity-90">
                                            <ShoppingCart class="h-6 w-6" />
                                            <span class="text-sm font-bold uppercase tracking-wide">Transactions</span>
                                        </div>
                                        <p class="text-4xl font-black mb-2">
                                            {{ reportData.metrics.current_kpis.transactions?.toLocaleString() }}
                                        </p>
                                        <div class="flex items-center gap-2 text-sm">
                                            <component :is="reportData.metrics.kpi_trends.transactions?.direction === 'up' ? ArrowUpRight : ArrowDownRight" class="h-4 w-4" />
                                            <span class="font-semibold">
                                                {{ Math.abs(reportData.metrics.kpi_trends.transactions?.change || 0).toFixed(1) }}%
                                                {{ reportData.metrics.kpi_trends.transactions?.direction === 'up' ? 'increase' : 'decrease' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-xl p-3">
                                        <Activity class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Performance Score -->
                        <Card :class="['border-0 shadow-xl text-white overflow-hidden bg-gradient-to-br', performanceGradient]">
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2 opacity-90">
                                            <Zap class="h-6 w-6" />
                                            <span class="text-sm font-bold uppercase tracking-wide">Performance</span>
                                        </div>
                                        <p class="text-4xl font-black mb-2">
                                            {{ reportData.tile_stats.performance.label }}
                                        </p>
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="font-semibold">Score: {{ reportData.tile_stats.performance.score }}%</span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-xl p-3">
                                        <Target class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Revenue Trend & Forecast Chart -->
                    <Card class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl p-3">
                                    <TrendingUp class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">Revenue Trend & AI-Assisted Forecast</CardTitle>
                                    <CardDescription class="text-base mt-1">
                                        <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold mr-2">BLUE = ACTUAL</span>
                                        <span class="inline-block bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-bold">RED = PROJECTED</span>
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-8">
                            <div class="h-96">
                                <Line v-if="revenueChartData" :data="revenueChartData" :options="revenueChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Top Products Bar Chart -->
                        <Card class="border-0 shadow-xl">
                            <CardHeader class="bg-gradient-to-r from-purple-50 to-pink-50 border-b">
                                <div class="flex items-center gap-3">
                                    <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-3">
                                        <BarChart3 class="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-xl font-black">Top Products by Revenue</CardTitle>
                                        <CardDescription>Best-selling products ranked by revenue generation</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div class="h-80">
                                    <Bar v-if="topProductsChartData" :data="topProductsChartData" :options="topProductsChartOptions" />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Profit Distribution Pie Chart -->
                        <Card class="border-0 shadow-xl">
                            <CardHeader class="bg-gradient-to-r from-green-50 to-emerald-50 border-b">
                                <div class="flex items-center gap-3">
                                    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-3">
                                        <PieChart class="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-xl font-black">Cost & Profit Distribution</CardTitle>
                                        <CardDescription>Financial breakdown and profit analysis</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div class="h-80">
                                    <Pie v-if="profitDistributionChartData" :data="profitDistributionChartData" :options="profitDistributionChartOptions" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- BI Pipeline Flowchart -->
                    <Card class="mb-8 border-0 shadow-xl bg-gradient-to-br from-slate-50 to-blue-50">
                        <CardHeader class="border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-slate-600 to-blue-600 rounded-xl p-3">
                                    <Activity class="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-xl font-black">Business Intelligence Pipeline</CardTitle>
                                    <CardDescription>Data processing and analysis workflow</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-8">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg">
                                    <Package class="h-8 w-8 mb-2" />
                                    <h3 class="font-bold text-lg">Database</h3>
                                    <p class="text-sm opacity-90">Source of Truth</p>
                                </div>
                                <div class="text-gray-400">
                                    <ArrowUpRight class="h-8 w-8" />
                                </div>
                                <div class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-6 shadow-lg">
                                    <BarChart3 class="h-8 w-8 mb-2" />
                                    <h3 class="font-bold text-lg">Metrics Engine</h3>
                                    <p class="text-sm opacity-90">Data Aggregation</p>
                                </div>
                                <div class="text-gray-400">
                                    <ArrowUpRight class="h-8 w-8" />
                                </div>
                                <div class="flex-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-lg">
                                    <Sparkles class="h-8 w-8 mb-2" />
                                    <h3 class="font-bold text-lg">BI Analysis</h3>
                                    <p class="text-sm opacity-90">Intelligence Layer</p>
                                </div>
                                <div class="text-gray-400">
                                    <ArrowUpRight class="h-8 w-8" />
                                </div>
                                <div class="flex-1 bg-gradient-to-r from-pink-500 to-pink-600 text-white rounded-xl p-6 shadow-lg">
                                    <FileText class="h-8 w-8 mb-2" />
                                    <h3 class="font-bold text-lg">PDF Report</h3>
                                    <p class="text-sm opacity-90">Export</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Business Insights -->
                    <Card v-if="reportData.analysis.insights?.length" class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-blue-50 to-purple-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl p-3">
                                    <Lightbulb class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">Business Intelligence Insights</CardTitle>
                                    <CardDescription class="text-base">Data-driven findings and observations</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    v-for="(insight, index) in reportData.analysis.insights"
                                    :key="index"
                                    :class="['p-5 rounded-xl shadow-sm', getInsightClass(insight.type)]"
                                >
                                    <div class="flex items-start gap-3">
                                        <component :is="getInsightIcon(insight.type)" class="h-6 w-6 mt-1" />
                                        <div class="flex-1">
                                            <h4 class="font-bold text-lg mb-1">{{ insight.title }}</h4>
                                            <p class="text-sm leading-relaxed">{{ insight.description }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- AI-Assisted Forecasts -->
                    <Card v-if="reportData.analysis.forecasts?.length" class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-blue-50 to-cyan-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-3">
                                    <TrendingUp class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">AI-Assisted Forecasts & Projections</CardTitle>
                                    <CardDescription class="text-base">Data-driven predictions based on historical trends</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                                <p class="text-sm font-semibold text-blue-900">
                                    📊 <strong>Visual Reference:</strong> See the Revenue Trend chart above for the visual forecast representation.
                                    The blue line shows actual performance, while the red dashed line indicates projected growth based on historical data.
                                </p>
                            </div>
                            <div class="space-y-4">
                                <div
                                    v-for="(forecast, index) in reportData.analysis.forecasts"
                                    :key="index"
                                    class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 shadow-sm"
                                >
                                    <div class="flex items-center gap-3 mb-3">
                                        <span class="text-xs font-black text-blue-700 uppercase tracking-wider bg-blue-100 px-3 py-1 rounded-full">
                                            {{ forecast.metric }}
                                        </span>
                                        <span :class="[
                                            'text-xs font-bold uppercase px-3 py-1 rounded-full',
                                            forecast.confidence === 'high' ? 'bg-green-100 text-green-700' :
                                            forecast.confidence === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                                            'bg-gray-100 text-gray-700'
                                        ]">
                                            {{ forecast.confidence }} CONFIDENCE
                                        </span>
                                    </div>
                                    <p class="text-gray-800 leading-relaxed font-medium">{{ forecast.description }}</p>
                                </div>
                            </div>
                            <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                                <p class="text-xs text-yellow-900 font-semibold">
                                    ⚠️ <strong>Disclaimer:</strong> Forecasts are statistical projections based on historical data patterns.
                                    Actual business results may vary due to market conditions, seasonal factors, and unforeseen circumstances.
                                    Use these projections as guidance for strategic planning, not as guarantees.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Strategic Recommendations -->
                    <Card v-if="reportData.analysis.recommendations?.length" class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-orange-50 to-amber-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl p-3">
                                    <Target class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">Strategic Recommendations</CardTitle>
                                    <CardDescription class="text-base">Actionable insights for business improvement</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="space-y-4">
                                <div
                                    v-for="(rec, index) in reportData.analysis.recommendations"
                                    :key="index"
                                    class="bg-white border-2 border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition-shadow"
                                >
                                    <div class="flex items-start gap-3 mb-3">
                                        <span :class="['text-xs font-black uppercase px-3 py-1.5 rounded-lg', getPriorityClass(rec.priority)]">
                                            {{ rec.priority }} PRIORITY
                                        </span>
                                        <h4 class="font-bold text-lg text-gray-900 flex-1">{{ rec.title }}</h4>
                                    </div>
                                    <p class="text-gray-700 mb-3 leading-relaxed">{{ rec.description }}</p>
                                    <div class="flex items-center gap-2 text-green-700 bg-green-50 px-4 py-2 rounded-lg">
                                        <CheckCircle class="h-4 w-4" />
                                        <span class="text-sm font-semibold">Expected Impact: {{ rec.expected_impact }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Top Products Detailed Table -->
                    <Card v-if="reportData.metrics.top_products?.length" class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-purple-50 to-pink-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-3">
                                    <Package class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">Top Performing Products</CardTitle>
                                    <CardDescription class="text-base">Best-selling products with detailed metrics</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider">Rank</th>
                                            <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider">Product Name</th>
                                            <th class="px-6 py-4 text-right text-xs font-black text-gray-700 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-6 py-4 text-right text-xs font-black text-gray-700 uppercase tracking-wider">Revenue</th>
                                            <th class="px-6 py-4 text-right text-xs font-black text-gray-700 uppercase tracking-wider">Profit</th>
                                            <th class="px-6 py-4 text-right text-xs font-black text-gray-700 uppercase tracking-wider">Margin</th>
                                            <th class="px-6 py-4 text-center text-xs font-black text-gray-700 uppercase tracking-wider">Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(product, index) in reportData.metrics.top_products.slice(0, 10)" :key="index" class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm">
                                                    {{ index + 1 }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 font-semibold text-gray-900">{{ product.name }}</td>
                                            <td class="px-6 py-4 text-right font-semibold">{{ product.units_sold.toLocaleString() }}</td>
                                            <td class="px-6 py-4 text-right font-bold text-blue-600">
                                                {{ formatCurrency(product.revenue, reportData.metrics.business.currency) }}
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-green-600">
                                                {{ formatCurrency(product.profit, reportData.metrics.business.currency) }}
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span :class="[
                                                    'px-3 py-1 rounded-full text-sm font-bold',
                                                    product.margin >= 30 ? 'bg-green-100 text-green-800' :
                                                    product.margin >= 15 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'
                                                ]">
                                                    {{ product.margin.toFixed(1) }}%
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-xl">{{ product.margin >= 30 ? '⭐⭐⭐' : product.margin >= 15 ? '⭐⭐' : '⭐' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Period Comparison -->
                    <Card class="mb-8 border-0 shadow-xl">
                        <CardHeader class="bg-gradient-to-r from-slate-50 to-gray-50 border-b">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-br from-slate-600 to-gray-700 rounded-xl p-3">
                                    <Calendar class="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-2xl font-black">Period-over-Period Analysis</CardTitle>
                                    <CardDescription class="text-base">Comparative metrics: Current vs Previous period</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                                    <h4 class="text-sm font-bold text-blue-700 uppercase mb-4">Current Period</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Revenue</span>
                                            <p class="text-2xl font-black text-gray-900">{{ formatCurrency(reportData.metrics.current_kpis.revenue, reportData.metrics.business.currency) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Net Profit</span>
                                            <p class="text-2xl font-black text-gray-900">{{ formatCurrency(reportData.metrics.current_kpis.net_profit, reportData.metrics.business.currency) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Margin</span>
                                            <p class="text-2xl font-black text-gray-900">{{ reportData.metrics.current_kpis.gross_margin?.toFixed(1) }}%</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-6 border-2 border-gray-300">
                                    <h4 class="text-sm font-bold text-gray-700 uppercase mb-4">Previous Period</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Revenue</span>
                                            <p class="text-2xl font-black text-gray-900">{{ formatCurrency(reportData.metrics.previous_kpis.revenue, reportData.metrics.business.currency) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Net Profit</span>
                                            <p class="text-2xl font-black text-gray-900">{{ formatCurrency(reportData.metrics.previous_kpis.net_profit, reportData.metrics.business.currency) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600 font-semibold uppercase">Margin</span>
                                            <p class="text-2xl font-black text-gray-900">{{ reportData.metrics.previous_kpis.gross_margin?.toFixed(1) }}%</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Raw Data Toggle -->
                    <div class="text-center mb-8">
                        <Button @click="showRawData = !showRawData" variant="outline" size="lg" class="font-bold">
                            <component :is="showRawData ? EyeOff : Eye" class="h-5 w-5 mr-2" />
                            {{ showRawData ? 'Hide Raw Data' : 'Show Raw Data' }}
                        </Button>
                    </div>
                    <Card v-if="showRawData" class="mb-8 border-0 shadow-xl bg-gray-900">
                        <CardContent class="p-6">
                            <pre class="text-green-400 text-xs overflow-x-auto">{{ JSON.stringify(reportData, null, 2) }}</pre>
                        </CardContent>
                    </Card>
                </template>

                <!-- Empty State -->
                <Card v-if="!reportData && !loading" class="border-0 shadow-2xl">
                    <CardContent class="p-16 text-center">
                        <div class="bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full w-32 h-32 flex items-center justify-center mx-auto mb-6">
                            <BarChart3 class="h-16 w-16 text-indigo-600" />
                        </div>
                        <h3 class="text-3xl font-black text-gray-900 mb-3">Business Intelligence Engine</h3>
                        <p class="text-gray-600 text-lg mb-2">Data-Driven • Predictive • Explainable</p>
                        <p class="text-gray-500 mb-8 max-w-md mx-auto">
                            Generate comprehensive business intelligence reports with AI-powered insights, forecasts, and strategic recommendations.
                        </p>
                        <Button @click="generateReport" size="lg" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold shadow-xl">
                            <Sparkles class="h-5 w-5 mr-2" />
                            Generate Intelligence Report
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.backdrop-blur-sm {
    backdrop-filter: blur(8px);
}
</style>
