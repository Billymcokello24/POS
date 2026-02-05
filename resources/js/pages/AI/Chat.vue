<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { MessageCircle, Send, Sparkles, TrendingUp, Package, DollarSign, BarChart3, Zap, Brain, Activity } from 'lucide-vue-next'
import { ref, onMounted, nextTick, computed } from 'vue'

import axios from '@/axios'
import AppLayout from '@/layouts/AppLayout.vue'


onMounted(() => {})

const chatInput = ref('')
const chatHistory = ref<Array<{role:string, text:string}>>([])
const loading = ref(false)
const chatContainer = ref<HTMLElement | null>(null)

const examplePrompts = [
    { icon: BarChart3, text: "Write me a report for this month", gradient: "from-violet-500 to-purple-600" },
    { icon: TrendingUp, text: "Show sales for the last 7 days", gradient: "from-emerald-500 to-teal-600" },
    { icon: Package, text: "Find product SKU-ABC123 and show stock levels", gradient: "from-amber-500 to-orange-600" },
    { icon: DollarSign, text: "Which products have been slow moving for 90 days?", gradient: "from-rose-500 to-pink-600" }
]

const lastAssistantMessage = computed(() => {
    const assistantMessages = chatHistory.value.filter(m => m.role === 'assistant')
    return assistantMessages.length > 0 ? assistantMessages[assistantMessages.length - 1].text : null
})

async function sendChat() {
    if (!chatInput.value.trim()) return
    const text = chatInput.value.trim()
    chatHistory.value.push({ role: 'user', text })
    chatInput.value = ''
    loading.value = true

    await nextTick()
    scrollToBottom()

    try {
        const res = await axios.post('/api/ai/chat', { message: text })
        const reply = res?.data?.data?.reply ?? ''
        chatHistory.value.push({ role: 'assistant', text: reply })
    } catch (e: any) {
        const msg = e?.response?.data?.message || e?.message || 'Chat failed'
        chatHistory.value.push({ role: 'assistant', text: `[error] ${msg}` })
    } finally {
        loading.value = false
        await nextTick()
        scrollToBottom()
    }
}

function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
}

function usePrompt(prompt: string) {
    chatInput.value = prompt
}
</script>

<template>
    <Head title="AI Chat" />
    <AppLayout>
        <div class="min-h-screen relative overflow-hidden bg-[#0A0B14]">

            <!-- Animated Background -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-0 left-1/4 w-48 h-48 sm:w-96 sm:h-96 bg-violet-500/10 rounded-full blur-3xl animate-pulse-slow"></div>
                <div class="absolute bottom-0 right-1/4 w-48 h-48 sm:w-96 sm:h-96 bg-cyan-500/10 rounded-full blur-3xl animate-pulse-slower"></div>
                <div class="absolute top-1/2 left-1/2 w-48 h-48 sm:w-96 sm:h-96 bg-fuchsia-500/5 rounded-full blur-3xl animate-pulse-slowest"></div>

                <!-- Grid Pattern -->
                <div class="absolute inset-0 bg-grid-pattern opacity-[0.02]"></div>
            </div>

            <div class="relative mx-auto max-w-[1600px] px-3 sm:px-6 py-6 sm:py-12">

                <!-- Header with Glassmorphism - Mobile Optimized -->
                <div class="mb-6 sm:mb-12 animate-fade-in-down">
                    <div class="relative inline-block w-full sm:w-auto">
                        <div class="absolute -inset-2 sm:-inset-4 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-cyan-500 rounded-2xl sm:rounded-3xl opacity-20 blur-2xl animate-pulse-slow"></div>
                        <div class="relative flex items-center gap-3 sm:gap-4">
                            <div class="relative p-3 sm:p-4 bg-gradient-to-br from-violet-500 to-fuchsia-600 rounded-xl sm:rounded-2xl shadow-2xl shadow-violet-500/30 flex-shrink-0">
                                <Brain class="h-6 w-6 sm:h-8 sm:w-8 text-white animate-float" />
                                <div class="absolute inset-0 bg-white/20 rounded-xl sm:rounded-2xl animate-shine"></div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white mb-1 truncate" style="font-family: 'Syne', sans-serif;">
                                    Neural Intelligence
                                </h1>
                                <p class="text-sm sm:text-base lg:text-lg text-violet-300/80 font-light tracking-wide truncate" style="font-family: 'Space Grotesk', sans-serif;">
                                    <span class="hidden sm:inline">Real-time business insights powered by advanced AI</span>
                                    <span class="sm:hidden">AI-Powered Insights</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Bar - Mobile Optimized -->
                <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-4 sm:mb-8 animate-fade-in-up" style="animation-delay: 200ms">
                    <div class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-xl sm:rounded-2xl p-2 sm:p-4 hover:border-violet-500/50 transition-all duration-500 group">
                        <div class="flex flex-col sm:flex-row items-center sm:gap-3 gap-1">
                            <div class="p-1.5 sm:p-2.5 bg-violet-500/20 rounded-lg sm:rounded-xl group-hover:bg-violet-500/30 transition-colors flex-shrink-0">
                                <Activity class="h-4 w-4 sm:h-5 sm:w-5 text-violet-400" />
                            </div>
                            <div class="text-center sm:text-left">
                                <div class="text-lg sm:text-2xl font-bold text-white" style="font-family: 'Syne', sans-serif;">{{ chatHistory.length }}</div>
                                <div class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider" style="font-family: 'Space Grotesk', sans-serif;">Messages</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-xl sm:rounded-2xl p-2 sm:p-4 hover:border-cyan-500/50 transition-all duration-500 group">
                        <div class="flex flex-col sm:flex-row items-center sm:gap-3 gap-1">
                            <div class="p-1.5 sm:p-2.5 bg-cyan-500/20 rounded-lg sm:rounded-xl group-hover:bg-cyan-500/30 transition-colors flex-shrink-0">
                                <Zap class="h-4 w-4 sm:h-5 sm:w-5 text-cyan-400" />
                            </div>
                            <div class="text-center sm:text-left">
                                <div class="text-lg sm:text-2xl font-bold text-white" style="font-family: 'Syne', sans-serif;">Active</div>
                                <div class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider" style="font-family: 'Space Grotesk', sans-serif;">Status</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-xl sm:rounded-2xl p-2 sm:p-4 hover:border-fuchsia-500/50 transition-all duration-500 group">
                        <div class="flex flex-col sm:flex-row items-center sm:gap-3 gap-1">
                            <div class="p-1.5 sm:p-2.5 bg-fuchsia-500/20 rounded-lg sm:rounded-xl group-hover:bg-fuchsia-500/30 transition-colors flex-shrink-0">
                                <Sparkles class="h-4 w-4 sm:h-5 sm:w-5 text-fuchsia-400" />
                            </div>
                            <div class="text-center sm:text-left">
                                <div class="text-lg sm:text-2xl font-bold text-white" style="font-family: 'Syne', sans-serif;">GPT-4</div>
                                <div class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider" style="font-family: 'Space Grotesk', sans-serif;">Model</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">

                    <!-- Main Chat - Mobile Optimized -->
                    <div class="lg:col-span-8 animate-fade-in-up" style="animation-delay: 300ms">
                        <div class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl shadow-black/50 hover:border-white/20 transition-all duration-500">

                            <!-- Chat Header - Mobile Optimized -->
                            <div class="border-b border-white/10 bg-gradient-to-r from-violet-500/10 via-fuchsia-500/10 to-cyan-500/10 p-3 sm:p-6">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                                        <div class="relative flex-shrink-0">
                                            <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                                            <div class="absolute inset-0 w-2 h-2 bg-emerald-400 rounded-full animate-ping"></div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-base sm:text-xl font-bold text-white truncate" style="font-family: 'Syne', sans-serif;">Neural Conversation</div>
                                            <div class="text-xs sm:text-sm text-gray-400 truncate" style="font-family: 'Space Grotesk', sans-serif;">
                                                <span class="hidden sm:inline">AI-powered business intelligence</span>
                                                <span class="sm:hidden">AI Intelligence</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-2 sm:px-4 py-1 sm:py-1.5 bg-white/5 rounded-full border border-white/10 flex-shrink-0">
                                        <span class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-widest" style="font-family: 'Space Grotesk', sans-serif;">Encrypted</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages - Mobile Optimized -->
                            <div
                                ref="chatContainer"
                                class="h-[400px] sm:h-[580px] overflow-y-auto p-4 sm:p-8 space-y-4 sm:space-y-6 scroll-smooth custom-scrollbar"
                            >
                                <!-- Empty State - Mobile Optimized -->
                                <div v-if="chatHistory.length === 0" class="h-full flex flex-col items-center justify-center px-3">
                                    <div class="relative mb-4 sm:mb-8 animate-float">
                                        <div class="absolute inset-0 bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-full opacity-30 blur-3xl"></div>
                                        <div class="relative p-6 sm:p-8 bg-gradient-to-br from-violet-500/20 to-fuchsia-500/20 rounded-full border border-white/10">
                                            <MessageCircle class="h-12 w-12 sm:h-16 sm:w-16 text-violet-400" />
                                        </div>
                                    </div>
                                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2 sm:mb-3 text-center" style="font-family: 'Syne', sans-serif;">
                                        Initiate Neural Link
                                    </h3>
                                    <p class="text-sm sm:text-base text-gray-400 max-w-md text-center mb-4 sm:mb-8 leading-relaxed" style="font-family: 'Space Grotesk', sans-serif;">
                                        Connect with advanced AI to analyze your business metrics.
                                    </p>
                                    <div class="flex flex-wrap gap-2 sm:gap-3 justify-center max-w-2xl">
                                        <button
                                            v-for="(prompt, idx) in examplePrompts"
                                            :key="idx"
                                            @click="usePrompt(prompt.text)"
                                            class="group px-3 sm:px-5 py-2 sm:py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 rounded-lg sm:rounded-xl transition-all duration-300 hover:scale-105 hover:shadow-lg text-left"
                                        >
                                            <div class="flex items-center gap-2">
                                                <component :is="prompt.icon" class="h-3 w-3 sm:h-4 sm:w-4 text-gray-400 group-hover:text-white transition-colors flex-shrink-0" />
                                                <span class="text-xs sm:text-sm text-gray-300 group-hover:text-white transition-colors line-clamp-2" style="font-family: 'Space Grotesk', sans-serif;">
                          {{ prompt.text }}
                        </span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Messages -->
                                <div v-for="(m, idx) in chatHistory" :key="idx" class="animate-message-appear">
                                    <!-- User Message -->
                                    <div v-if="m.role === 'user'" class="flex justify-end">
                                        <div class="max-w-[85%] sm:max-w-[75%] group">
                                            <div class="flex items-start gap-2 sm:gap-3 flex-row-reverse">
                                                <div class="relative flex-shrink-0">
                                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center shadow-lg shadow-violet-500/30">
                                                        <div class="text-[10px] sm:text-xs font-bold text-white" style="font-family: 'Syne', sans-serif;">YOU</div>
                                                    </div>
                                                </div>
                                                <div class="relative">
                                                    <div class="absolute -inset-1 bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-3xl opacity-20 blur group-hover:opacity-30 transition-opacity"></div>
                                                    <div class="relative px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl rounded-tr-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 shadow-xl">
                                                        <p class="text-xs sm:text-[15px] text-white leading-relaxed" style="font-family: 'Space Grotesk', sans-serif;">{{ m.text }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- AI Message -->
                                    <div v-else class="flex justify-start">
                                        <div class="max-w-[90%] sm:max-w-[80%] group">
                                            <div class="flex items-start gap-2 sm:gap-3">
                                                <div class="relative flex-shrink-0">
                                                    <div class="absolute inset-0 bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-2xl opacity-50 blur animate-pulse"></div>
                                                    <div class="relative w-8 h-8 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-gradient-to-br from-cyan-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                                                        <Brain class="h-4 w-4 sm:h-5 sm:w-5 text-white" />
                                                    </div>
                                                </div>
                                                <div class="relative">
                                                    <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-3xl opacity-10 blur group-hover:opacity-20 transition-opacity"></div>
                                                    <div class="relative px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl rounded-tl-lg bg-white/5 backdrop-blur-xl border border-white/10 shadow-xl group-hover:border-white/20 transition-colors">
                                                        <p class="text-xs sm:text-[15px] text-gray-100 leading-relaxed" style="font-family: 'Space Grotesk', sans-serif;">{{ m.text }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loading -->
                                <div v-if="loading" class="flex justify-start animate-message-appear">
                                    <div class="flex items-start gap-2 sm:gap-3">
                                        <div class="relative flex-shrink-0">
                                            <div class="absolute inset-0 bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-2xl opacity-50 blur animate-pulse"></div>
                                            <div class="relative w-8 h-8 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-gradient-to-br from-cyan-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                                                <Brain class="h-4 w-4 sm:h-5 sm:w-5 text-white animate-pulse" />
                                            </div>
                                        </div>
                                        <div class="px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl rounded-tl-lg bg-white/5 backdrop-blur-xl border border-white/10 shadow-xl">
                                            <div class="flex gap-1 sm:gap-1.5">
                                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-cyan-400 rounded-full animate-bounce-custom" style="animation-delay: 0ms"></div>
                                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-cyan-400 rounded-full animate-bounce-custom" style="animation-delay: 150ms"></div>
                                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-cyan-400 rounded-full animate-bounce-custom" style="animation-delay: 300ms"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Input - Mobile Optimized -->
                            <div class="border-t border-white/10 bg-gradient-to-r from-violet-500/5 via-fuchsia-500/5 to-cyan-500/5 p-3 sm:p-6">
                                <div class="flex gap-2 sm:gap-4">
                                    <div class="flex-1 relative group">
                                        <div class="absolute -inset-0.5 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-cyan-500 rounded-xl sm:rounded-2xl opacity-0 group-focus-within:opacity-20 blur transition-opacity duration-500"></div>
                                        <input
                                            v-model="chatInput"
                                            @keydown.enter.prevent="sendChat"
                                            type="text"
                                            class="relative w-full bg-white/5 backdrop-blur-xl border border-white/10 rounded-xl sm:rounded-2xl px-4 sm:px-6 py-3 sm:py-4 text-sm sm:text-base text-white placeholder-gray-500 focus:outline-none focus:border-violet-500/50 transition-all duration-300 shadow-xl"
                                            placeholder="Enter your query..."
                                            :disabled="loading"
                                            style="font-family: 'Space Grotesk', sans-serif;"
                                        />
                                    </div>
                                    <button
                                        @click.prevent="sendChat"
                                        :disabled="loading || !chatInput.trim()"
                                        class="relative group px-4 sm:px-8 bg-gradient-to-r from-violet-500 to-fuchsia-600 hover:from-violet-600 hover:to-fuchsia-700 text-white rounded-xl sm:rounded-2xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed hover:scale-105 shadow-xl shadow-violet-500/30 hover:shadow-2xl hover:shadow-violet-500/50 flex-shrink-0"
                                    >
                                        <div class="absolute inset-0 bg-white/20 rounded-xl sm:rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 animate-shine"></div>
                                        <div class="relative flex items-center gap-2">
                                            <Send class="h-4 w-4 sm:h-5 sm:w-5" />
                                            <span class="font-semibold hidden sm:inline" style="font-family: 'Syne', sans-serif;">Send</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar - Mobile Optimized -->
                    <div class="lg:col-span-4 space-y-4 sm:space-y-6 animate-fade-in-up" style="animation-delay: 400ms">

                        <!-- Quick Actions -->
                        <div class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl shadow-black/50 hover:border-white/20 transition-all duration-500">
                            <div class="border-b border-white/10 bg-gradient-to-r from-violet-500/10 to-fuchsia-500/10 p-4 sm:p-5">
                                <div class="flex items-center gap-2">
                                    <Zap class="h-4 w-4 sm:h-5 sm:w-5 text-violet-400" />
                                    <h3 class="text-base sm:text-lg font-bold text-white" style="font-family: 'Syne', sans-serif;">Quick Launch</h3>
                                </div>
                            </div>
                            <div class="p-4 sm:p-5 space-y-2 sm:space-y-3">
                                <button
                                    v-for="(prompt, idx) in examplePrompts"
                                    :key="idx"
                                    @click="usePrompt(prompt.text)"
                                    class="group w-full text-left"
                                >
                                    <div class="relative">
                                        <div :class="`absolute -inset-0.5 bg-gradient-to-r ${prompt.gradient} rounded-xl sm:rounded-2xl opacity-0 group-hover:opacity-20 blur transition-opacity duration-500`"></div>
                                        <div class="relative p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-white/5 border border-white/10 hover:border-white/20 hover:bg-white/10 transition-all duration-300">
                                            <div class="flex items-start gap-2 sm:gap-3">
                                                <div :class="`p-2 sm:p-2.5 rounded-lg sm:rounded-xl bg-gradient-to-br ${prompt.gradient} bg-opacity-20 group-hover:scale-110 transition-transform duration-300 flex-shrink-0`">
                                                    <component :is="prompt.icon" class="h-3 w-3 sm:h-4 sm:w-4 text-white" />
                                                </div>
                                                <p class="text-xs sm:text-sm text-gray-300 group-hover:text-white leading-relaxed flex-1 transition-colors line-clamp-2" style="font-family: 'Space Grotesk', sans-serif;">
                                                    {{ prompt.text }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Latest Response -->
                        <div v-if="lastAssistantMessage" class="bg-gradient-to-br from-white/5 to-white/[0.02] backdrop-blur-xl border border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl shadow-black/50 hover:border-white/20 transition-all duration-500">
                            <div class="border-b border-white/10 bg-gradient-to-r from-cyan-500/10 to-emerald-500/10 p-4 sm:p-5">
                                <div class="flex items-center gap-2">
                                    <Brain class="h-4 w-4 sm:h-5 sm:w-5 text-cyan-400" />
                                    <h3 class="text-base sm:text-lg font-bold text-white" style="font-family: 'Syne', sans-serif;">Latest Output</h3>
                                </div>
                            </div>
                            <div class="p-4 sm:p-5">
                                <div class="relative group">
                                    <div class="absolute -inset-0.5 bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-xl sm:rounded-2xl opacity-10 blur"></div>
                                    <div class="relative p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-white/5 border border-white/10">
                                        <p class="text-xs sm:text-sm text-gray-300 leading-relaxed line-clamp-6" style="font-family: 'Space Grotesk', sans-serif;">
                                            {{ lastAssistantMessage }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Info -->
                        <div class="relative group">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-cyan-500 rounded-2xl sm:rounded-3xl opacity-30 blur-xl group-hover:opacity-50 transition-opacity duration-500"></div>
                            <div class="relative bg-gradient-to-br from-violet-500/20 to-fuchsia-500/20 backdrop-blur-xl border border-white/20 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-2xl">
                                <div class="flex items-start gap-3 sm:gap-4">
                                    <div class="p-2 sm:p-3 bg-white/10 rounded-xl sm:rounded-2xl backdrop-blur-sm flex-shrink-0">
                                        <Sparkles class="h-5 w-5 sm:h-6 sm:w-6 text-white" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg sm:text-xl font-bold text-white mb-1 sm:mb-2" style="font-family: 'Syne', sans-serif;">Neural Engine</h3>
                                        <p class="text-xs sm:text-sm text-violet-200/80 leading-relaxed" style="font-family: 'Space Grotesk', sans-serif;">
                                            Powered by advanced machine learning models with real-time access to your complete business data ecosystem.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Import distinctive fonts */
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap');

/* Custom Animations */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes pulse-slow {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

@keyframes pulse-slower {
    0%, 100% { opacity: 0.2; }
    50% { opacity: 0.5; }
}

@keyframes pulse-slowest {
    0%, 100% { opacity: 0.1; }
    50% { opacity: 0.3; }
}

@keyframes fade-in-down {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes message-appear {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes bounce-custom {
    0%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.animate-shine {
    animation: shine 3s ease-in-out infinite;
}

.animate-pulse-slow {
    animation: pulse-slow 4s ease-in-out infinite;
}

.animate-pulse-slower {
    animation: pulse-slower 6s ease-in-out infinite;
}

.animate-pulse-slowest {
    animation: pulse-slowest 8s ease-in-out infinite;
}

.animate-fade-in-down {
    animation: fade-in-down 0.6s ease-out;
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out;
}

.animate-message-appear {
    animation: message-appear 0.4s ease-out;
}

.animate-bounce-custom {
    animation: bounce-custom 1.4s infinite;
}

/* Grid Pattern */
.bg-grid-pattern {
    background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 50px 50px;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, rgba(139, 92, 246, 0.5), rgba(236, 72, 153, 0.5));
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, rgba(139, 92, 246, 0.7), rgba(236, 72, 153, 0.7));
}

/* Text Selection */
::selection {
    background-color: rgba(139, 92, 246, 0.3);
    color: white;
}
</style>
