<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

const props = defineProps<{
    redirectTo?: string
}>()

const show = ref(true)
const minDisplayTime = 4000 // Minimum 4 seconds
const startTime = Date.now()

onMounted(() => {
    const destination = props.redirectTo || '/dashboard'
    let navigationComplete = false
    let minTimeElapsed = false

    // Function to hide preloader when both conditions are met
    const tryHide = () => {
        if (navigationComplete && minTimeElapsed) {
            show.value = false
        }
    }

    // Start the navigation immediately
    router.visit(destination, {
        preserveState: false,
        preserveScroll: false,
        onFinish: () => {
            navigationComplete = true
            tryHide()
        }
    })

    // Ensure minimum 4 seconds display
    setTimeout(() => {
        minTimeElapsed = true
        tryHide()
    }, minDisplayTime)
})
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 overflow-hidden">
        <!-- Animated background particles -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-indigo-500 rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
            <div class="absolute top-1/3 right-1/4 w-64 h-64 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
            <div class="absolute bottom-1/4 left-1/3 w-64 h-64 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
        </div>

        <!-- Main logo container with zoom animation -->
        <div class="relative z-10 flex flex-col items-center gap-8">
            <!-- Logo with zoom in/out animation -->
            <div class="animate-zoom-pulse">
                <svg
                    class="w-32 h-32 text-white drop-shadow-2xl"
                    viewBox="0 0 200 200"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <!-- Modern POS Logo -->
                    <circle cx="100" cy="100" r="90" fill="currentColor" fill-opacity="0.1" />
                    <circle cx="100" cy="100" r="70" stroke="currentColor" stroke-width="3" />

                    <!-- M Letter stylized -->
                    <path d="M60 80 L60 120 L80 100 L100 120 L100 80" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

                    <!-- P Letter stylized -->
                    <path d="M120 80 L120 120 M120 80 L140 80 Q150 80 150 95 Q150 110 140 110 L120 110" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>

            <!-- ModernPOS Text -->
            <div class="text-center space-y-2">
                <h1 class="text-5xl font-black text-white tracking-tight animate-fade-in">
                    Modern<span class="text-indigo-400">POS</span>
                </h1>
                <p class="text-indigo-200 text-sm font-medium tracking-wider uppercase animate-fade-in animation-delay-500">
                    Point of Sale System
                </p>
            </div>

            <!-- Loading dots -->
            <div class="flex gap-2 animate-fade-in animation-delay-1000">
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce"></div>
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce animation-delay-200"></div>
                <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce animation-delay-400"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes zoom-pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
}

@keyframes blob {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(30px, -50px) scale(1.1);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-zoom-pulse {
    animation: zoom-pulse 2s ease-in-out infinite;
}

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 0.2s;
}

.animation-delay-400 {
    animation-delay: 0.4s;
}

.animation-delay-500 {
    animation-delay: 0.5s;
}

.animation-delay-1000 {
    animation-delay: 1s;
}
</style>
