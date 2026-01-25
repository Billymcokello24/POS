<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps({ cms: Object })

// default background used if none provided (plain string for template binding)
const defaultBg: string = props.cms?.hero_bg_image || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?q=80&w=2069&auto=format&fit=crop'

const form = useForm({
  // Hero
  hero_title: props.cms?.hero_title || '',
  hero_subtitle: props.cms?.hero_subtitle || '',
  hero_bg_image: props.cms?.hero_bg_image || '',
  announcement_text: props.cms?.announcement_text || '',
  // About
  about_title: props.cms?.about_title || '',
  about_content: props.cms?.about_content || '',
  // SEO
  seo_site_title: props.cms?.seo_site_title || '',
  seo_meta_description: props.cms?.seo_meta_description || '',
  // Media
  media_logo_url: props.cms?.media_logo_url || '',
  media_favicon_url: props.cms?.media_favicon_url || '',
  // MPESA platform defaults (nested object)
  mpesa: {
    consumer_key: props.cms?.mpesa?.consumer_key || '',
    consumer_secret: props.cms?.mpesa?.consumer_secret || '',
    shortcode: props.cms?.mpesa?.shortcode || '',
    passkey: props.cms?.mpesa?.passkey || '',
    environment: props.cms?.mpesa?.environment || 'sandbox',
    callback_url: props.cms?.mpesa?.callback_url || '',
    result_url: props.cms?.mpesa?.result_url || '',
    head_office_shortcode: props.cms?.mpesa?.head_office_shortcode || '',
    head_office_passkey: props.cms?.mpesa?.head_office_passkey || '',
    initiator_name: props.cms?.mpesa?.initiator_name || '',
    initiator_password: props.cms?.mpesa?.initiator_password || '',
    security_credential: props.cms?.mpesa?.security_credential || '',
    simulate: !!props.cms?.mpesa?.simulate,
  }
})

const page: any = usePage()
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
  // Resolve JS route helper safely; fallback to literal URL if it's not present
  let url = '/admin/cms'
  try {
    const r = (globalThis as any).route
    if (typeof r !== 'undefined') {
      url = r('admin.cms.update')
    }
  } catch {
    // keep fallback
    console.warn('route() helper not available, falling back to /admin/cms')
  }

  console.debug('Saving CMS to', url, form)

  form.put(url, {
    preserveScroll: true,
    onSuccess: () => {
      // server will set flash; watch will pick it up
    },
    onError: (errors) => {
      console.warn('CMS save errors', errors)
    }
  })
}
</script>

<template>
  <Head title="Platform CMS - Welcome Page" />

  <AdminLayout>
    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between bg-white p-6 rounded-xl shadow-sm border border-slate-100">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Welcome Page CMS</h1>
          <p class="text-slate-500 text-sm mt-1">Edit the public welcome page content for the platform. Changes apply immediately to the public welcome page.</p>
        </div>
        <div>
          <Button class="bg-slate-900 hover:bg-black text-white font-bold" @click="save">Save Changes</Button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7">
          <Card class="border-none shadow-sm bg-white">
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle class="text-lg font-bold">Hero Section</CardTitle>
                  <CardDescription>Update the hero title, subtitle and background image URL.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 gap-4">
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Hero Title (HTML allowed)</span>
                  <input v-model="form.hero_title" class="mt-2 block w-full rounded border p-3" />
                </label>

                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Hero Subtitle</span>
                  <textarea v-model="form.hero_subtitle" class="mt-2 block w-full rounded border p-3" rows="4"></textarea>
                </label>

                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Hero Background Image URL</span>
                  <input v-model="form.hero_bg_image" class="mt-2 block w-full rounded border p-3" />
                </label>

                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Announcement Text</span>
                  <input v-model="form.announcement_text" class="mt-2 block w-full rounded border p-3" />
                </label>
              </div>
            </CardContent>
          </Card>

          <Card class="border-none shadow-sm bg-white mt-6">
            <CardHeader>
              <CardTitle class="text-lg font-bold">About Section</CardTitle>
              <CardDescription>Short about blurb displayed on the welcome page.</CardDescription>
            </CardHeader>
            <CardContent>
              <label class="block">
                <span class="text-sm font-medium text-slate-700">Title</span>
                <input v-model="form.about_title" class="mt-2 block w-full rounded border p-3" />
              </label>
              <label class="block mt-4">
                <span class="text-sm font-medium text-slate-700">Content</span>
                <textarea v-model="form.about_content" class="mt-2 block w-full rounded border p-3" rows="6"></textarea>
              </label>
            </CardContent>
          </Card>

          <Card class="border-none shadow-sm bg-white mt-6">
            <CardHeader>
              <CardTitle class="text-lg font-bold">SEO & Media</CardTitle>
              <CardDescription>Site title, meta description, logo and favicon URLs.</CardDescription>
            </CardHeader>
            <CardContent>
              <label class="block">
                <span class="text-sm font-medium text-slate-700">Site Title (SEO)</span>
                <input v-model="form.seo_site_title" class="mt-2 block w-full rounded border p-3" />
              </label>
              <label class="block mt-2">
                <span class="text-sm font-medium text-slate-700">Meta Description</span>
                <textarea v-model="form.seo_meta_description" class="mt-2 block w-full rounded border p-3" rows="3"></textarea>
              </label>
              <div class="grid grid-cols-2 gap-4 mt-4">
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Logo URL</span>
                  <input v-model="form.media_logo_url" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Favicon URL</span>
                  <input v-model="form.media_favicon_url" class="mt-2 block w-full rounded border p-3" />
                </label>
              </div>
            </CardContent>
          </Card>

          <!-- MPESA Platform Defaults -->
          <Card class="border-none shadow-sm bg-white mt-6">
            <CardHeader>
              <CardTitle class="text-lg font-bold">MPESA Platform Defaults</CardTitle>
              <CardDescription>Default MPESA credentials used when a business has not provided theirs (used for subscription/payment fallbacks).</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Consumer Key</span>
                  <input v-model="form.mpesa.consumer_key" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Consumer Secret</span>
                  <input type="password" v-model="form.mpesa.consumer_secret" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Shortcode</span>
                  <input v-model="form.mpesa.shortcode" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Passkey</span>
                  <input type="password" v-model="form.mpesa.passkey" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Environment</span>
                  <select v-model="form.mpesa.environment" class="mt-2 block w-full rounded border p-3">
                    <option value="sandbox">Sandbox</option>
                    <option value="production">Production</option>
                    <option value="live">Live</option>
                  </select>
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Callback URL</span>
                  <input v-model="form.mpesa.callback_url" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Result URL</span>
                  <input v-model="form.mpesa.result_url" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Head Office Shortcode</span>
                  <input v-model="form.mpesa.head_office_shortcode" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Head Office Passkey</span>
                  <input type="password" v-model="form.mpesa.head_office_passkey" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Initiator Name</span>
                  <input v-model="form.mpesa.initiator_name" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Initiator Password</span>
                  <input type="password" v-model="form.mpesa.initiator_password" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block col-span-2">
                  <span class="text-sm font-medium text-slate-700">Security Credential</span>
                  <input type="password" v-model="form.mpesa.security_credential" class="mt-2 block w-full rounded border p-3" />
                </label>
                <label class="block col-span-2">
                </label>
                <div class="col-span-2">
                  <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.mpesa.simulate" />
                    <span class="text-sm font-medium text-slate-700">Simulate Mode</span>
                  </label>
                </div>
              </div>
            </CardContent>
          </Card>

        </div>

        <div class="lg:col-span-5">
          <Card class="border-none shadow-sm bg-white h-full sticky top-24">
            <CardHeader>
              <CardTitle class="text-lg font-bold">Live Preview</CardTitle>
              <CardDescription>Preview of the public welcome page using current form values.</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="rounded-lg overflow-hidden border">
                <div class="bg-cover bg-center p-8" :style="{ backgroundImage: `url(${form.hero_bg_image || defaultBg})` }">
                  <div class="bg-black/40 p-8 rounded-lg">
                    <h2 class="text-3xl font-extrabold text-white" v-html="form.hero_title || 'POWER YOUR <br> COMMERCE'"></h2>
                    <p class="text-white/80 mt-3">{{ form.hero_subtitle || 'The complete retail operating system.' }}</p>
                  </div>
                </div>
                <div class="p-6">
                  <h3 class="text-xl font-bold">{{ form.about_title || 'Designed for every role' }}</h3>
                  <p class="text-slate-600 mt-2">{{ form.about_content || 'Strict role-based access control ensures your data is secure.' }}</p>

                 <div class="mt-4 p-3 border rounded bg-slate-50">
                    <h4 class="font-bold text-sm">MPESA Defaults (platform)</h4>
                    <p class="text-xs text-slate-600">Shortcode: <strong>{{ form.mpesa.shortcode || '—' }}</strong></p>
                    <p class="text-xs text-slate-600">Environment: <strong>{{ form.mpesa.environment }}</strong></p>
                 </div>

                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Success Banner -->
      <div v-if="successFlash" class="fixed top-0 inset-x-0 p-4 z-50">
        <div class="bg-green-500 text-white text-center py-2 px-4 rounded-lg shadow-md">
          {{ successFlash }}
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
