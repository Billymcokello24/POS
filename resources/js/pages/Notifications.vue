<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Bell, Trash2, CheckCheck, Trash, Eye, EyeOff } from 'lucide-vue-next'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

interface Notification {
    id: string
    type: string
    title: string
    message: string
    data?: any
    created_at?: string
    read_at?: string | null
}

const page = usePage()
const notifications = ref<Notification[]>([])
const isLoading = ref(false)
const selectedFilter = ref('all')

const unreadCount = computed(() => {
    return notifications.value.filter(n => !n.read_at).length
})

const filteredNotifications = computed(() => {
    let filtered = [...notifications.value]

    // Filter out notifications older than 24 hours
    const twentyFourHoursAgo = new Date(Date.now() - 24 * 60 * 60 * 1000)
    filtered = filtered.filter(n => {
        if (!n.created_at) return false
        return new Date(n.created_at) > twentyFourHoursAgo
    })

    // Apply user-selected filters
    if (selectedFilter.value === 'unread') {
        filtered = filtered.filter(n => !n.read_at)
    } else if (selectedFilter.value === 'read') {
        filtered = filtered.filter(n => n.read_at)
    } else if (selectedFilter.value === 'product') {
        filtered = filtered.filter(n => n.type.includes('product'))
    } else if (selectedFilter.value === 'category') {
        filtered = filtered.filter(n => n.type.includes('category'))
    } else if (selectedFilter.value === 'support') {
        filtered = filtered.filter(n => n.type.includes('support'))
    } else if (selectedFilter.value === 'subscription') {
        filtered = filtered.filter(n => n.type.includes('subscription'))
    } else if (selectedFilter.value === 'impersonation') {
        filtered = filtered.filter(n => n.type.includes('impersonation'))
    }

    // Sort by newest first and limit to 10
    return filtered
        .sort((a, b) => {
            const dateA = new Date(a.created_at || 0).getTime()
            const dateB = new Date(b.created_at || 0).getTime()
            return dateB - dateA
        })
        .slice(0, 10)
})

const getIcon = (type: string) => {
    const icons: Record<string, string> = {
        'support.message': '💬',
        'support.ticket.created': '🎫',
        'subscription.created': '📋',
        'subscription.updated': '📈',
        'subscription.expiring': '⏰',
        'subscription.expired': '❌',
        'subscription.duplicate': '📊',
        'impersonation.started': '🔐',
        'impersonation.ended': '🔓',
        'product.created': '📦',
        'category.created': '🏷️',
        'payment.received': '💰',
        'payment.failed': '❌',
        'test.notification': '🧪',
        'default': 'ℹ️',
    }
    return icons[type] || icons['default']
}

const getColor = (type: string) => {
    if (type.includes('expired') || type.includes('failed')) return 'from-red-50 to-red-100'
    if (type.includes('expiring')) return 'from-yellow-50 to-yellow-100'
    if (type.includes('created') || type.includes('product') || type.includes('category')) return 'from-green-50 to-green-100'
    if (type.includes('impersonation')) return 'from-purple-50 to-purple-100'
    if (type.includes('subscription')) return 'from-blue-50 to-blue-100'
    return 'from-slate-50 to-slate-100'
}

const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        'support.message': 'Support Message',
        'support.ticket.created': 'Support Ticket',
        'subscription.created': 'New Subscription',
        'subscription.updated': 'Subscription Updated',
        'subscription.expiring': 'Subscription Expiring',
        'subscription.expired': 'Subscription Expired',
        'subscription.duplicate': 'Duplicate Subscription',
        'impersonation.started': 'Impersonation Started',
        'impersonation.ended': 'Impersonation Ended',
        'product.created': 'New Product',
        'category.created': 'New Category',
        'payment.received': 'Payment Received',
        'payment.failed': 'Payment Failed',
        'test.notification': 'Test Notification',
    }
    return labels[type] || 'Notification'
}

const formatTime = (dateStr: string | undefined) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    const now = new Date()
    const diff = now.getTime() - date.getTime()

    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(diff / 86400000)

    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    if (hours < 24) return `${hours}h ago`
    if (days < 7) return `${days}d ago`

    return date.toLocaleDateString()
}

const formatFullDate = (dateStr: string | undefined) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

onMounted(() => {
    loadNotifications()
    // Reload every 10 seconds for real-time feel
    setInterval(loadNotifications, 10000)
})

const loadNotifications = async () => {
    try {
        const response = await axios.get('/api/notifications')
        notifications.value = response.data.notifications || []
    } catch (error) {
        console.error('Failed to load notifications:', error)
    }
}

const markAsRead = async (notificationId: string) => {
    try {
        await axios.patch(`/api/notifications/${notificationId}/read`)
        const notification = notifications.value.find(n => n.id === notificationId)
        if (notification) {
            notification.read_at = new Date().toISOString()
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error)
    }
}

const markAllAsRead = async () => {
    try {
        await axios.post('/api/notifications/read-all')
        notifications.value.forEach(n => {
            n.read_at = new Date().toISOString()
        })
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error)
    }
}

const deleteNotification = async (notificationId: string) => {
    try {
        await axios.delete(`/api/notifications/${notificationId}`)
        notifications.value = notifications.value.filter(n => n.id !== notificationId)
    } catch (error) {
        console.error('Failed to delete notification:', error)
    }
}

const clearAllNotifications = async () => {
    if (!confirm('Are you sure you want to delete all notifications?')) return
    try {
        await axios.post('/api/notifications/clear-all')
        notifications.value = []
    } catch (error) {
        console.error('Failed to clear notifications:', error)
    }
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="w-[90%] mx-auto py-6 space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <Bell class="h-6 w-6 text-blue-600" />
                            </div>
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">Notification Center</span>
                        </div>
                        <h1 class="text-3xl font-black text-slate-900">Your Notifications</h1>
                        <p class="text-slate-500 text-sm font-medium mt-1">Stay updated with all activities</p>
                    </div>

                    <div class="flex items-center gap-2 sm:flex-col">
                        <div class="bg-white rounded-xl shadow-md p-4 flex-1 sm:flex-none">
                            <div class="text-center">
                                <div class="text-3xl font-black text-blue-600">{{ unreadCount }}</div>
                                <div class="text-xs text-slate-600 font-medium uppercase tracking-wide">Unread</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-md p-4 flex-1 sm:flex-none">
                            <div class="text-center">
                                <div class="text-3xl font-black text-slate-900">{{ filteredNotifications.length }}</div>
                                <div class="text-xs text-slate-600 font-medium uppercase tracking-wide">Last 24hrs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="filter in ['all', 'unread', 'read', 'product', 'category', 'support', 'subscription', 'impersonation']"
                                :key="filter"
                                @click="selectedFilter = filter"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium text-sm transition-all whitespace-nowrap',
                                    selectedFilter === filter
                                        ? 'bg-blue-600 text-white shadow-lg'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                ]"
                            >
                                {{ filter.charAt(0).toUpperCase() + filter.slice(1) }}
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="unreadCount > 0"
                                @click="markAllAsRead"
                                class="px-4 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg font-medium text-sm transition-colors flex items-center gap-2"
                            >
                                <Eye class="h-4 w-4" />
                                Mark All Read
                            </button>
                            <button
                                v-if="notifications.length > 0"
                                @click="clearAllNotifications"
                                class="px-4 py-2 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg font-medium text-sm transition-colors flex items-center gap-2"
                            >
                                <Trash class="h-4 w-4" />
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="space-y-4">
                    <div v-if="isLoading" class="text-center py-12">
                        <div class="inline-block">
                            <div class="animate-spin h-8 w-8 border-4 border-blue-200 border-t-blue-600 rounded-full"></div>
                        </div>
                        <p class="text-slate-400 mt-3">Loading notifications...</p>
                    </div>

                    <div v-else-if="filteredNotifications.length === 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                        <Bell class="h-16 w-16 mx-auto mb-4 text-slate-300" />
                        <p class="text-slate-500 font-medium text-lg">No notifications</p>
                        <p class="text-slate-400 text-sm mt-1">You're all caught up! 🎉</p>
                    </div>

                    <div
                        v-for="notification in filteredNotifications"
                        :key="notification.id"
                        :class="[
                            'bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md hover:border-slate-300',
                            !notification.read_at ? 'border-l-4 border-l-blue-500' : ''
                        ]"
                    >
                        <div class="flex items-start gap-4 p-6">
                            <!-- Icon -->
                            <div class="text-4xl flex-shrink-0 pt-1">
                                {{ getIcon(notification.type) }}
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-slate-900">{{ notification.title }}</h3>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700">
                                                {{ getTypeLabel(notification.type) }}
                                            </span>
                                            <span v-if="!notification.read_at" class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700">
                                                New
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-slate-700 font-medium mb-3 leading-relaxed">{{ notification.message }}</p>

                                <!-- Data Details -->
                                <div v-if="notification.data && Object.keys(notification.data).length > 0" class="mb-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                    <div class="text-xs text-slate-600 space-y-1">
                                        <div v-for="(value, key) in notification.data" :key="key" class="flex justify-between">
                                            <span class="font-semibold text-slate-700">{{ key }}:</span>
                                            <span class="text-slate-700 break-all">{{ value }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timestamps -->
                                <div class="text-xs text-slate-500 space-y-1">
                                    <div class="font-medium">{{ formatTime(notification.created_at) }} • {{ formatFullDate(notification.created_at) }}</div>
                                    <div v-if="notification.read_at" class="text-slate-400">
                                        Read at {{ formatFullDate(notification.read_at) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                <button
                                    v-if="!notification.read_at"
                                    @click="markAsRead(notification.id)"
                                    class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                    title="Mark as read"
                                >
                                    <Eye class="h-5 w-5" />
                                </button>
                                <button
                                    @click="deleteNotification(notification.id)"
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                    title="Delete notification"
                                >
                                    <Trash2 class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Footer -->
                <div v-if="filteredNotifications.length > 0" class="bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 rounded-2xl border border-blue-200 p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-white/50 rounded-lg p-4 text-center backdrop-blur">
                            <div class="text-2xl font-black text-blue-600">{{ filteredNotifications.length }}</div>
                            <div class="text-xs text-slate-600 font-medium uppercase mt-1">Last 24hrs (Max 10)</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-4 text-center backdrop-blur">
                            <div class="text-2xl font-black text-slate-900">{{ unreadCount }}</div>
                            <div class="text-xs text-slate-600 font-medium uppercase mt-1">Unread</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-4 text-center backdrop-blur">
                            <div class="text-2xl font-black text-emerald-600">{{ filteredNotifications.filter(n => n.read_at).length }}</div>
                            <div class="text-xs text-slate-600 font-medium uppercase mt-1">Read</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-4 text-center backdrop-blur">
                            <div class="text-2xl font-black text-purple-600">{{ filteredNotifications.filter(n => n.type.includes('subscription')).length }}</div>
                            <div class="text-xs text-slate-600 font-medium uppercase mt-1">Subscriptions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

