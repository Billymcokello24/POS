<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    Mail,
    Send,
    ArrowLeft,
    Users,
    Users2,
    ShieldAlert,
    CheckCircle2,
    Info,
    Eraser
} from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AdminLayout from '@/layouts/AdminLayout.vue'


const props = defineProps<{
    selectedBusinesses: Array<{
        id: number
        name: string
        email: string
    }>
    filters: {
        ids: string[]
    }
}>()

const form = useForm({
    subject: '',
    content: '',
    recipients: props.filters.ids.length > 0 ? 'selected' : 'active',
    selected_ids: props.filters.ids
})

const submit = () => {
    form.post('/admin/bulk-email/send', {
        onSuccess: () => {
            // Success logic handled by controller redirect
        }
    })
}

const clearSelection = () => {
    form.selected_ids = []
    if (form.recipients === 'selected') {
        form.recipients = 'active'
    }
}
</script>

<template>
    <Head title="Bulk Emailing Tool" />

    <AdminLayout>
        <div class="max-w-[95%] mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/businesses" class="p-2 hover:bg-slate-100 rounded-full transition-colors bg-white shadow-sm border border-slate-100">
                        <ArrowLeft class="h-6 w-6 text-slate-500" />
                    </Link>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Bulk Emailing Tool</h1>
                        <p class="text-slate-500 text-sm font-semibold tracking-wide">Dynamic broadcasting system for business administrators.</p>
                    </div>
                </div>

                <div class="hidden lg:flex items-center gap-6 px-6 py-3 bg-white rounded-2xl border border-slate-100 shadow-sm">
                   <div class="flex flex-col items-end">
                       <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">System Status</span>
                       <span class="text-xs font-bold text-emerald-600 flex items-center gap-1.5">
                           <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                           Broadcast Engine Ready
                       </span>
                   </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Composer -->
                <div class="lg:col-span-8 space-y-6">
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50 space-y-6">
                        <div class="space-y-2">
                            <Label for="subject" class="text-xs font-black uppercase text-slate-400 tracking-widest ml-1">Email Subject Line</Label>
                            <Input
                                id="subject"
                                v-model="form.subject"
                                placeholder="e.g. Action Required: {business_name} System Update"
                                class="h-14 border-slate-100 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-bold text-lg transition-all rounded-2xl"
                                :class="{ 'border-red-500 ring-red-500/20': form.errors.subject }"
                            />
                            <p v-if="form.errors.subject" class="text-xs text-red-500 font-bold mt-1 ml-1">{{ form.errors.subject }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="content" class="text-xs font-black uppercase text-slate-400 tracking-widest ml-1">Message Body</Label>
                            <textarea
                                id="content"
                                v-model="form.content"
                                rows="18"
                                class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-6 py-5 text-base ring-offset-background placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-medium resize-none leading-relaxed"
                                placeholder="Start writing your broadcast message here..."
                                :class="{ 'border-red-500 ring-red-500/20': form.errors.content }"
                            ></textarea>
                            <p v-if="form.errors.content" class="text-xs text-red-500 font-bold mt-1 ml-1">{{ form.errors.content }}</p>
                            <div class="flex items-center gap-2 text-[11px] text-slate-400 font-semibold mt-2 ml-1">
                                <Info class="h-3.5 w-3.5 text-blue-500" />
                                <span>Placeholders are dynamically injected. Line breaks are preserved in the final email.</span>
                            </div>
                        </div>

                        <div class="pt-6">
                            <Button
                                @click="submit"
                                class="w-full h-16 bg-gradient-to-r from-blue-600 via-indigo-600 to-indigo-700 text-white font-black text-lg rounded-2xl shadow-xl shadow-blue-200 hover:shadow-2xl hover:-translate-y-0.5 transition-all active:scale-[0.98] disabled:opacity-50 disabled:translate-y-0"
                                :disabled="form.processing"
                            >
                                <Send class="h-5 w-5 mr-3" />
                                {{ form.processing ? 'ENGINE DISPATCHING...' : 'DISPATCH BROADCAST' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Targeting & Hints -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- Hints / Placeholders -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
                        <h3 class="font-bold text-slate-900 flex items-center gap-2">
                            <Info class="h-5 w-5 text-amber-500" />
                            Personalization Hints
                        </h3>
                        <p class="text-[11px] text-slate-500 font-medium leading-relaxed">
                            Use these tags in the subject or content to automatically inject business-specific data.
                        </p>
                        
                        <div class="space-y-2">
                            <div class="p-2 bg-slate-50 rounded-lg border border-slate-100 group cursor-help hover:border-blue-200 transition-colors">
                                <code class="text-[10px] font-black text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded uppercase">{business_name}</code>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">The company name (e.g. Acme Inc.)</p>
                            </div>
                            <div class="p-2 bg-slate-50 rounded-lg border border-slate-100 group cursor-help hover:border-blue-200 transition-colors">
                                <code class="text-[10px] font-black text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded uppercase">{admin_name}</code>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">The administrator's full name</p>
                            </div>
                            <div class="p-2 bg-slate-50 rounded-lg border border-slate-100 group cursor-help hover:border-blue-200 transition-colors">
                                <code class="text-[10px] font-black text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded uppercase">{business_email}</code>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">The primary contact email</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <Users class="h-5 w-5 text-blue-600" />
                            Target Audience
                        </h3>

                        <div class="space-y-3">
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer group" :class="{ 'bg-blue-50 border-blue-200': form.recipients === 'all' }">
                                <input type="radio" v-model="form.recipients" value="all" class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <p class="text-sm font-bold text-slate-900">All Businesses</p>
                                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight">Every registered entity</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer group" :class="{ 'bg-blue-50 border-blue-200': form.recipients === 'active' }">
                                <input type="radio" v-model="form.recipients" value="active" class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Active Only</p>
                                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight text-emerald-600">Operating businesses only</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer group" :class="{ 'bg-blue-50 border-blue-200': form.recipients === 'suspended' }">
                                <input type="radio" v-model="form.recipients" value="suspended" class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Suspended Only</p>
                                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight text-red-600">Restricted accounts only</p>
                                </div>
                            </label>

                            <label v-if="props.selectedBusinesses.length > 0" class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer group" :class="{ 'bg-blue-50 border-blue-200': form.recipients === 'selected' }">
                                <input type="radio" v-model="form.recipients" value="selected" class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Selected ({{ props.selectedBusinesses.length }})</p>
                                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight text-blue-600">Manual selection from table</p>
                                </div>
                            </label>
                        </div>

                        <div v-if="form.recipients === 'selected' && props.selectedBusinesses.length > 0" class="mt-6 space-y-3">
                            <div class="flex justify-between items-center">
                                <h4 class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">Recipients</h4>
                                <button @click="clearSelection" class="text-[10px] font-bold text-red-500 hover:text-red-600 underline uppercase tracking-widest">Clear</button>
                            </div>
                            <div class="max-h-48 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                                <div v-for="b in props.selectedBusinesses" :key="b.id" class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg border border-slate-100">
                                    <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-[10px] font-black text-blue-700 border border-slate-100">
                                        {{ b.name.substring(0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate">{{ b.name }}</p>
                                        <p class="text-[10px] text-slate-400 truncate">{{ b.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-amber-50 rounded-xl border border-amber-100 space-y-2">
                             <div class="flex items-center gap-2 text-amber-700">
                                <ShieldAlert class="h-4 w-4" />
                                <span class="text-xs font-bold uppercase tracking-tight">Warning</span>
                             </div>
                             <p class="text-[11px] text-amber-800 leading-relaxed font-medium">Bulk emails are sent immediately. Ensure your content is accurate and professional before dispatching.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
