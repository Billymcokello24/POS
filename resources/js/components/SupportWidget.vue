<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, X, Send, Loader2, LifeBuoy, ShieldCheck, User as UserIcon, Bot } from 'lucide-vue-next';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

// State Machine types: 'initial' | 'verification' | 'chat'
const step = ref('initial');
const isOpen = ref(false);
const isLoading = ref(false);
const messages = ref<any[]>([]);
const currentTicketId = ref<number | null>(null);
const newMessage = ref('');
const scrollContainer = ref<HTMLElement | null>(null);

// Typing indicator state
const otherUserTyping = ref(false);
const otherUserName = ref('');
let typingTimeout: any = null;
let typingDebounce: any = null;

// Form States
const initForm = ref({
    subject: '',
    message: '',
});
const verificationCode = ref('');

// Echo channel reference
let echoChannel: any = null;

// Load session from localStorage
onMounted(() => {
    const saved = localStorage.getItem('support_session');
    if (saved) {
        const session = JSON.parse(saved);
        currentTicketId.value = session.ticketId;
        step.value = session.step;
        if (step.value === 'chat' && currentTicketId.value) {
            fetchMessages();
            subscribeToChannel();
        }
    }
});

onUnmounted(() => {
    leaveChannel();
});

// Persist session
watch([step, currentTicketId], () => {
    if (currentTicketId.value) {
        localStorage.setItem('support_session', JSON.stringify({
            ticketId: currentTicketId.value,
            step: step.value
        }));
    } else {
        localStorage.removeItem('support_session');
    }
});

const scrollToBottom = async () => {
    await nextTick();
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
};

// Subscribe to Echo channel for real-time updates
const subscribeToChannel = () => {
    if (!currentTicketId.value || !window.Echo) return;

    leaveChannel(); // Clean up any existing subscription

    echoChannel = window.Echo.private(`support.ticket.${currentTicketId.value}`)
        .listen('.message.sent', (data: any) => {
            // Check if message already exists to avoid duplicates
            const exists = messages.value.some((m: any) => m.id === data.id);
            if (!exists) {
                messages.value.push(data);
                scrollToBottom();
            }
            // Clear typing indicator when message received
            otherUserTyping.value = false;
        })
        .listen('.user.typing', (data: any) => {
            // Show typing indicator from admin
            if (data.is_admin) {
                otherUserTyping.value = data.is_typing;
                otherUserName.value = data.user_name;

                // Auto-clear typing after 3 seconds
                if (typingTimeout) clearTimeout(typingTimeout);
                if (data.is_typing) {
                    typingTimeout = setTimeout(() => {
                        otherUserTyping.value = false;
                    }, 3000);
                }
            }
        });
};

const leaveChannel = () => {
    if (echoChannel && currentTicketId.value && window.Echo) {
        window.Echo.leave(`support.ticket.${currentTicketId.value}`);
        echoChannel = null;
    }
};

// Watch for entering chat mode
watch([isOpen, step], ([open, currentStep]) => {
    if (open && currentStep === 'chat' && currentTicketId.value) {
        subscribeToChannel();
    } else if (!open) {
        leaveChannel();
    }
});

const initiateChat = async () => {
    isLoading.value = true;
    try {
        const response = await axios.post('/api/support/tickets', initForm.value);
        currentTicketId.value = response.data.ticket_id;
        step.value = 'verification';
    } catch (error) {
        console.error('Failed to initiate chat', error);
    } finally {
        isLoading.value = false;
    }
};

const verifyCode = async () => {
    isLoading.value = true;
    try {
        const response = await axios.post(`/api/support/tickets/${currentTicketId.value}/verify`, {
            code: verificationCode.value
        });
        messages.value = response.data.messages;
        step.value = 'chat';
        scrollToBottom();
        subscribeToChannel();
    } catch (error) {
        alert('Invalid code. Please try again.');
    } finally {
        isLoading.value = false;
    }
};

const fetchMessages = async () => {
    if (!currentTicketId.value) return;
    try {
        const response = await axios.get(`/api/support/tickets/${currentTicketId.value}/messages`);
        messages.value = response.data.messages;
        scrollToBottom();
    } catch (error) {
        console.error('Failed to fetch messages', error);
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || !currentTicketId.value) return;

    const msg = newMessage.value;
    newMessage.value = '';

    // Stop typing indicator
    sendTypingIndicator(false);

    // Create optimistic message (instant UI update)
    const optimisticMessage = {
        id: `optimistic-${Date.now()}`,
        support_ticket_id: currentTicketId.value,
        user_id: (page.props.auth as any).user.id,
        message: msg,
        is_from_admin: (page.props.auth as any).user.is_super_admin,
        created_at: new Date().toISOString(),
        user: {
            id: (page.props.auth as any).user.id,
            name: (page.props.auth as any).user.name
        },
        _optimistic: true // Mark as optimistic for styling
    };

    // Add to UI immediately (optimistic update)
    messages.value.push(optimisticMessage);
    scrollToBottom();

    try {
        const response = await axios.post(`/api/support/tickets/${currentTicketId.value}/messages`, {
            message: msg
        });

        // Replace optimistic message with real one
        const optimisticIndex = messages.value.findIndex(m => m.id === optimisticMessage.id);
        if (optimisticIndex !== -1) {
            messages.value[optimisticIndex] = response.data.message;
        }
        scrollToBottom();
    } catch (error) {
        console.error('Failed to send message', error);
        // Remove optimistic message on failure
        messages.value = messages.value.filter(m => m.id !== optimisticMessage.id);
        newMessage.value = msg; // Restore message for retry
    }
};

// Send typing indicator (debounced)
const sendTypingIndicator = async (isTyping: boolean) => {
    if (!currentTicketId.value) return;
    try {
        await axios.post(`/api/support/tickets/${currentTicketId.value}/typing`, {
            is_typing: isTyping
        });
    } catch (error) {
        // Ignore typing indicator errors
    }
};

// Handle input changes for typing indicator
const onInputChange = () => {
    if (typingDebounce) clearTimeout(typingDebounce);

    sendTypingIndicator(true);

    // Send stop typing after 2 seconds of no input
    typingDebounce = setTimeout(() => {
        sendTypingIndicator(false);
    }, 2000);
};

const resetSession = () => {
    leaveChannel();
    currentTicketId.value = null;
    step.value = 'initial';
    messages.value = [];
    initForm.value = { subject: '', message: '' };
    verificationCode.value = '';
    otherUserTyping.value = false;
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <Dialog v-model:open="isOpen">
            <DialogTrigger asChild>
                <button
                    class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-2xl transition-all hover:scale-110 hover:bg-indigo-700 active:scale-95"
                >
                    <div class="absolute inset-0 rounded-full bg-indigo-500/20 animate-ping group-hover:hidden"></div>
                    <LifeBuoy v-if="!isOpen" class="h-6 w-6 transition-transform group-hover:rotate-12" />
                    <X v-else class="h-6 w-6" />
                </button>
            </DialogTrigger>

            <DialogContent class="fixed bottom-24 right-6 sm:max-w-[450px] h-[600px] flex flex-col border-none bg-white/95 backdrop-blur-xl shadow-2xl rounded-3xl overflow-hidden ring-1 ring-black/5 p-0 sm:left-auto sm:top-auto sm:translate-x-0 sm:translate-y-0">
                <!-- Header -->
                <div class="bg-slate-900 px-6 py-5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg">
                            <Bot class="text-white h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-white font-black text-sm uppercase tracking-wider leading-none">Support Agent</h3>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span class="text-[9px] font-black uppercase text-slate-400">Always Online</span>
                            </div>
                        </div>
                    </div>
                    <button @click="resetSession" class="text-slate-500 hover:text-white transition-colors" title="Change Ticket / New Request">
                        <MessageCircle class="h-4 w-4" />
                    </button>
                </div>

                <!-- STEP 1: INITIAL FORM -->
                <div v-if="step === 'initial'" class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div class="text-center space-y-2 mb-4">
                        <h4 class="text-xl font-black text-slate-900 tracking-tight">How can we help?</h4>
                        <p class="text-slate-500 text-xs font-medium px-4">Our superadmin agents are standing by. Start a conversation below.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Topic</Label>
                            <Input v-model="initForm.subject" placeholder="e.g., Payment Issue" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Message</Label>
                            <Textarea v-model="initForm.message" placeholder="Describe your problem..." class="min-h-[140px] bg-slate-50 border-slate-200 rounded-2xl p-4 font-medium resize-none" />
                        </div>
                        <Button @click="initiateChat" :disabled="isLoading || !initForm.subject || !initForm.message" class="w-full h-14 bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest rounded-2xl shadow-xl transition-all">
                            <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin mr-2" />
                            <Send v-else class="h-4 w-4 mr-2" />
                            Start My Chat
                        </Button>
                    </div>
                </div>

                <!-- STEP 2: VERIFICATION -->
                <div v-else-if="step === 'verification'" class="flex-1 flex flex-col items-center justify-center p-8 space-y-6 text-center">
                    <div class="h-20 w-20 rounded-full bg-indigo-50 flex items-center justify-center animate-bounce">
                        <ShieldCheck class="h-10 w-10 text-indigo-600" />
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-xl font-black text-slate-900 tracking-tight">Security Check</h4>
                        <p class="text-slate-500 text-xs font-medium">We've sent a 6-digit code to your **business account email**. Please enter it below to verify your identity.</p>
                    </div>
                    <Input
                        v-model="verificationCode"
                        maxlength="6"
                        class="h-16 text-center text-3xl font-black tracking-[0.5em] bg-slate-50 border-slate-200 rounded-3xl"
                        placeholder="000000"
                    />
                    <Button @click="verifyCode" :disabled="isLoading || verificationCode.length < 6" class="w-full h-14 bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest rounded-3xl shadow-xl">
                        <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin mr-2" />
                        Verify Identity
                    </Button>
                    <button @click="step = 'initial'" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-indigo-600">Incorrect Email? Start Over</button>
                </div>

                <!-- STEP 3: CHAT -->
                <div v-else-if="step === 'chat'" class="flex-1 flex flex-col min-h-0">
                    <!-- Chat Area -->
                    <div ref="scrollContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50">
                        <div v-for="msg in messages" :key="msg.id" :class="['flex', msg.is_from_admin ? 'justify-start' : 'justify-end']">
                            <div :class="['max-w-[80%] rounded-2xl px-4 py-3 shadow-sm', msg.is_from_admin ? 'bg-white text-slate-900 rounded-bl-none ring-1 ring-slate-100' : 'bg-indigo-600 text-white rounded-br-none tracking-tight']">
                                <p class="text-[13px] font-medium leading-relaxed">{{ msg.message }}</p>
                                <span class="text-[9px] mt-1 block opacity-50 font-black uppercase">{{ msg.user?.name || 'User' }}</span>
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div v-if="otherUserTyping" class="flex items-center gap-2 px-2 py-1">
                            <div class="flex space-x-1">
                                <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="h-2 w-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">{{ otherUserName || 'Admin' }} is typing...</span>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="p-4 bg-white border-t border-slate-100">
                        <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                            <div class="flex-1 relative">
                                <input
                                    v-model="newMessage"
                                    @input="onInputChange"
                                    placeholder="Type a message..."
                                    class="w-full h-12 bg-slate-100 border-none rounded-2xl px-4 pr-12 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition-all placeholder:text-slate-400"
                                />
                                <button type="submit" class="absolute right-2 top-1.5 h-9 w-9 bg-indigo-600 text-white rounded-xl flex items-center justify-center hover:scale-105 transition-transform active:scale-95 shadow-md">
                                    <Send class="h-4 w-4" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
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
