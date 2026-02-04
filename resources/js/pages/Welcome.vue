<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ShoppingCart, ArrowRight, Search, Menu, ArrowUpRight, ShieldCheck, CheckCircle2, BarChart3, Users, Zap } from 'lucide-vue-next'
import { toRef } from 'vue'

import { dashboard, login } from '@/routes'
import { register as registerBusiness } from '@/routes/business'

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

     <div class="min-h-screen font-inter selection:bg-[#3B82F6] selection:text-white relative bg-[#F8F9FA]">
        <!-- Global Background Image -->
        <div class="fixed inset-0 z-0 pointer-events-none">
             <img
                :src="cms?.hero_bg_image || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?q=80&w=2069&auto=format&fit=crop'"
                alt="Background Texture"
                class="w-full h-full object-cover opacity-[0.08]"
            />
        </div>

        <!-- Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white/5 backdrop-blur-md border-b border-white/10">
            <div class="px-6 md:px-12 py-6 flex items-center justify-between max-w-[1920px] mx-auto">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-[#0E1129] text-white flex items-center justify-center rounded-lg">
                        <img v-if="cms?.media_logo_url" :src="cms?.media_logo_url" class="w-8 h-8 object-contain" alt="Logo" />
                        <ShoppingCart v-else :size="20" stroke-width="2.5" />
                    </div>
                    <span class="text-xl font-bold tracking-tight">{{ cms?.seo_site_title ? cms?.seo_site_title.split(' ')[0] : 'MODERN' }}<span class="text-[#3B82F6]">POS</span></span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-12">
                    <Link href="#features" class="text-[11px] font-bold tracking-[0.2em] uppercase hover:text-[#3B82F6] transition-colors">Features</Link>
                    <Link href="#about" class="text-[11px] font-bold tracking-[0.2em] uppercase hover:text-[#3B82F6] transition-colors">About ModernPOS</Link>
                    <Link href="#contact" class="text-[11px] font-bold tracking-[0.2em] uppercase hover:text-[#3B82F6] transition-colors">Contact Us</Link>
                </div>

                <div class="flex items-center gap-6">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="hidden md:flex items-center gap-2 text-[11px] font-bold tracking-[0.2em] uppercase hover:text-[#3B82F6]"
                    >
                        Dashboard <ArrowRight :size="14" />
                    </Link>
                    <template v-else>
                        <Link :href="registerBusiness.url()" class="hidden md:flex items-center gap-2 text-[11px] font-bold tracking-[0.2em] uppercase hover:text-[#3B82F6]">
                            Start Business
                        </Link>
                        <Link
                            :href="registerBusiness.url()"
                            class="px-8 py-3 bg-[#0E1129] text-white text-[11px] font-bold tracking-[0.2em] uppercase rounded-full hover:bg-[#3B82F6] transition-all hover:scale-95 duration-300"
                        >
                            Get Started
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center pt-20 overflow-hidden bg-[#0E1129]">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img
                    :src="cms?.hero_bg_image || 'https://images.unsplash.com/photo-1556742526-795a3eac97d5?q=80&w=2068&auto=format&fit=crop'"
                    alt="Modern Retail POS Environment"
                    class="w-full h-full object-cover opacity-100"
                />
                 <div class="absolute inset-0 bg-gradient-to-t from-[#0E1129] via-[#0E1129]/60 to-transparent"></div>
                 <!-- Animated Aura Glows -->
                 <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-[#3B82F6]/10 rounded-full blur-[120px] -translate-y-1/2 translate-x-1/2 animate-blob mix-blend-screen" aria-hidden="true"></div>
                 <div class="absolute bottom-0 left-1/4 w-[600px] h-[600px] bg-[#10B981]/5 rounded-full blur-[100px] translate-y-1/2 animate-blob animation-delay-2000 mix-blend-screen" aria-hidden="true"></div>
            </div>

            <div class="relative z-10 w-full max-w-[1920px] mx-auto px-6 md:px-12 grid grid-cols-1 lg:grid-cols-12 gap-12 items-end pb-32">
                <div class="lg:col-span-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-white/70 text-[10px] font-bold uppercase tracking-widest mb-8">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#10B981] animate-pulse"></div>
                        {{ cms?.announcement_text || 'System Status: Optimal' }}
                    </div>
                    <h1 class="text-6xl md:text-8xl lg:text-[7rem] leading-[0.9] font-extrabold text-white tracking-tight mb-8">
                        <span v-html="cms?.hero_title"></span>
                    </h1>
                    <p class="text-lg md:text-xl font-medium text-white/60 max-w-xl leading-relaxed">
                        {{ cms?.hero_subtitle }}
                    </p>
                </div>

                <!-- Glassmorphic Search / Action Card -->
                <div class="lg:col-span-4 flex justify-end">
                    <div class="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 p-8 rounded-[2rem] shadow-2xl">
                        <div class="flex items-center justify-between mb-8">
                            <span class="text-xs font-bold tracking-[0.2em] uppercase text-white">System Access</span>
                            <div class="w-2 h-2 rounded-full bg-[#10B981] animate-pulse"></div>
                        </div>

                        <div class="space-y-4">
                            <div class="p-4 rounded-xl bg-white/5 border border-white/5 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-[#3B82F6]/20 flex items-center justify-center text-[#3B82F6]">
                                    <ShoppingCart :size="20" />
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-white">Point of Sale</div>
                                    <div class="text-[10px] text-white/50 uppercase tracking-widest">Active Module</div>
                                </div>
                            </div>

                            <Link :href="registerBusiness.url()" class="flex items-center justify-between w-full bg-[#3B82F6] text-white p-4 rounded-xl group cursor-pointer hover:bg-white hover:text-[#0E1129] transition-all duration-300">
                                <span class="text-sm font-bold uppercase tracking-widest pl-2">Enter Workspace</span>
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

        <section id="about" class="py-32 px-6 md:px-12 max-w-[1920px] mx-auto bg-[#F8F9FA]/90 relative z-10">
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
        <section id="features" class="py-32 px-6 md:px-12 max-w-[1920px] mx-auto relative z-10">
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

        <!-- Footer -->
        <footer id="contact" class="bg-[#0E1129] text-white py-24 px-8 md:px-12 border-t border-white/10">
             <div class="max-w-[1800px] mx-auto flex flex-col md:flex-row justify-between gap-24">
                 <div>
                     <h4 class="text-[10px] font-bold tracking-[0.25em] uppercase mb-8 text-[#3B82F6]">Contact</h4>
                     <p class="text-2xl md:text-3xl font-bold max-w-md leading-tight mb-8">
                         Ready to modernize your retail operations?
                     </p>
                     <a href="mailto:hello@modernpos.com" class="text-xl underline decoration-white/30 underline-offset-8 hover:decoration-white transition-all">hello@modernpos.com</a>
                 </div>

                 <div class="grid grid-cols-2 gap-24">
                     <div>
                         <h4 class="text-[10px] font-bold tracking-[0.25em] uppercase mb-8 opacity-50">Sitemap</h4>
                         <ul class="space-y-4 text-sm font-medium">
                             <li><Link href="#features" class="hover:text-[#3B82F6] transition-colors">Features</Link></li>
                             <li><Link href="#about" class="hover:text-[#3B82F6] transition-colors">Access Control</Link></li>
                             <li><Link :href="login()" class="hover:text-[#3B82F6] transition-colors">Login</Link></li>
                         </ul>
                     </div>
                     <div>
                         <h4 class="text-[10px] font-bold tracking-[0.25em] uppercase mb-8 opacity-50">Social</h4>
                         <ul class="space-y-4 text-sm font-medium">
                             <li><a href="#" class="hover:text-[#3B82F6] transition-colors">Twitter</a></li>
                             <li><a href="#" class="hover:text-[#3B82F6] transition-colors">LinkedIn</a></li>
                             <li><a href="#" class="hover:text-[#3B82F6] transition-colors">Instagram</a></li>
                         </ul>
                     </div>
                 </div>
             </div>

             <div class="max-w-[1800px] mx-auto mt-24 pt-8 border-t border-white/5 flex justify-between text-[10px] uppercase tracking-widest opacity-40">
                 <span>&copy; 2026 Modern POS</span>
                 <span>Nairobi, Kenya</span>
             </div>
        </footer>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

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
