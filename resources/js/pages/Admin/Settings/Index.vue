<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import {
    Settings as SettingsIcon,
    Smartphone,
    Mail,
    ShieldCheck,
    Save,
    Loader2,
    Globe,
    Zap,
    Lock
} from 'lucide-vue-next'

import { Button } from '@/components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
    settings: {
        mpesa: Record<string, any>;
        mail: Record<string, any>;
    }
}>()

const form = useForm({
    mpesa: {
        consumer_key: props.settings.mpesa.consumer_key ?? '',
        consumer_secret: props.settings.mpesa.consumer_secret ?? '',
        shortcode: props.settings.mpesa.shortcode ?? '',
        passkey: props.settings.mpesa.passkey ?? '',
        environment: props.settings.mpesa.environment ?? 'sandbox',
        callback_url: props.settings.mpesa.callback_url ?? '',
        result_url: props.settings.mpesa.result_url ?? '',
        head_office_shortcode: props.settings.mpesa.head_office_shortcode ?? '',
        head_office_passkey: props.settings.mpesa.head_office_passkey ?? '',
        initiator_name: props.settings.mpesa.initiator_name ?? '',
        initiator_password: props.settings.mpesa.initiator_password ?? '',
        security_credential: props.settings.mpesa.security_credential ?? '',
        simulate: Boolean(props.settings.mpesa.simulate ?? false),
    },
    mail: {
        host: props.settings.mail.host ?? '',
        port: props.settings.mail.port ?? '',
        username: props.settings.mail.username ?? '',
        password: props.settings.mail.password ?? '',
        encryption: props.settings.mail.encryption ?? 'tls',
        from_address: props.settings.mail.from_address ?? '',
        from_name: props.settings.mail.from_name ?? '',
    },
})

const saveSettings = () => {
    // @ts-ignore
    const url = typeof route !== 'undefined' ? route('admin.settings.update') : '/admin/settings'
    form.put(url, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout>
        <div class="space-y-8 max-w-[90%] mx-auto pb-12">
            <!-- Header -->
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">System Settings</h1>
                <p class="text-slate-500 font-medium">Configure global platform credentials and system defaults.</p>
            </div>

            <Tabs defaultValue="mpesa" class="w-full space-y-6">
                <TabsList class="bg-slate-100 p-1.5 rounded-2xl h-auto flex flex-wrap gap-1 border border-slate-200 shadow-sm">
                    <TabsTrigger value="mpesa" class="rounded-xl px-6 py-3 data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm font-black text-[10px] uppercase tracking-widest transition-all">
                        <Smartphone class="mr-2 h-4 w-4" />
                        MPESA Gateway
                    </TabsTrigger>
                    <TabsTrigger value="mail" class="rounded-xl px-6 py-3 data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm font-black text-[10px] uppercase tracking-widest transition-all">
                        <Mail class="mr-2 h-4 w-4" />
                        Email (SMTP)
                    </TabsTrigger>
                    <TabsTrigger value="general" disabled class="rounded-xl px-6 py-3 font-black text-[10px] uppercase tracking-widest text-slate-400 opacity-50 cursor-not-allowed">
                        <Globe class="mr-2 h-4 w-4" />
                        General Config
                    </TabsTrigger>
                </TabsList>

                <!-- MPESA Tab -->
                <TabsContent value="mpesa" class="space-y-6">
                    <Card class="border-none shadow-2xl shadow-slate-200/50 rounded-3xl overflow-hidden ring-1 ring-black/5 bg-white">
                        <CardHeader class="border-b border-slate-100 bg-slate-50/50 p-8">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                                    <Smartphone class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <CardTitle class="text-xl font-black text-slate-900 tracking-tight">MPESA Integration</CardTitle>
                                    <CardDescription class="font-medium text-slate-500">Credentials for the platform-wide payment gateway.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-8 space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-left">
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Environment</Label>
                                    <Select v-model="form.mpesa.environment">
                                        <SelectTrigger class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold">
                                            <SelectValue placeholder="Select environment" />
                                        </SelectTrigger>
                                        <SelectContent class="rounded-2xl border-none shadow-xl">
                                            <SelectItem value="sandbox" class="font-bold py-3 px-4">Sandbox (Testing)</SelectItem>
                                            <SelectItem value="production" class="font-bold py-3 px-4">Production (Live)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Shortcode</Label>
                                    <Input v-model="form.mpesa.shortcode" placeholder="e.g., 174379" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Consumer Key</Label>
                                    <Input v-model="form.mpesa.consumer_key" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Consumer Secret</Label>
                                    <div class="relative">
                                        <Input v-model="form.mpesa.consumer_secret" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10" />
                                        <Lock class="absolute right-4 top-4 h-4 w-4 text-slate-300" />
                                    </div>
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Passkey (Lipa na M-Pesa)</Label>
                                    <div class="relative">
                                        <Input v-model="form.mpesa.passkey" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10" />
                                        <Lock class="absolute right-4 top-4 h-4 w-4 text-slate-300" />
                                    </div>
                                </div>

                                <div class="relative py-4 col-span-full">
                                    <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-slate-100"></span></div>
                                    <div class="relative flex justify-start text-[9px] uppercase font-black text-slate-300">
                                        <span class="bg-white pr-2">Head Office & B2C Credentials</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Head Office Shortcode</Label>
                                    <Input v-model="form.mpesa.head_office_shortcode" placeholder="e.g., 600000" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Head Office Passkey</Label>
                                    <div class="relative">
                                        <Input v-model="form.mpesa.head_office_passkey" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10" />
                                        <Lock class="absolute right-4 top-4 h-4 w-4 text-slate-300" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Initiator Name</Label>
                                    <Input v-model="form.mpesa.initiator_name" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Initiator Password</Label>
                                    <div class="relative">
                                        <Input v-model="form.mpesa.initiator_password" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10" />
                                        <Lock class="absolute right-4 top-4 h-4 w-4 text-slate-300" />
                                    </div>
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Security Credential (B2C)</Label>
                                    <textarea v-model="form.mpesa.security_credential" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 font-bold text-sm focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                                </div>

                                <div class="relative py-4 col-span-full">
                                    <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-slate-100"></span></div>
                                    <div class="relative flex justify-start text-[9px] uppercase font-black text-slate-300">
                                        <span class="bg-white pr-2">Webhooks & Simulation</span>
                                    </div>
                                </div>

                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Callback URL</Label>
                                    <Input v-model="form.mpesa.callback_url" placeholder="https://your-domain.com/api/callback" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200">
                                    <div class="space-y-0.5">
                                        <Label class="text-sm font-black text-slate-900">Enable Simulation</Label>
                                        <p class="text-xs text-slate-500 font-medium">Toggle M-Pesa C2B simulation mode</p>
                                    </div>
                                    <input type="checkbox" v-model="form.mpesa.simulate" class="h-6 w-11 rounded-full bg-slate-200 checked:bg-indigo-600 appearance-none cursor-pointer transition-all relative after:content-[''] after:absolute after:top-1 after:left-1 after:bg-white after:h-4 after:w-4 after:rounded-full after:transition-all checked:after:translate-x-5" />
                                </div>
                            </div>

                            <div class="bg-indigo-50/50 p-6 rounded-3xl flex items-start gap-4 ring-1 ring-indigo-100">
                                <ShieldCheck class="h-6 w-6 text-indigo-500 shrink-0 mt-0.5" />
                                <div class="space-y-1">
                                    <p class="text-xs font-black text-indigo-900 uppercase tracking-widest">Security Note</p>
                                    <p class="text-xs font-medium text-indigo-700 leading-relaxed">Sensitive keys are encrypted before being stored in the database. They will never be exposed in plaintext to unauthenticated users or in client-side bundles.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Mail Tab -->
                <TabsContent value="mail" class="space-y-6">
                    <Card class="border-none shadow-2xl shadow-slate-200/50 rounded-3xl overflow-hidden ring-1 ring-black/5 bg-white">
                        <CardHeader class="border-b border-slate-100 bg-slate-50/50 p-8">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                                    <Mail class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <CardTitle class="text-xl font-black text-slate-900 tracking-tight">Email Service (SMTP)</CardTitle>
                                    <CardDescription class="font-medium text-slate-500">Configure how the platform sends notifications and verification codes.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-8 space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-left">
                                <div class="space-y-2 col-span-full sm:col-span-1">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">SMTP Host</Label>
                                    <Input v-model="form.mail.host" placeholder="smtp.mailtrap.io" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full sm:col-span-1">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">SMTP Port</Label>
                                    <Input v-model="form.mail.port" placeholder="587" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Username</Label>
                                    <Input v-model="form.mail.username" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Password</Label>
                                    <div class="relative">
                                        <Input v-model="form.mail.password" type="password" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10" />
                                        <Lock class="absolute right-4 top-4 h-4 w-4 text-slate-300" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Encryption</Label>
                                    <Select v-model="form.mail.encryption">
                                        <SelectTrigger class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent class="rounded-2xl border-none shadow-xl">
                                            <SelectItem value="tls" class="font-bold py-3 px-4">TLS</SelectItem>
                                            <SelectItem value="ssl" class="font-bold py-3 px-4">SSL</SelectItem>
                                            <SelectItem value="none" class="font-bold py-3 px-4">None</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2 col-span-full md:col-span-1">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">From Name</Label>
                                    <Input v-model="form.mail.from_name" placeholder="DigiProjects Support" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                                <div class="space-y-2 col-span-full">
                                    <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">From Email Address</Label>
                                    <Input v-model="form.mail.from_address" type="email" placeholder="no-reply@digiprojects.ke" class="h-12 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Sticky Footer Action -->
            <div class="fixed bottom-8 left-1/2 -translate-x-1/2 w-full max-w-sm px-4 lg:left-auto lg:right-12 lg:translate-x-0 lg:max-w-[280px]">
                <Button @click="saveSettings" :disabled="form.processing" class="w-full h-16 bg-slate-900 hover:bg-black text-white font-black uppercase tracking-widest rounded-2xl shadow-2xl flex items-center justify-center gap-3 transition-all hover:scale-[1.05] active:scale-[0.95]">
                    <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" />
                    <Save v-else class="h-5 w-5" />
                    Apply Changes
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>
