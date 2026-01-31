<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import { 
    Layout, 
    Image as ImageIcon, 
    Type, 
    Settings2, 
    Eye, 
    Save, 
    Info, 
    Globe, 
    Megaphone,
    Search,
    PenLine
} from 'lucide-vue-next'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
    cms: Record<string, any>
}>()

// default background used if none provided
const defaultBg = props.cms?.hero_bg_image || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?q=80&w=2069&auto=format&fit=crop'

const form = useForm({
  hero_title: props.cms?.hero_title || '',
  hero_subtitle: props.cms?.hero_subtitle || '',
  hero_bg_image: props.cms?.hero_bg_image || '',
  announcement_text: props.cms?.announcement_text || '',
  about_title: props.cms?.about_title || '',
  about_content: props.cms?.about_content || '',
  seo_site_title: props.cms?.seo_site_title || '',
  seo_meta_description: props.cms?.seo_meta_description || '',
  media_logo_url: props.cms?.media_logo_url || '',
  media_favicon_url: props.cms?.media_favicon_url || '',
})

const page = usePage()
const successFlash = ref<string | null>(null)

watch(() => (page.props as any).flash?.success, (v: any) => {
  if (v) {
    successFlash.value = v
    setTimeout(() => (successFlash.value = null), 3500)
  }
})

onMounted(() => {
  const f = (page.props as any).flash?.success
  if (f) {
    successFlash.value = f
    setTimeout(() => (successFlash.value = null), 3500)
  }
})

function save() {
  // @ts-ignore
  let url = typeof route !== 'undefined' ? route('admin.cms.update') : '/admin/cms'
  form.put(url, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Platform CMS - Welcome Page" />

  <AdminLayout>
    <div class="space-y-8 max-w-[90%] mx-auto pb-12">
      <!-- Premium Header -->
      <div class="relative overflow-hidden bg-slate-900 rounded-[2.5rem] p-8 md:p-12 shadow-2xl border border-slate-800">
          <div class="absolute top-0 right-0 p-12 opacity-10 blur-2xl flex gap-4 pointer-events-none">
              <div class="w-32 h-32 bg-indigo-500 rounded-full"></div>
              <div class="w-48 h-48 bg-purple-500 rounded-full translate-y-12"></div>
              <div class="w-24 h-24 bg-blue-500 rounded-full -translate-x-12"></div>
          </div>

          <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
              <div class="space-y-2">
                  <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-black uppercase tracking-widest">
                      <Layout class="h-3 w-3" />
                      Frontend Experience
                  </div>
                  <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight flex items-center gap-3">
                      Welcome Page CMS
                  </h1>
                  <p class="text-slate-400 font-medium max-w-xl text-sm md:text-base leading-relaxed">
                      Craft the first impression of your platform. Update hero sections, about details, and SEO metadata in real-time.
                  </p>
              </div>

              <div class="shrink-0 flex items-center gap-3">
                  <Button 
                      @click="save" 
                      :disabled="form.processing"
                      class="h-14 px-8 bg-white hover:bg-slate-100 text-slate-900 font-black rounded-2xl shadow-xl transition-all active:scale-95 flex items-center gap-2"
                  >
                      <Save class="h-5 w-5" />
                      {{ form.processing ? 'Saving...' : 'Save Changes' }}
                  </Button>
              </div>
          </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-7 space-y-8">
          <!-- Hero Section Card -->
          <Card class="border-none shadow-2xl bg-white rounded-[2rem] overflow-hidden group">
            <CardHeader class="p-8 pb-4">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 transition-colors group-hover:bg-indigo-600 group-hover:text-white">
                  <ImageIcon class="h-6 w-6" />
                </div>
                <div>
                  <CardTitle class="text-xl font-black text-slate-900">Hero Section</CardTitle>
                  <CardDescription>Main banner and introductory content.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-8 pt-4 space-y-6">
              <div class="grid grid-cols-1 gap-6">
                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Landing Title (HTML allowed)</Label>
                  <div class="relative">
                      <Input v-model="form.hero_title" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10 focus:ring-2 focus:ring-indigo-500" placeholder="e.g. POWER YOUR COMMERCE" />
                      <Type class="absolute right-4 top-4.5 h-4 w-4 text-slate-300" />
                  </div>
                </div>

                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Hero Subtitle</Label>
                  <textarea v-model="form.hero_subtitle" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 font-bold text-sm focus:ring-2 focus:ring-indigo-500 transition-all min-h-[100px]" placeholder="Briefly describe your platform's value proposition..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1 font-black">Background Image URL</Label>
                        <div class="relative">
                            <Input v-model="form.hero_bg_image" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10 focus:ring-2 focus:ring-indigo-500" placeholder="https://..." />
                            <ImageIcon class="absolute right-4 top-4.5 h-4 w-4 text-slate-300" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Announcement Text</Label>
                        <div class="relative">
                            <Input v-model="form.announcement_text" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10 focus:ring-2 focus:ring-indigo-500" placeholder="e.g. New features available!" />
                            <Megaphone class="absolute right-4 top-4.5 h-4 w-4 text-slate-300" />
                        </div>
                    </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- About Section -->
          <Card class="border-none shadow-2xl bg-white rounded-[2rem] overflow-hidden group">
            <CardHeader class="p-8 pb-4">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600 transition-colors group-hover:bg-amber-600 group-hover:text-white">
                  <Info class="h-6 w-6" />
                </div>
                <div>
                  <CardTitle class="text-xl font-black text-slate-900">About Section</CardTitle>
                  <CardDescription>Mission statement or value overview.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-8 pt-4 space-y-6">
              <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Section Title</Label>
                  <div class="relative">
                      <Input v-model="form.about_title" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold pr-10 focus:ring-2 focus:ring-indigo-500" />
                      <PenLine class="absolute right-4 top-4.5 h-4 w-4 text-slate-300" />
                  </div>
              </div>
              <div class="space-y-2">
                <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Main Content</Label>
                <textarea v-model="form.about_content" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 font-bold text-sm focus:ring-2 focus:ring-indigo-500 transition-all min-h-[150px]"></textarea>
              </div>
            </CardContent>
          </Card>

          <!-- SEO & Media -->
          <Card class="border-none shadow-2xl bg-white rounded-[2rem] overflow-hidden group">
            <CardHeader class="p-8 pb-4">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-600 group-hover:text-white">
                  <Globe class="h-6 w-6" />
                </div>
                <div>
                  <CardTitle class="text-xl font-black text-slate-900">SEO & Identity</CardTitle>
                  <CardDescription>Search engine presence and branding.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="p-8 pt-4 space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Site Title (SEO)</Label>
                  <Input v-model="form.seo_site_title" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Meta Description</Label>
                  <textarea v-model="form.seo_meta_description" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 font-bold text-sm focus:ring-2 focus:ring-indigo-500 transition-all" rows="2"></textarea>
                </div>
                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Brand Logo URL</Label>
                  <Input v-model="form.media_logo_url" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold focus:ring-2 focus:ring-indigo-500" placeholder="https://..." />
                </div>
                <div class="space-y-2">
                  <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Favicon URL</Label>
                  <Input v-model="form.media_favicon_url" class="h-14 bg-slate-50 border-slate-200 rounded-2xl px-4 font-bold focus:ring-2 focus:ring-indigo-500" placeholder="https://..." />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="lg:col-span-5">
          <!-- Real-time Preview -->
          <div class="sticky top-24 space-y-6">
            <div class="flex items-center justify-between px-6">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <Eye class="h-5 w-5 text-indigo-500" />
                    Live Preview
                </h3>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-black uppercase tracking-widest">Real-time</span>
            </div>

            <Card class="border-none shadow-2xl bg-white rounded-[2.5rem] overflow-hidden">
                <div class="relative min-h-[400px] flex flex-col">
                    <!-- Virtual Hero -->
                    <div class="relative bg-cover bg-center p-8 min-h-[300px] transition-all duration-700" :style="{ backgroundImage: `url(${form.hero_bg_image || defaultBg})` }">
                        <div class="absolute inset-0 bg-gradient-to-br from-black/70 via-black/40 to-transparent"></div>
                        <div class="relative z-10 space-y-4">
                            <div class="inline-flex px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-[9px] text-white font-black uppercase tracking-widest border border-white/20">
                                {{ form.announcement_text || 'Premium SaaS Platform' }}
                            </div>
                            <h2 class="text-4xl md:text-5xl font-black text-white leading-tight drop-shadow-2xl" v-html="form.hero_title || 'POWER YOUR <br> COMMERCE'"></h2>
                            <p class="text-white/80 text-lg font-medium max-w-sm line-clamp-3 leading-relaxed drop-shadow-lg">
                                {{ form.hero_subtitle || 'The complete retail operating system for modern business.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Virtual Content -->
                    <div class="p-8 space-y-6 bg-white flex-1">
                        <div class="space-y-2">
                            <h4 class="text-2xl font-black text-slate-900">{{ form.about_title || 'Designed for every role' }}</h4>
                            <p class="text-slate-500 font-medium leading-relaxed italic text-sm">
                                "{{ form.about_content || 'Strict role-based access control ensures your data is secure.' }}"
                            </p>
                        </div>

                        <div class="flex items-center gap-4 pt-4 border-t border-slate-100">
                            <div v-if="form.media_logo_url" class="h-10 w-auto bg-slate-50 rounded px-3 flex items-center">
                                <img :src="form.media_logo_url" alt="Logo" class="max-h-6 w-auto grayscale" />
                            </div>
                            <div class="flex-1">
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Global SEO Title</div>
                                <div class="text-sm font-bold text-slate-900 line-clamp-1 mt-1">{{ form.seo_site_title || 'Platform Name' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <div class="bg-indigo-50/50 p-6 rounded-[2rem] flex items-start gap-4 ring-1 ring-indigo-100 border border-white shadow-sm">
                <div class="h-10 w-10 rounded-xl bg-white border border-indigo-100 flex items-center justify-center shrink-0">
                    <Settings2 class="h-5 w-5 text-indigo-500 animate-spin-slow" />
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-black text-slate-900">Synchronization Active</h4>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                        Changes made here are instantly available on your public welcome page. Use valid image URLs to ensure optimal rendering.
                    </p>
                </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Compact Toast -->
      <Transition
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="translate-y-4 opacity-0"
          enter-to-class="translate-y-0 opacity-100"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
      >
          <div v-if="successFlash" class="fixed bottom-12 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-8 py-4 rounded-3xl shadow-2xl flex items-center gap-3 z-[100] border border-slate-800 backdrop-blur-xl">
              <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
              <span class="text-sm font-black tracking-tight">{{ successFlash }}</span>
          </div>
      </Transition>
    </div>
  </AdminLayout>
</template>

<style scoped>
.animate-spin-slow {
    animation: spin 8s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
