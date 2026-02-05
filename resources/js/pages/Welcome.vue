<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ShoppingCart, ArrowRight, Search, Menu, X, ArrowUpRight, ShieldCheck, CheckCircle2, BarChart3, Users, Zap, Crown, Rocket } from 'lucide-vue-next'
import { toRef, ref, onMounted } from 'vue'

import axios from '@/axios'
import SubscriptionModal from '@/components/SubscriptionModal.vue'
import { dashboard, login } from '@/routes'

interface Plan {
    id: number
    name: string
    description: string
    price_monthly: number
    price_yearly: number
    currency: string
    features: { id: number; name: string; description: string }[]
    is_popular: boolean
}

// Receive CMS content from the server (Inertia).
const props = defineProps<{
    canRegister?: boolean
    cms?: {
        hero_title?: string
        hero_subtitle?: string
        hero_bg_image?: string
        announcement_text?: string
        about_title?: string
        about_content?: string
        seo_site_title?: string
        seo_meta_description?: string
        media_logo_url?: string
        media_favicon_url?: string
    }
}>()

// `cms` is a reactive ref bound to the incoming prop — template uses optional chaining to avoid TS undefined errors
const cms = toRef(props, 'cms')

// Subscription plans
const plans = ref<Plan[]>([])
const selectedPlan = ref<Plan | null>(null)
const showModal = ref(false)
const loadingPlans = ref(true)

// Navigation scroll state
const isScrolled = ref(false)

// Mobile menu state
const mobileMenuOpen = ref(false)

// Contact form state
const contactForm = ref({
    email: '',
    subject: '',
    message: ''
})
const isSubmitting = ref(false)
const formMessage = ref<{ type: 'success' | 'error', text: string } | null>(null)

const submitContactForm = async () => {
    if (!contactForm.value.email || !contactForm.value.subject || !contactForm.value.message) {
        formMessage.value = { type: 'error', text: 'Please fill in all fields' }
        return
    }

    isSubmitting.value = true
    formMessage.value = null

    try {
        const response = await axios.post('/api/contact', contactForm.value)
        if (response.data.success) {
            formMessage.value = { type: 'success', text: 'Message sent successfully! We\'ll get back to you soon.' }
            contactForm.value = { email: '', subject: '', message: '' }
        } else {
            formMessage.value = { type: 'error', text: response.data.message || 'Failed to send message' }
        }
    } catch (error: any) {
        formMessage.value = { type: 'error', text: error.response?.data?.message || 'Failed to send message. Please try again.' }
    } finally {
        isSubmitting.value = false
    }
}

onMounted(async () => {
    try {
        const response = await axios.get('/api/public/plans')
        if (response.data.success) {
            plans.value = response.data.plans
        }
    } catch (error) {
        console.error('Failed to load plans:', error)
    } finally {
        loadingPlans.value = false
    }

    // Add scroll listener for navigation bar
    const handleScroll = () => {
        // Change to white background when scrolled 100px from top
        isScrolled.value = window.scrollY > 100
    }
    window.addEventListener('scroll', handleScroll)

    // Initial check on mount
    handleScroll()

    // Cleanup on unmount
    return () => {
        window.removeEventListener('scroll', handleScroll)
    }
})

const subscribe = (plan: Plan) => {
    selectedPlan.value = plan
    showModal.value = true
}

const handleSuccess = () => {
    showModal.value = false
    // Modal will redirect to success page
}
</script>

<template>
    <Head>
        <title>{{ cms?.seo_site_title || 'Modern POS - Retail Architecture' }}</title>
        <meta name="description" :content="cms?.seo_meta_description || 'The complete retail operating system.'" />
        <link v-if="cms?.media_favicon_url" rel="icon" :href="cms?.media_favicon_url" />
         <link rel="preconnect" href="https://fonts.googleapis.com" />
         <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
         <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
     </Head>

     <div class="min-h-screen font-inter selection:bg-[#3B82F6] selection:text-white relative bg-[#F8F9FA] scroll-smooth">
        <!-- Global Background Image -->
        <div class="fixed inset-0 z-0 pointer-events-none">
             <img
                :src="cms?.hero_bg_image || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?q=80&w=2069&auto=format&fit=crop'"
                alt="Background Texture"
                class="w-full h-full object-cover opacity-[0.08]"
            />
        </div>

        <!-- Enhanced Navigation -->
        <nav :class="[
            'fixed top-0 left-0 right-0 z-50 transition-all duration-500',
            isScrolled
                ? 'bg-white shadow-lg border-b border-[#0E1129]/10'
                : 'bg-white/5 backdrop-blur-md border-b border-white/10'
        ]">
            <div class="px-4 md:px-12 py-4 md:py-5 flex items-center justify-between max-w-[1920px] mx-auto">
                <!-- Logo -->
                <div class="flex items-center gap-2 md:gap-4">
                    <div :class="[
                        'w-10 h-10 md:w-12 md:h-12 flex items-center justify-center rounded-lg transition-all duration-300',
                        isScrolled ? 'bg-[#0E1129]' : 'bg-[#0E1129]'
                    ]">
                        <img v-if="cms?.media_logo_url" :src="cms?.media_logo_url" class="w-7 h-7 md:w-9 md:h-9 object-contain" alt="Logo" />
                        <template v-else>
                            <ShoppingCart :size="18" class="md:hidden text-white" stroke-width="2.5" />
                            <ShoppingCart :size="22" class="hidden md:block text-white" stroke-width="2.5" />
                        </template>
                    </div>
                    <span :class="[
                        'text-lg md:text-2xl font-bold tracking-tight transition-colors duration-300',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">
                        {{ cms?.seo_site_title ? cms?.seo_site_title.split(' ')[0] : 'MODERN' }}<span class="text-[#3B82F6]">POS</span>
                    </span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden lg:flex items-center gap-8 xl:gap-10">
                    <a href="#features" :class="[
                        'text-sm font-bold uppercase tracking-wider transition-colors duration-300 hover:text-[#3B82F6]',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">Features</a>

                    <a href="#about" :class="[
                        'text-sm font-bold uppercase tracking-wider transition-colors duration-300 hover:text-[#3B82F6]',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">About</a>

                    <a href="#plans" :class="[
                        'text-sm font-bold uppercase tracking-wider transition-colors duration-300 hover:text-[#3B82F6]',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">Plans</a>

                    <a href="#contact" :class="[
                        'text-sm font-bold uppercase tracking-wider transition-colors duration-300 hover:text-[#3B82F6]',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">Contact Us</a>

                    <a href="#demo" :class="[
                        'text-sm font-bold uppercase tracking-wider transition-colors duration-300 hover:text-[#3B82F6]',
                        isScrolled ? 'text-[#0E1129]' : 'text-white'
                    ]">Demo</a>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Mobile Menu Toggle -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden p-2 hover:bg-white/10 rounded-lg transition-colors"
                        :class="isScrolled ? 'text-[#0E1129]' : 'text-white'"
                    >
                        <Menu v-if="!mobileMenuOpen" :size="24" />
                        <X v-else :size="24" />
                    </button>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        :class="[
                            'hidden md:flex items-center gap-2 text-sm font-bold uppercase tracking-wider transition-all duration-300',
                            isScrolled ? 'text-[#0E1129] hover:text-[#3B82F6]' : 'text-white hover:text-[#3B82F6]'
                        ]"
                    >
                        Dashboard <ArrowRight :size="16" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            :class="[
                                'hidden md:flex items-center gap-2 text-sm font-bold uppercase tracking-wider transition-all duration-300',
                                isScrolled ? 'text-[#0E1129] hover:text-[#3B82F6]' : 'text-white hover:text-[#3B82F6]'
                            ]"
                        >
                            Login
                        </Link>
                        <a
                            href="/register-business"
                            :class="[
                                'px-4 md:px-6 py-2 md:py-3 text-xs md:text-sm font-bold uppercase tracking-wider rounded-full transition-all duration-300 hover:scale-95 shadow-lg whitespace-nowrap',
                                isScrolled
                                    ? 'bg-[#3B82F6] text-white hover:bg-[#0E1129]'
                                    : 'bg-[#3B82F6] text-white hover:bg-white hover:text-[#0E1129]'
                            ]"
                        >
                            Get Started
                        </a>
                    </template>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div
                v-if="mobileMenuOpen"
                :class="[
                    'lg:hidden absolute top-full left-0 right-0 shadow-lg border-t transition-all duration-300',
                    isScrolled ? 'bg-white border-[#0E1129]/10' : 'bg-[#0E1129]/95 backdrop-blur-md border-white/10'
                ]"
            >
                <div class="px-4 py-4 space-y-2">
                    <a
                        href="#features"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-4 py-3 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors',
                            isScrolled
                                ? 'text-[#0E1129] hover:bg-[#F8F9FA] hover:text-[#3B82F6]'
                                : 'text-white hover:bg-white/10 hover:text-[#3B82F6]'
                        ]"
                    >
                        Features
                    </a>
                    <a
                        href="#about"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-4 py-3 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors',
                            isScrolled
                                ? 'text-[#0E1129] hover:bg-[#F8F9FA] hover:text-[#3B82F6]'
                                : 'text-white hover:bg-white/10 hover:text-[#3B82F6]'
                        ]"
                    >
                        About
                    </a>
                    <a
                        href="#plans"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-4 py-3 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors',
                            isScrolled
                                ? 'text-[#0E1129] hover:bg-[#F8F9FA] hover:text-[#3B82F6]'
                                : 'text-white hover:bg-white/10 hover:text-[#3B82F6]'
                        ]"
                    >
                        Plans
                    </a>
                    <a
                        href="#contact"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-4 py-3 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors',
                            isScrolled
                                ? 'text-[#0E1129] hover:bg-[#F8F9FA] hover:text-[#3B82F6]'
                                : 'text-white hover:bg-white/10 hover:text-[#3B82F6]'
                        ]"
                    >
                        Contact Us
                    </a>
                    <a
                        href="#demo"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-4 py-3 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors',
                            isScrolled
                                ? 'text-[#0E1129] hover:bg-[#F8F9FA] hover:text-[#3B82F6]'
                                : 'text-white hover:bg-white/10 hover:text-[#3B82F6]'
                        ]"
                    >
                        Demo
                    </a>

                    <!-- Mobile Login Button -->
                    <div class="pt-2 border-t" :class="isScrolled ? 'border-[#0E1129]/10' : 'border-white/10'">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            @click="mobileMenuOpen = false"
                            class="block w-full px-4 py-3 bg-[#3B82F6] text-white text-center font-bold uppercase tracking-wider rounded-lg hover:bg-[#0E1129] transition-colors text-sm"
                        >
                            Dashboard
                        </Link>
                        <Link
                            v-else
                            :href="login()"
                            @click="mobileMenuOpen = false"
                            class="block w-full px-4 py-3 bg-[#3B82F6] text-white text-center font-bold uppercase tracking-wider rounded-lg hover:bg-[#0E1129] transition-colors text-sm"
                        >
                            Login
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center pt-16 md:pt-20 overflow-hidden bg-[#0E1129]">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img
                    :src="cms?.hero_bg_image || 'https://images.unsplash.com/photo-1556742526-795a3eac97d5?q=80&w=2068&auto=format&fit=crop'"
                    alt="Modern Retail POS Environment"
                    class="w-full h-full object-cover opacity-100"
                />
                 <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] via-[#0E1129]/60 to-transparent"></div>
                 <!-- Animated Aura Glows -->
                 <div class="absolute top-0 right-0 w-[400px] md:w-[800px] h-[400px] md:h-[800px] bg-[#3B82F6]/10 rounded-full blur-[120px] -translate-y-1/2 translate-x-1/2 animate-blob mix-blend-screen" aria-hidden="true"></div>
                 <div class="absolute bottom-0 left-1/4 w-[300px] md:w-[600px] h-[300px] md:h-[600px] bg-[#10B981]/5 rounded-full blur-[100px] translate-y-1/2 animate-blob animation-delay-2000 mix-blend-screen" aria-hidden="true"></div>
            </div>

            <div class="relative z-10 w-full max-w-[1920px] mx-auto px-4 md:px-12 grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-end pb-16 md:pb-32">
                <div class="lg:col-span-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-white/70 text-[10px] font-bold uppercase tracking-widest mb-6 md:mb-8">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#10B981] animate-pulse"></div>
                        {{ cms?.announcement_text || 'System Status: Optimal' }}
                    </div>
                    <h1 class="text-4xl sm:text-5xl md:text-7xl lg:text-[7rem] leading-[0.95] md:leading-[0.9] font-extrabold text-white tracking-tight mb-6 md:mb-8">
                        <span v-html="cms?.hero_title"></span>
                    </h1>
                    <p class="text-base md:text-lg lg:text-xl font-medium text-white/60 max-w-xl leading-relaxed">
                        {{ cms?.hero_subtitle }}
                    </p>
                </div>

                <!-- Glassmorphic Search / Action Card -->
                <div class="lg:col-span-4 flex justify-end w-full">
                    <div class="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 p-6 md:p-8 rounded-2xl md:rounded-[2rem] shadow-2xl">
                        <div class="flex items-center justify-between mb-6 md:mb-8">
                            <span class="text-xs font-bold tracking-[0.2em] uppercase text-white">System Access</span>
                            <div class="w-2 h-2 rounded-full bg-[#10B981] animate-pulse"></div>
                        </div>

                        <div class="space-y-3 md:space-y-4">
                            <div class="p-3 md:p-4 rounded-xl bg-white/5 border border-white/5 flex items-center gap-3 md:gap-4">
                                <div class="w-10 h-10 rounded-lg bg-[#3B82F6]/20 flex items-center justify-center text-[#3B82F6]">
                                    <ShoppingCart :size="20" />
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-white">Point of Sale</div>
                                    <div class="text-[10px] text-white/50 uppercase tracking-widest">Active Module</div>
                                </div>
                            </div>

                            <Link :href="login()" class="flex items-center justify-between w-full bg-[#3B82F6] text-white p-3 md:p-4 rounded-xl group cursor-pointer hover:bg-white hover:text-[#0E1129] transition-all duration-300">
                                <span class="text-xs md:text-sm font-bold uppercase tracking-widest pl-2">Enter Workspace</span>
                                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center group-hover:bg-[#0E1129]/10 transition-colors">
                                    <ArrowUpRight :size="20" />
                                </div>
                            </Link>

                        </div>

                         <div class="mt-8 pt-6 border-t border-white/10 grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-lg font-bold text-white">2.4s</div>
                                <div class="text-[9px] uppercase tracking-widest text-white/40">Checkout</div>
                            </div>
                            <div>
                                <div class="text-lg font-bold text-white">Real-time</div>
                                <div class="text-[9px] uppercase tracking-widest text-white/40">Sync</div>
                            </div>
                            <div>
                                <div class="text-lg font-bold text-white">AES-256</div>
                                <div class="text-[9px] uppercase tracking-widest text-white/40">Security</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Marquee Strip -->
        <div class="border-y border-[#0E1129]/5 bg-white py-6 overflow-hidden relative">
            <div class="flex animate-marquee gap-16 items-center">
                 <!-- Set 1 -->
                 <div class="flex gap-16 items-center shrink-0">
                    <div class="text-sm font-bold tracking-[0.1em] text-[#0E1129]/40 uppercase">System Status: Optimal</div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Inventory: Synced</span>
                    </div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                         <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Datastream: 42ms Latency</span>
                    </div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                         <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Gateway: TLS 1.3 Secure</span>
                    </div>
                    <div class="text-sm font-bold tracking-[0.1em] text-[#0E1129]/40 uppercase">v2.4.0 (Stable)</div>
                 </div>

                 <!-- Set 2 (Duplicate for smooth loop) -->
                 <div class="flex gap-16 items-center shrink-0">
                    <div class="text-sm font-bold tracking-[0.1em] text-[#0E1129]/40 uppercase">System Status: Optimal</div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Inventory: Synced</span>
                    </div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Datastream: 42ms Latency</span>
                    </div>
                     <div class="flex items-center gap-2 text-[#0E1129]/60">
                         <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-bold uppercase tracking-widest">Gateway: TLS 1.3 Secure</span>
                    </div>
                    <div class="text-sm font-bold tracking-[0.1em] text-[#0E1129]/40 uppercase">v2.4.0 (Stable)</div>
                 </div>
            </div>

             <!-- Gradient Masks for seamless fade -->
             <div class="absolute inset-y-0 left-0 w-32 bg-gradient-to-r from-white to-transparent z-10"></div>
             <div class="absolute inset-y-0 right-0 w-32 bg-gradient-to-l from-white to-transparent z-10"></div>
        </div>

        <section id="about" class="py-16 md:py-32 px-4 md:px-12 max-w-[1920px] mx-auto bg-[#F8F9FA]/90 relative z-10">
             <div class="mb-20">
                <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">Access Control</span>
                <h2 class="text-4xl md:text-5xl font-bold text-[#0E1129] mb-6">{{ cms?.about_title || 'Designed for every \n role in your business.' }}</h2>
                <p class="text-lg text-[#0E1129]/60 max-w-2xl">
                    {{ cms?.about_content || 'Strict role-based access control (RBAC) ensures your data is secure. Cashiers focus on selling, while Admins manage the big picture.' }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Admin Card -->
                <div class="group relative bg-[#0E1129] rounded-[2.5rem] p-12 overflow-hidden text-white min-h-[500px] flex flex-col justify-between shadow-2xl shadow-[#0E1129]/10 transition-all duration-500 hover:shadow-3xl hover:-translate-y-2">
                     <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-[#3B82F6]/20 rounded-full blur-[100px] -translate-y-1/2 translate-x-1/2 group-hover:bg-[#3B82F6]/30 transition-colors duration-500" aria-hidden="true"></div>
                     <div class="relative z-10">
                         <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center mb-10 border border-white/10 backdrop-blur-sm group-hover:bg-[#3B82F6] group-hover:border-[#3B82F6] transition-all duration-300">
                            <ShieldCheck :size="28" />
                         </div>
                         <h3 class="text-3xl font-bold mb-6">Admin Command Center</h3>
                         <ul class="space-y-5 mb-8">
                             <li class="flex items-center gap-4 text-white/70 group-hover:text-white transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#3B82F6] group-hover:text-white transition-colors" />
                                 <span class="font-medium">Full visibility of all sales & transactions</span>
                             </li>
                             <li class="flex items-center gap-4 text-white/70 group-hover:text-white transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#3B82F6] group-hover:text-white transition-colors" />
                                 <span class="font-medium">User management & permission controls</span>
                             </li>
                             <li class="flex items-center gap-4 text-white/70 group-hover:text-white transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#3B82F6] group-hover:text-white transition-colors" />
                                 <span class="font-medium">Inventory adjustment & auditing</span>
                             </li>
                             <li class="flex items-center gap-4 text-white/70 group-hover:text-white transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#3B82F6] group-hover:text-white transition-colors" />
                                 <span class="font-medium">Financial reporting & export</span>
                             </li>
                         </ul>
                     </div>
                     <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop" alt="admin illustration" class="absolute bottom-0 right-0 w-3/4 opacity-40 rounded-tl-[2rem] border-t border-l border-white/10 shadow-2xl translate-x-12 translate-y-12 transition-transform duration-700 group-hover:translate-x-6 group-hover:translate-y-6" />
                </div>

                <!-- Cashier Card -->
                 <div class="group relative bg-white rounded-[2.5rem] p-12 overflow-hidden text-[#0E1129] min-h-[500px] flex flex-col justify-between border border-[#0E1129]/5 shadow-xl transition-all duration-500 hover:shadow-2xl hover:-translate-y-2">
                     <div class="relative z-10">
                         <div class="w-14 h-14 rounded-2xl bg-[#F3F4F6] flex items-center justify-center mb-10 text-[#0E1129] border border-[#0E1129]/5 group-hover:bg-[#10B981] group-hover:text-white transition-all duration-300">
                            <ShoppingCart :size="28" />
                         </div>
                         <h3 class="text-3xl font-bold mb-6">Cashier Terminal</h3>
                         <ul class="space-y-5 mb-8">
                             <li class="flex items-center gap-4 text-[#0E1129]/70 group-hover:text-[#0E1129] transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#10B981]" />
                                 <span class="font-medium">Distraction-free POS interface</span>
                             </li>
                             <li class="flex items-center gap-4 text-[#0E1129]/70 group-hover:text-[#0E1129] transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#10B981]" />
                                 <span class="font-medium">Barcode scanning & rapid search</span>
                             </li>
                             <li class="flex items-center gap-4 text-[#0E1129]/70 group-hover:text-[#0E1129] transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#10B981]" />
                                 <span class="font-medium">Access to own sales history only</span>
                             </li>
                             <li class="flex items-center gap-4 text-[#0E1129]/70 group-hover:text-[#0E1129] transition-colors">
                                 <CheckCircle2 :size="20" class="text-[#10B981]" />
                                 <span class="font-medium">Secure session management</span>
                             </li>
                         </ul>
                     </div>
                     <img src="https://images.unsplash.com/photo-1556740738-b6a63e27c4df?q=80&w=2070&auto=format&fit=crop" alt="cashier illustration" class="absolute bottom-0 right-0 w-3/4 opacity-80 rounded-tl-[2rem] border-t border-l border-[#0E1129]/10 shadow-2xl translate-x-12 translate-y-12 transition-transform duration-700 group-hover:translate-x-6 group-hover:translate-y-6 grayscale group-hover:grayscale-0" />
                </div>
            </div>
        </section>

        <!-- Hardware & Ecosystem -->
         <section class="py-24 bg-[#0E1129] text-white overflow-hidden relative">
             <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white/5 to-transparent opacity-50"></div>

             <div class="max-w-[1920px] mx-auto px-6 md:px-12 relative z-10">
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                     <div>
                         <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">Hardware Ready</span>
                         <h2 class="text-4xl md:text-5xl font-bold mb-6">Plug & Play Ecosystem</h2>
                         <p class="text-white/60 text-lg leading-relaxed mb-8">
                             Our system interacts seamlessly with industry standard hardware. No complex drivers or setups required.
                         </p>

                         <div class="grid grid-cols-2 gap-6">
                             <div class="p-6 rounded-2xl bg-white/5 border border-white/10">
                                 <div class="mb-4 text-[#3B82F6]"><Search :size="24" /></div>
                                 <h4 class="font-bold mb-2">Barcode Scanners</h4>
                                 <p class="text-sm text-white/50">Compatible with all USB and Bluetooth HID scanners.</p>
                             </div>
                             <div class="p-6 rounded-2xl bg-white/5 border border-white/10">
                                 <div class="mb-4 text-[#3B82F6]"><ArrowUpRight :size="24" /></div>
                                 <h4 class="font-bold mb-2">Receipt Printers</h4>
                                 <p class="text-sm text-white/50">Support for 58mm and 80mm thermal printers via RawBT.</p>
                             </div>
                         </div>
                     </div>
                     <div class="relative rounded-3xl overflow-hidden border border-white/10 shadow-2xl">
                         <img src="https://images.unsplash.com/photo-1614624532983-4ce03382d63d?q=80&w=1931&auto=format&fit=crop" alt="hardware" class="w-full h-full object-cover opacity-80" />
                          <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] to-transparent"></div>
                          <div class="absolute bottom-0 left-0 p-8">
                              <p class="font-mono text-xs text-[#3B82F6] mb-2">SYSTEM_HARDWARE_CHECK</p>
                              <div class="flex gap-2">
                                  <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                  <span class="text-xs font-bold uppercase tracking-widest">Peripheral Connection Active</span>
                              </div>
                          </div>
                     </div>
                 </div>
             </div>
         </section>

        <!-- Comprehensive Bento Grid -->
        <section id="features" class="py-16 md:py-32 px-4 md:px-12 max-w-[1920px] mx-auto relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-end mb-20 gap-8">
                <div>
                    <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">Core Modules</span>
                    <h2 class="text-4xl md:text-6xl font-bold text-[#0E1129]">Everything you need to <br> scale operations.</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 grid-rows-2 gap-6 h-auto md:h-[800px]">
                <!-- Large Card : Inventory -->
                <div class="md:col-span-2 md:row-span-2 group relative bg-[#F8F9FA]/90 rounded-[2rem] overflow-hidden cursor-pointer border border-[#0E1129]/5">
                    <img src="https://images.unsplash.com/photo-1586880244406-556ebe35f282?q=80&w=1974&auto=format&fit=crop" alt="inventory" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] via-[#0E1129]/40 to-transparent p-10 flex flex-col justify-end text-white">
                        <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center mb-6">
                            <Menu :size="24" />
                        </div>
                        <h3 class="text-3xl font-bold mb-4">Intelligent Inventory</h3>
                        <p class="text-white/80 text-sm leading-relaxed mb-6 max-w-md">
                            Never run out of stock again. Automatic stock deduction, visual stock alerts, and bulk product management make handling thousands of SKUs effortless.
                        </p>
                         <div class="flex gap-3">
                             <span class="px-3 py-1 rounded-full bg-white/10 border border-white/20 text-[10px] font-bold uppercase tracking-widest">Bulk Import</span>
                             <span class="px-3 py-1 rounded-full bg-white/10 border border-white/20 text-[10px] font-bold uppercase tracking-widest">Barcode Gen</span>
                         </div>
                    </div>
                </div>

                <!-- Medium Card : Analytics -->
                <div class="md:col-span-2 group relative bg-[#F8F9FA] rounded-[2rem] overflow-hidden cursor-pointer border border-[#0E1129]/5">
                     <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop" alt="analytics" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                    <div class="absolute inset-0 bg-gradient-to-l from-[#0E1129] via-[#0E1129]/80 to-transparent p-10 flex flex-col justify-center items-end text-right text-white">
                         <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center mb-6">
                            <BarChart3 :size="24" />
                        </div>
                        <h3 class="text-2xl font-bold mb-2">Real-time Analytics</h3>
                         <p class="text-white/70 text-sm leading-relaxed mb-6 max-w-sm">
                            Visualize sales performance, revenue trends, and cashier productivity in real-time.
                        </p>
                    </div>
                </div>

                <!-- Small Card : Accounts -->
                <div class="md:col-span-1 group relative bg-[#0E1129] rounded-[2rem] overflow-hidden cursor-pointer p-8 flex flex-col justify-between">
                     <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center text-white mb-4">
                        <Users :size="20" />
                     </div>
                     <div>
                         <h3 class="text-xl font-bold text-white mb-2">Staff Accounts</h3>
                         <p class="text-white/50 text-xs">Secure login access for every employee.</p>
                     </div>
                </div>

                <!-- Small Card : Support -->
                 <div class="md:col-span-1 group relative bg-white border border-[#0E1129]/10 rounded-[2rem] overflow-hidden cursor-pointer p-8 flex flex-col justify-between hover:bg-[#F8F9FA] transition-colors">
                     <div class="w-10 h-10 bg-[#0E1129]/5 rounded-lg flex items-center justify-center text-[#0E1129] mb-4">
                        <Zap :size="20" />
                     </div>
                     <div>
                         <h3 class="text-xl font-bold text-[#0E1129] mb-2">Fast Setup</h3>
                         <p class="text-[#0E1129]/50 text-xs">Up and running in less than 5 minutes.</p>
                     </div>
                </div>
            </div>
        </section>

        <!-- Subscription Plans Section with Background -->
        <section id="plans" class="py-20 px-4 md:px-12 relative z-10 overflow-hidden">
            <!-- More Visible Background Image -->
            <div class="absolute inset-0 z-0">
                <img
                    src="https://images.unsplash.com/photo-1557804506-669a67965ba0?q=80&w=2074&auto=format&fit=crop"
                    alt="Plans Background"
                    class="w-full h-full object-cover opacity-40"
                />
                <div class="absolute inset-0 bg-gradient-to-b from-white/80 via-white/75 to-white/80"></div>
            </div>

            <div class="max-w-[80%] mx-auto relative z-10">
                <!-- Compact Section Header -->
                <div class="text-center mb-16">
                    <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">Pricing Plans</span>
                    <h2 class="text-4xl md:text-5xl font-bold text-[#0E1129] mb-4">Choose Your Plan</h2>
                    <p class="text-[#0E1129]/60 text-base max-w-2xl mx-auto">
                        Transparent pricing. Scale as you grow.
                    </p>
                </div>

                <!-- Loading State -->
                <div v-if="loadingPlans" class="flex justify-center items-center py-12">
                    <div class="w-12 h-12 border-3 border-[#3B82F6]/20 border-t-[#3B82F6] rounded-full animate-spin"></div>
                </div>

                <!-- Compact Plans Grid with More Dimmed Cards & Larger Content -->
                <div v-else-if="plans.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="[
                            'group relative bg-white/75 backdrop-blur-md border-2 rounded-2xl p-7 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:bg-white/95 flex flex-col',
                            plan.is_popular
                                ? 'border-[#3B82F6] shadow-lg shadow-[#3B82F6]/10 scale-[1.02] bg-white/85'
                                : 'border-[#0E1129]/10 hover:border-[#3B82F6]/30'
                        ]"
                    >
                        <!-- Popular Badge -->
                        <div
                            v-if="plan.is_popular"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-[#3B82F6] text-white text-xs font-bold uppercase tracking-wider rounded-full"
                        >
                            Popular
                        </div>

                        <!-- Plan Icon -->
                        <div class="mb-5">
                            <div :class="[
                                'w-14 h-14 rounded-xl flex items-center justify-center transition-all duration-300',
                                plan.is_popular
                                    ? 'bg-[#3B82F6] text-white'
                                    : 'bg-[#F8F9FA] text-[#0E1129] group-hover:bg-[#3B82F6] group-hover:text-white'
                            ]">
                                <Crown v-if="plan.is_popular" :size="26" />
                                <Rocket v-else :size="26" />
                            </div>
                        </div>

                        <!-- Plan Name & Description -->
                        <div class="mb-5">
                            <h3 class="text-2xl font-bold text-[#0E1129] mb-3">{{ plan.name }}</h3>
                            <p class="text-[#0E1129]/60 text-sm leading-relaxed">{{ plan.description }}</p>
                        </div>

                        <!-- Compact Pricing -->
                        <div class="mb-5 pb-5 border-b border-[#0E1129]/10">
                            <div class="flex items-baseline gap-1 mb-2">
                                <span class="text-4xl font-black text-[#0E1129]">{{ plan.currency }} {{ plan.price_monthly.toLocaleString() }}</span>
                                <span class="text-[#0E1129]/50 text-sm font-medium">/mo</span>
                            </div>
                            <div class="text-sm text-[#0E1129]/60">
                                or {{ plan.currency }} {{ plan.price_yearly.toLocaleString() }}/year
                                <span class="text-[#10B981] font-bold ml-1">
                                    ({{ Math.round((1 - (plan.price_yearly / (plan.price_monthly * 12))) * 100) }}% off)
                                </span>
                            </div>
                        </div>

                        <!-- Compact Features List -->
                        <div class="mb-7 flex-grow">
                            <ul class="space-y-3">
                                <li
                                    v-for="feature in plan.features.slice(0, 5)"
                                    :key="feature.id"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <CheckCircle2 :size="16" :class="plan.is_popular ? 'text-[#3B82F6]' : 'text-[#10B981]'" class="flex-shrink-0 mt-0.5" />
                                    <span class="font-medium text-[#0E1129]">{{ feature.name }}</span>
                                </li>
                                <li v-if="plan.features.length > 5" class="text-sm text-[#0E1129]/50 font-medium pl-6">
                                    + {{ plan.features.length - 5 }} more features
                                </li>
                            </ul>
                        </div>

                        <!-- Compact Subscribe Button -->
                        <button
                            @click="subscribe(plan)"
                            :class="[
                                'w-full py-3.5 px-4 rounded-xl font-bold text-sm uppercase tracking-wider transition-all duration-300 flex items-center justify-center gap-2',
                                plan.is_popular
                                    ? 'bg-[#3B82F6] text-white hover:bg-[#0E1129] shadow-md'
                                    : 'bg-[#0E1129] text-white hover:bg-[#3B82F6]'
                            ]"
                        >
                            <span>Get Started</span>
                            <ArrowRight :size="16" class="group-hover:translate-x-1 transition-transform" />
                        </button>
                    </div>
                </div>

                <!-- No Plans Message -->
                <div v-else class="text-center py-16">
                    <div class="w-16 h-16 bg-[#F8F9FA] rounded-2xl flex items-center justify-center mx-auto mb-4 border border-[#0E1129]/10">
                        <Crown :size="32" class="text-[#0E1129]/40" />
                    </div>
                    <h3 class="text-lg font-bold text-[#0E1129] mb-2">No Plans Available</h3>
                    <p class="text-[#0E1129]/60 text-sm">Please check back later or contact support.</p>
                </div>

                <!-- Compact Trust Indicators -->
                <div class="mt-16 pt-12 border-t border-[#0E1129]/10">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-[#10B981]/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                                <CheckCircle2 :size="24" class="text-[#10B981]" />
                            </div>
                            <h4 class="font-bold text-[#0E1129] mb-2 text-sm">Secure Payment</h4>
                            <p class="text-xs text-[#0E1129]/60 leading-relaxed">M-PESA integration for safe transactions</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-[#3B82F6]/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                                <ShieldCheck :size="24" class="text-[#3B82F6]" />
                            </div>
                            <h4 class="font-bold text-[#0E1129] mb-2 text-sm">Instant Activation</h4>
                            <p class="text-xs text-[#0E1129]/60 leading-relaxed">Start using features immediately</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-[#F59E0B]/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                                <Users :size="24" class="text-[#F59E0B]" />
                            </div>
                            <h4 class="font-bold text-[#0E1129] mb-2 text-sm">24/7 Support</h4>
                            <p class="text-xs text-[#0E1129]/60 leading-relaxed">Get help whenever you need it</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Demo Section -->
        <section id="demo" class="py-16 md:py-32 px-4 md:px-12 relative z-10 bg-white overflow-hidden">
            <div class="max-w-[1400px] mx-auto relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 md:gap-16 items-center">
                    <!-- Demo Content -->
                    <div>
                        <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">See It In Action</span>
                        <h2 class="text-4xl md:text-6xl font-bold text-[#0E1129] mb-6">
                            Experience ModernPOS<br>in real-time
                        </h2>
                        <p class="text-[#0E1129]/60 text-lg mb-8 leading-relaxed">
                            Book a personalized demo with our team and discover how ModernPOS can transform your retail operations. See all features in action and get answers to your questions.
                        </p>

                        <!-- Demo Features -->
                        <div class="space-y-4 mb-10">
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-[#10B981] flex items-center justify-center flex-shrink-0 mt-1">
                                    <CheckCircle2 :size="14" class="text-white" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#0E1129] mb-1">Live Product Demo</h4>
                                    <p class="text-[#0E1129]/60 text-sm">See the complete POS system in action with real scenarios</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-[#10B981] flex items-center justify-center flex-shrink-0 mt-1">
                                    <CheckCircle2 :size="14" class="text-white" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#0E1129] mb-1">Personalized Consultation</h4>
                                    <p class="text-[#0E1129]/60 text-sm">Get expert advice tailored to your business needs</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-[#10B981] flex items-center justify-center flex-shrink-0 mt-1">
                                    <CheckCircle2 :size="14" class="text-white" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#0E1129] mb-1">Q&A Session</h4>
                                    <p class="text-[#0E1129]/60 text-sm">Ask anything about features, pricing, or implementation</p>
                                </div>
                            </div>
                        </div>

                        <!-- Demo CTA -->
                        <a
                            href="tel:+254759814390"
                            class="inline-flex items-center gap-3 px-8 py-4 bg-[#3B82F6] text-white font-bold text-sm uppercase tracking-wider rounded-xl hover:bg-[#0E1129] transition-all duration-300 shadow-lg"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span>Call for Demo: +254 759 814 390</span>
                        </a>
                    </div>

                    <!-- Demo Image/Video Placeholder -->
                    <div class="relative">
                        <div class="relative rounded-3xl overflow-hidden shadow-2xl border border-[#0E1129]/10">
                            <img
                                src="https://images.unsplash.com/photo-1556155092-490a1ba16284?q=80&w=2070&auto=format&fit=crop"
                                alt="POS Demo"
                                class="w-full h-auto"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129]/60 to-transparent flex items-center justify-center">
                                <div class="w-20 h-20 bg-white/90 rounded-full flex items-center justify-center cursor-pointer hover:bg-white transition-all hover:scale-110 duration-300 group">
                                    <svg class="w-10 h-10 text-[#3B82F6] ml-1 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <!-- Floating Stats -->
                        <div class="absolute -bottom-6 -left-6 bg-white p-6 rounded-2xl shadow-xl border border-[#0E1129]/10">
                            <div class="text-3xl font-black text-[#3B82F6] mb-1">500+</div>
                            <div class="text-sm font-bold text-[#0E1129]">Happy Businesses</div>
                        </div>
                        <div class="absolute -top-6 -right-6 bg-white p-6 rounded-2xl shadow-xl border border-[#0E1129]/10">
                            <div class="text-3xl font-black text-[#10B981] mb-1">99.9%</div>
                            <div class="text-sm font-bold text-[#0E1129]">Uptime SLA</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Enhanced Contact Section -->
        <footer id="contact" class="bg-[#0E1129] text-white py-16 md:py-24 px-4 md:px-12 border-t border-white/10 relative overflow-hidden">
            <!-- Background Effects -->
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white/5 to-transparent opacity-50"></div>

            <div class="max-w-[1400px] mx-auto relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 md:gap-16">
                    <!-- Contact Info -->
                    <div>
                        <span class="text-[#3B82F6] font-bold tracking-[0.2em] uppercase text-xs mb-4 block">Get In Touch</span>
                        <h2 class="text-4xl md:text-5xl font-bold mb-8 leading-tight">
                            Ready to transform<br>your business?
                        </h2>
                        <p class="text-white/70 text-lg mb-12 leading-relaxed">
                            Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                        </p>

                        <!-- Contact Details -->
                        <div class="space-y-6">
                            <!-- Phone -->
                            <a href="tel:+254759814390" class="flex items-center gap-4 group">
                                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-[#3B82F6] transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white/50 text-xs font-medium uppercase tracking-wider">Call Us</div>
                                    <div class="text-white text-lg font-bold group-hover:text-[#3B82F6] transition-colors">+254 759 814 390</div>
                                </div>
                            </a>

                            <!-- Email -->
                            <a href="mailto:info@doitrix.co.ke" class="flex items-center gap-4 group">
                                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-[#3B82F6] transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white/50 text-xs font-medium uppercase tracking-wider">Email Us</div>
                                    <div class="text-white text-lg font-bold group-hover:text-[#3B82F6] transition-colors">info@doitrix.co.ke</div>
                                </div>
                            </a>

                            <!-- Website -->
                            <a href="https://www.doitrixtech.co.ke" target="_blank" rel="noopener noreferrer" class="flex items-center gap-4 group">
                                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-[#3B82F6] transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white/50 text-xs font-medium uppercase tracking-wider">Visit Website</div>
                                    <div class="text-white text-lg font-bold group-hover:text-[#3B82F6] transition-colors">www.doitrixtech.co.ke</div>
                                </div>
                            </a>

                            <!-- Location -->
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white/50 text-xs font-medium uppercase tracking-wider">Location</div>
                                    <div class="text-white text-lg font-bold">Nairobi, Kenya</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Form -->
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 md:p-8">
                        <h3 class="text-xl md:text-2xl font-bold mb-4 md:mb-6">Send us a message</h3>

                        <!-- Success/Error Message -->
                        <div v-if="formMessage" :class="[
                            'mb-6 p-4 rounded-xl text-sm font-medium',
                            formMessage.type === 'success' ? 'bg-[#10B981]/20 text-[#10B981] border border-[#10B981]/30' : 'bg-red-500/20 text-red-400 border border-red-500/30'
                        ]">
                            {{ formMessage.text }}
                        </div>

                        <form @submit.prevent="submitContactForm" class="space-y-5">
                            <!-- Email Input -->
                            <div>
                                <label for="email" class="block text-sm font-bold mb-2 text-white/90">Email</label>
                                <input
                                    v-model="contactForm.email"
                                    type="email"
                                    id="email"
                                    required
                                    placeholder="your@email.com"
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:border-[#3B82F6] focus:ring-2 focus:ring-[#3B82F6]/20 transition-all"
                                />
                            </div>

                            <!-- Subject Input -->
                            <div>
                                <label for="subject" class="block text-sm font-bold mb-2 text-white/90">Subject</label>
                                <input
                                    v-model="contactForm.subject"
                                    type="text"
                                    id="subject"
                                    required
                                    placeholder="How can we help?"
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:border-[#3B82F6] focus:ring-2 focus:ring-[#3B82F6]/20 transition-all"
                                />
                            </div>

                            <!-- Message Input -->
                            <div>
                                <label for="message" class="block text-sm font-bold mb-2 text-white/90">Message</label>
                                <textarea
                                    v-model="contactForm.message"
                                    id="message"
                                    required
                                    rows="5"
                                    placeholder="Tell us more about your needs..."
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:border-[#3B82F6] focus:ring-2 focus:ring-[#3B82F6]/20 transition-all resize-none"
                                ></textarea>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="isSubmitting"
                                class="w-full py-4 px-6 bg-[#3B82F6] text-white font-bold text-sm uppercase tracking-wider rounded-xl hover:bg-white hover:text-[#0E1129] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <span v-if="isSubmitting">Sending...</span>
                                <span v-else>Send Message</span>
                                <ArrowRight v-if="!isSubmitting" :size="18" />
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Footer Bottom -->
                <div class="mt-20 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-6">
                    <div class="text-white/40 text-sm">
                        &copy; 2026 ModernPOS by Doitix Tech Labs. All rights reserved.
                    </div>
                    <div class="flex items-center gap-6">
                        <Link href="#features" class="text-white/60 hover:text-white text-sm transition-colors">Features</Link>
                        <Link href="#plans" class="text-white/60 hover:text-white text-sm transition-colors">Plans</Link>
                        <Link href="#about" class="text-white/60 hover:text-white text-sm transition-colors">About</Link>
                        <Link :href="login()" class="text-white/60 hover:text-white text-sm transition-colors">Login</Link>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Subscription Modal -->
        <SubscriptionModal
            v-if="selectedPlan"
            :plan="selectedPlan"
            :open="showModal"
            @close="showModal = false"
            @success="handleSuccess"
        />
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

html {
    scroll-behavior: smooth;
}

.font-inter {
    font-family: 'Inter', sans-serif;
}

@keyframes marquee {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.animate-marquee {
    display: flex;
    white-space: nowrap;
    animation: marquee 30s linear infinite;
}

@keyframes blob {
    0% { transform: translate(0px, 0px) scale(1); }
    33% { transform: translate(30px, -50px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
    100% { transform: translate(0px, 0px) scale(1); }
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
</style>
