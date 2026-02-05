<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { CheckCircle2, ArrowRight } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'

import { Button } from '@/components/ui/button'

const countdown = ref(5)

onMounted(() => {
    const timer = setInterval(() => {
        countdown.value--
        if (countdown.value === 0) {
            clearInterval(timer)
            window.location.href = '/login'
        }
    }, 1000)
})
</script>

<template>
    <Head title="Subscription Successful" />

    <div class="min-h-screen bg-gradient-to-br from-green-50 via-white to-indigo-50 flex items-center justify-center p-4">
        <div class="max-w-2xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Success Header -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-12 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-white rounded-full mb-6 animate-bounce">
                    <CheckCircle2 :size="48" class="text-green-500" />
                </div>
                <h1 class="text-4xl font-black text-white mb-4">Payment Successful!</h1>
                <p class="text-green-100 text-lg">Your subscription has been activated</p>
            </div>

            <!-- Content -->
            <div class="p-12 space-y-8">
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-2xl p-8">
                    <h2 class="text-2xl font-black text-slate-900 mb-4">What's Next?</h2>
                    <div class="space-y-4 text-slate-700">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-500 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                1
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Check Your Email</p>
                                <p class="text-sm text-slate-600">We've sent you a payment receipt and subscription details</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-500 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                2
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Log In to Your Account</p>
                                <p class="text-sm text-slate-600">Use your email and password to access your dashboard</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-500 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                3
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Start Using ModernPOS</p>
                                <p class="text-sm text-slate-600">Access all premium features subscribed for your business</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auto Redirect Notice -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center">
                    <p class="text-slate-600 mb-2">Redirecting to login in</p>
                    <div class="text-5xl font-black text-indigo-600">{{ countdown }}</div>
                    <p class="text-sm text-slate-500 mt-2">seconds</p>
                </div>

                <!-- Manual Login Button -->
                <div class="text-center">
                    <a href="/login">
                        <Button class="px-8 h-14 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-base">
                            Go to Login Now
                            <ArrowRight :size="20" class="ml-2" />
                        </Button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
}

.animate-bounce {
    animation: bounce 2s ease-in-out infinite;
}
</style>
