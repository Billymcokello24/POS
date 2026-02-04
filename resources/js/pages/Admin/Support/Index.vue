<script setup lang="ts">
import { ref, watch, nextTick, onUnmounted } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import {
    MessageSquare,
    X,
    Send,
    Clock,
    Building2,
    User as UserIcon,
    Check,
    AlertCircle,
    Loader2,
    ChevronRight
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import axios from 'axios'

interface User {
    id: number
    name: string
    email?: string
}

interface Business {
    id: number
    name: string
    email?: string
}

interface Message {
    id: number
    message: string
    is_from_admin: boolean
    created_at: string
    user?: User
}

interface Ticket {
    id: number
    subject: string
    status: string
    created_at: string
    updated_at: string
    messages_count?: number
    business?: Business
    user?: User
    messages?: Message[]
}

interface PaginatedTickets {
    data: Ticket[]
    links: any[]
    meta?: any
}

const props = defineProps<{
    tickets: PaginatedTickets
}>()

const page = usePage()
const currentAdmin = (page.props as any).auth?.user

// Chat Panel State
const isPanelOpen = ref(false)
const selectedTicket = ref<Ticket | null>(null)
const messages = ref<Message[]>([])
const newMessage = ref('')
const isLoading = ref(false)
const isSending = ref(false)
const scrollContainer = ref<HTMLElement | null>(null)

// Typing indicator state
const otherUserTyping = ref(false)
const otherUserName = ref('')
let typingTimeout: any = null
let typingDebounce: any = null

// Echo channel reference
let echoChannel: any = null

const scrollToBottom = async () => {
    await nextTick()
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight
    }
}

// Subscribe to Echo channel for real-time updates
const subscribeToChannel = () => {
    if (!selectedTicket.value || !window.Echo) return

    leaveChannel() // Clean up any existing subscription

    echoChannel = window.Echo.private(`support.ticket.${selectedTicket.value.id}`)
        .listen('.message.sent', (data: any) => {
            // Check if message already exists to avoid duplicates
            const exists = messages.value.some((m: any) => m.id === data.id)
            if (!exists) {
                messages.value.push(data)
                scrollToBottom()
            }
            // Clear typing indicator when message received
            otherUserTyping.value = false
        })
        .listen('.user.typing', (data: any) => {
            // Show typing indicator from user (non-admin)
            if (!data.is_admin) {
                otherUserTyping.value = data.is_typing
                otherUserName.value = data.user_name

                // Auto-clear typing after 3 seconds
                if (typingTimeout) clearTimeout(typingTimeout)
                if (data.is_typing) {
                    typingTimeout = setTimeout(() => {
                        otherUserTyping.value = false
                    }, 3000)
                }
            }
        })
}

const leaveChannel = () => {
    if (echoChannel && selectedTicket.value && window.Echo) {
        window.Echo.leave(`support.ticket.${selectedTicket.value.id}`)
        echoChannel = null
    }
}

onUnmounted(() => {
    leaveChannel()
})

const openTicket = async (ticket: Ticket) => {
    selectedTicket.value = ticket
    isPanelOpen.value = true
    isLoading.value = true

    try {
        const response = await axios.get(`/admin/support/${ticket.id}`)
        messages.value = response.data.ticket.messages || []
        scrollToBottom()
        subscribeToChannel()
    } catch (error) {
        console.error('Failed to load ticket messages', error)
    } finally {
        isLoading.value = false
    }
}

const closePanel = () => {
    leaveChannel()
    isPanelOpen.value = false
    selectedTicket.value = null
    messages.value = []
    newMessage.value = ''
    otherUserTyping.value = false
}

const sendMessage = async () => {
    if (!newMessage.value.trim() || !selectedTicket.value) return

    const msg = newMessage.value
    newMessage.value = ''
    isSending.value = true

    // Stop typing indicator
    sendTypingIndicator(false)

    try {
        const response = await axios.post(`/api/support/tickets/${selectedTicket.value.id}/messages`, {
            message: msg
        })
        // Don't add to messages here - wait for broadcast from Pusher
        // The message will come through the Echo channel listener with .toOthers() not applying to own messages
        // Actually, we need to add it since .toOthers() excludes the sender
        // But check if it's not already there (race condition prevention)
        const messageExists = messages.value.some((m: any) => m.id === response.data.message.id)
        if (!messageExists) {
            messages.value.push(response.data.message)
        }
        scrollToBottom()
    } catch (error) {
        console.error('Failed to send message', error)
        newMessage.value = msg // Restore message on failure
    } finally {
        isSending.value = false
    }
}

// Send typing indicator (debounced)
const sendTypingIndicator = async (isTyping: boolean) => {
    if (!selectedTicket.value) return
    try {
        await axios.post(`/api/support/tickets/${selectedTicket.value.id}/typing`, {
            is_typing: isTyping
        })
    } catch (error) {
        // Ignore typing indicator errors
    }
}

// Handle input changes for typing indicator
const onInputChange = () => {
    if (typingDebounce) clearTimeout(typingDebounce)

    sendTypingIndicator(true)

    // Send stop typing after 2 seconds of no input
    typingDebounce = setTimeout(() => {
        sendTypingIndicator(false)
    }, 2000)
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getStatusColor = (status: string) => {
    switch (status?.toLowerCase()) {
        case 'open':
            return 'bg-emerald-50 text-emerald-600 ring-emerald-100'
        case 'closed':
            return 'bg-slate-100 text-slate-500 ring-slate-200'
        case 'pending':
            return 'bg-amber-50 text-amber-600 ring-amber-100'
        default:
            return 'bg-blue-50 text-blue-600 ring-blue-100'
    }
}
</script>

<template>
    <Head title="Support Tickets" />

    <AdminLayout>
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="p-1.5 bg-indigo-100 rounded-lg">
                            <MessageSquare class="size-4 text-indigo-600" />
                        </div>
                        <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">Support Center</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Support Tickets</h1>
                    <p class="text-slate-500 text-sm font-medium">Manage and respond to business support requests.</p>
                </div>
                <Badge class="bg-slate-900 text-white font-black uppercase text-[9px] tracking-widest px-4 py-2 rounded-xl">
                    {{ tickets.data.length }} Active Tickets
                </Badge>
            </div>

            <!-- Tickets Table -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden ring-1 ring-black/5">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Ticket</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Business</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Messages</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Last Update</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="ticket in tickets.data"
                                :key="ticket.id"
                                class="group hover:bg-slate-50/50 transition-colors cursor-pointer"
                                @click="openTicket(ticket)"
                            >
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-black text-sm shadow-inner">
                                            #{{ ticket.id }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-black text-slate-900 tracking-tight">{{ ticket.subject }}</div>
                                            <div class="text-xs font-medium text-slate-400 flex items-center gap-1 mt-0.5">
                                                <UserIcon class="h-3 w-3" />
                                                {{ ticket.user?.name || 'Unknown User' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="h-4 w-4 text-slate-400" />
                                        <span class="text-sm font-bold text-slate-700">{{ ticket.business?.name || 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span :class="['inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ring-1', getStatusColor(ticket.status || 'open')]">
                                        {{ ticket.status || 'Open' }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-1.5 text-sm font-bold text-slate-600">
                                        <MessageSquare class="h-4 w-4" />
                                        {{ ticket.messages_count || 0 }}
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5 text-xs font-bold text-slate-500">
                                        <Clock class="h-3 w-3" />
                                        {{ formatDate(ticket.updated_at) }}
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <Button variant="ghost" size="icon" class="h-8 w-8 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                        <ChevronRight class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="tickets.data.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="h-16 w-16 rounded-full bg-slate-100 flex items-center justify-center">
                                            <MessageSquare class="h-8 w-8 text-slate-300" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-slate-900 uppercase tracking-widest">No tickets yet</p>
                                            <p class="text-xs text-slate-400 mt-1">Support requests will appear here.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Chat Slide-Over Panel -->
        <Teleport to="body">
            <Transition name="slide">
                <div v-if="isPanelOpen" class="fixed inset-0 z-50 flex justify-end">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="closePanel"></div>

                    <!-- Panel -->
                    <div class="relative w-full max-w-lg bg-white shadow-2xl flex flex-col animate-in slide-in-from-right duration-300">
                        <!-- Panel Header -->
                        <div class="bg-slate-900 px-6 py-5 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg">
                                    <MessageSquare class="text-white h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-sm uppercase tracking-wider leading-none">
                                        Ticket #{{ selectedTicket?.id }}
                                    </h3>
                                    <p class="text-slate-400 text-[10px] font-bold uppercase mt-1 truncate max-w-[200px]">
                                        {{ selectedTicket?.subject }}
                                    </p>
                                </div>
                            </div>
                            <button @click="closePanel" class="text-slate-400 hover:text-white transition-colors">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Business Info Bar -->
                        <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex items-center gap-3 shrink-0">
                            <Building2 class="h-4 w-4 text-slate-400" />
                            <span class="text-xs font-bold text-slate-600">{{ selectedTicket?.business?.name }}</span>
                            <span class="text-[10px] text-slate-400">•</span>
                            <UserIcon class="h-3 w-3 text-slate-400" />
                            <span class="text-xs font-medium text-slate-500">{{ selectedTicket?.user?.name }}</span>
                        </div>

                        <!-- Messages Area -->
                        <div ref="scrollContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50">
                            <div v-if="isLoading" class="flex items-center justify-center h-full">
                                <Loader2 class="h-8 w-8 text-indigo-600 animate-spin" />
                            </div>
                            <template v-else>
                                <div
                                    v-for="msg in messages"
                                    :key="msg.id"
                                    :class="['flex', msg.is_from_admin ? 'justify-start' : 'justify-end']"
                                >
                                    <div :class="['max-w-[80%] rounded-2xl px-4 py-3 shadow-sm', msg.is_from_admin ? 'bg-white text-slate-900 rounded-bl-none ring-1 ring-slate-100' : 'bg-indigo-600 text-white rounded-br-none']">
                                        <p class="text-[13px] font-medium leading-relaxed">{{ msg.message }}</p>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span :class="['text-[9px] font-black uppercase', msg.is_from_admin ? 'text-indigo-600' : 'text-indigo-200']">
                                                {{ msg.user?.name || 'User' }}
                                            </span>
                                            <span v-if="msg.is_from_admin" class="text-[8px] bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded-full font-bold uppercase">Admin</span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Typing Indicator -->
                            <div v-if="otherUserTyping" class="flex items-center gap-2 px-2 py-1">
                                <div class="flex space-x-1">
                                    <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                    <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                    <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                                </div>
                                <span class="text-xs text-slate-500 font-medium">{{ otherUserName || 'User' }} is typing...</span>
                            </div>
                        </div>

                        <!-- Input Area -->
                        <div class="p-4 bg-white border-t border-slate-100 shrink-0">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="h-6 w-6 rounded-lg bg-slate-900 flex items-center justify-center text-white text-[9px] font-black">
                                    {{ currentAdmin?.name?.split(' ').map((n: string) => n[0]).join('').substring(0, 2) || 'SA' }}
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Replying as {{ currentAdmin?.name || 'Admin' }}</span>
                            </div>
                            <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                                <div class="flex-1 relative">
                                    <input
                                        v-model="newMessage"
                                        @input="onInputChange"
                                        placeholder="Type your reply..."
                                        class="w-full h-12 bg-slate-100 border-none rounded-2xl px-4 pr-12 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition-all placeholder:text-slate-400"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="isSending || !newMessage.trim()"
                                        class="absolute right-2 top-1.5 h-9 w-9 bg-indigo-600 text-white rounded-xl flex items-center justify-center hover:scale-105 transition-transform active:scale-95 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <Loader2 v-if="isSending" class="h-4 w-4 animate-spin" />
                                        <Send v-else class="h-4 w-4" />
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
}

.slide-enter-from > div:last-child,
.slide-leave-to > div:last-child {
    transform: translateX(100%);
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 4px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
