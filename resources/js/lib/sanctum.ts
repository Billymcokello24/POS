// Simple helper to ensure Laravel Sanctum's CSRF cookie is present
let _sanctumReady = false

export async function ensureSanctum(): Promise<void> {
  if (_sanctumReady) return
  try {
    // Requesting this endpoint will set the XSRF-TOKEN cookie for Sanctum
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' })
    _sanctumReady = true
  } catch {
    console.warn('ensureSanctum: failed to fetch /sanctum/csrf-cookie')
  }
}

export function readXsrftokenFromCookie(): string | null {
  const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))
  return match ? decodeURIComponent(match.split('=')[1]) : null
}

// Helper for fetch POST with proper XSRF header and credentials
export async function postJsonWithSanctum(url: string, payload: any, extraHeaders: Record<string, string> = {}) {
  await ensureSanctum()
  const xsrf = readXsrftokenFromCookie()

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...extraHeaders,
  }
  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf

  return fetch(url, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  })
}

// Optional: attach XSRF token to axios defaults and add retry on 419
export function attachSanctumToAxios(axiosInstance: any) {
  try {
    axiosInstance.defaults.withCredentials = true

    // read token from cookie and set header if available
    const xsrf = readXsrftokenFromCookie()
    if (xsrf) {
      axiosInstance.defaults.headers.common['X-XSRF-TOKEN'] = xsrf
    }

    // Also set X-CSRF-TOKEN from meta if present
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    if (meta) {
      const token = meta.getAttribute('content')
      if (token) axiosInstance.defaults.headers.common['X-CSRF-TOKEN'] = token
    }

    // Response interceptor: on 419 try to refresh cookie once and retry the request
    axiosInstance.interceptors.response.use(undefined, async (error: any) => {
      const originalRequest = error?.config
      if (!originalRequest || originalRequest.__sanctumRetry) return Promise.reject(error)

      const status = error?.response?.status
      if (status === 419) {
        try {
          await ensureSanctum()
          const newXsrf = readXsrftokenFromCookie()
          if (newXsrf) axiosInstance.defaults.headers.common['X-XSRF-TOKEN'] = newXsrf
          originalRequest.__sanctumRetry = true
          return axiosInstance(originalRequest)
        } catch {
          // fall through
        }
      }

      return Promise.reject(error)
    })
  } catch (e) {
    // ignore if axios not available or fails
    console.warn('attachSanctumToAxios failed', e)
  }
}
