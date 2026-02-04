<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'
import Pusher from 'pusher-js'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface Notification {
    id: string
    type: string
    data: {
        type: string
        title: string
        message: string
        icon?: string
        [key: string]: any
    }
    read_at: string | null
    created_at: string
}

const page = usePage()
const unreadCount = ref(0)
const recentNotifications = ref<Notification[]>([])
const notificationSound = ref<HTMLAudioElement | null>(null)
const isInitialized = ref(false)

const user = computed(() => (page.props.auth as any)?.user)
const isAdmin = computed(() => user.value?.is_super_admin)
const isAuthenticated = computed(() => !!user.value && !!user.value.id)

// Initialize notification sound
onMounted(() => {
    initializeNotifications()
})

// Watch for authentication changes (e.g., after login)
watch(() => user.value, (newUser, oldUser) => {
    if (newUser && !oldUser && !isInitialized.value) {
        initializeNotifications()
    } else if (!newUser && oldUser) {
        // User logged out, cleanup
        isInitialized.value = false
        unreadCount.value = 0
        recentNotifications.value = []
    }
}, { deep: true })

function initializeNotifications() {
    // Only initialize if user is authenticated and not already initialized
    if (!isAuthenticated.value || isInitialized.value) return

    isInitialized.value = true
    notificationSound.value = new Audio('/notification.mp3')
    fetchUnreadCount()
    fetchRecentNotifications()
    setupPusher()
}

async function fetchUnreadCount() {
    if (!isAuthenticated.value) return

    try {
        const endpoint = isAdmin.value ? '/admin/api/notifications/unread-count' : '/api/notifications/unread-count'
        const response = await fetch(endpoint, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })

        if (!response.ok) {
            if (response.status === 401) {
                console.log('Not authenticated, skipping notification fetch')
                return
            }
            console.error('Failed to fetch unread count:', response.status)
            return
        }

        const data = await response.json()
        unreadCount.value = data.count || 0
    } catch (error) {
        // Silently fail - user might not be authenticated yet
        console.log('Could not fetch unread count:', error)
    }
}

async function fetchRecentNotifications() {
    if (!isAuthenticated.value) return

    try {
        const endpoint = isAdmin.value ? '/admin/api/notifications/recent' : '/api/notifications/recent'
        const response = await fetch(endpoint, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })

        if (!response.ok) {
            if (response.status === 401) {
                console.log('Not authenticated, skipping notification fetch')
                return
            }
            console.error('Failed to fetch recent notifications:', response.status)
            return
        }

        const data = await response.json()
        recentNotifications.value = data.notifications || []
        unreadCount.value = data.unreadCount || 0
    } catch (error) {
        // Silently fail - user might not be authenticated yet
        console.log('Could not fetch recent notifications:', error)
    }
}

function setupPusher() {
    if (!isAuthenticated.value) return

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

        if (!csrfToken) {
            console.error('No CSRF token found in page')
            return
        }

        const pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
            // Use default /broadcasting/auth endpoint
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            },
        })

        const channelName = isAdmin.value
            ? `private-admin.${user.value.id}`
            : `private-business.${user.value.id}`

        const channel = pusher.subscribe(channelName)

        // Listen for general notifications
        channel.bind('notification.received', (data: any) => {
            console.log('New notification received:', data)
            handleNewNotification(data)
        })

        // Also listen for Laravel's default notification event
        channel.bind('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (data: any) => {
            console.log('Laravel notification received:', data)
            handleNewNotification(data)
        })

        // Handle connection errors
        pusher.connection.bind('error', (err: any) => {
            if (err.error?.data?.code === 4004) {
                console.log('Pusher auth error - user may not be authenticated')
            } else {
                console.error('Pusher connection error:', err)
            }
        })

        pusher.connection.bind('connected', () => {
            console.log('Pusher connected successfully')
        })

        // Handle subscription errors
        channel.bind('pusher:subscription_error', (status: any) => {
            console.error('Pusher subscription error:', status)
        })

        onUnmounted(() => {
            pusher.unsubscribe(channelName)
            pusher.disconnect()
        })
    } catch (error) {
        console.error('Failed to setup Pusher:', error)
    }
}

function handleNewNotification(data: any) {
    // Play notification sound
    playNotificationSound()

    // Update unread count
    unreadCount.value++

    // Extract notification data
    const notificationData = data.data || data

    // Add to recent notifications
    recentNotifications.value.unshift({
        id: data.id || Math.random().toString(),
        type: data.type || notificationData.type || 'notification',
        data: {
            type: notificationData.type || 'notification',
            title: notificationData.title || 'New Notification',
            message: notificationData.message || '',
            icon: notificationData.icon || '📬',
            ...notificationData
        },
        read_at: null,
        created_at: new Date().toISOString(),
    })

    // Keep only last 10 notifications
    if (recentNotifications.value.length > 10) {
        recentNotifications.value = recentNotifications.value.slice(0, 10)
    }
}

function playNotificationSound() {
    if (notificationSound.value) {
        notificationSound.value.play().catch(() => {
            console.log('Could not play notification sound, using fallback beep')
            // Fallback: Create a simple beep using Web Audio API
            try {
                const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)()
                const oscillator = audioContext.createOscillator()
                const gainNode = audioContext.createGain()

                oscillator.connect(gainNode)
                gainNode.connect(audioContext.destination)

                oscillator.frequency.value = 800
                oscillator.type = 'sine'

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5)

                oscillator.start(audioContext.currentTime)
                oscillator.stop(audioContext.currentTime + 0.5)
            } catch (beepErr) {
                console.log('Could not play beep sound:', beepErr)
            }
        })
    }
}

function markAsRead(id: string) {
    const endpoint = isAdmin.value
        ? `/admin/notifications/${id}/mark-as-read`
        : `/notifications/${id}/mark-as-read`

    router.post(endpoint, {}, {
        preserveScroll: true,
        onSuccess: () => {
            fetchUnreadCount()
            fetchRecentNotifications()
        }
    })
}

function formatTimeAgo(date: string) {
    const now = new Date()
    const then = new Date(date)
    const seconds = Math.floor((now.getTime() - then.getTime()) / 1000)

    if (seconds < 60) return 'just now'
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
    return `${Math.floor(seconds / 86400)}d ago`
}
</script>

<template>
    <DropdownMenu v-if="isAuthenticated">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="h-5 w-5" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs bg-red-600 hover:bg-red-700"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </Badge>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <div class="px-4 py-3 border-b">
                <h3 class="font-semibold text-sm">Notifications</h3>
                <p class="text-xs text-slate-600">You have {{ unreadCount }} unread notifications</p>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <DropdownMenuItem
                    v-for="notification in recentNotifications"
                    :key="notification.id"
                    class="flex-col items-start p-4 cursor-pointer"
                    :class="{ 'bg-blue-50': !notification.read_at }"
                    @click="markAsRead(notification.id)"
                >
                    <div class="flex items-start gap-3 w-full">
                        <span class="text-lg">{{ notification.data.icon || '📬' }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm">{{ notification.data.title }}</p>
                            <p class="text-xs text-slate-600 mt-1 line-clamp-2">{{ notification.data.message }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ formatTimeAgo(notification.created_at) }}</p>
                        </div>
                    </div>
                </DropdownMenuItem>

                <div v-if="recentNotifications.length === 0" class="px-4 py-8 text-center text-sm text-slate-600">
                    No notifications yet
                </div>
            </div>

            <DropdownMenuSeparator />

            <DropdownMenuItem as-child>
                <Link
                    :href="isAdmin ? '/admin/notifications' : '/notifications'"
                    class="w-full text-center text-sm font-medium py-2"
                >
                    View All Notifications
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

