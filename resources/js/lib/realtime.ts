import { Inertia } from '@inertiajs/inertia'

import axios from '@/axios'

let intervalId: number | null = null
let lastState: any = null
let failingCount = 0

export function startRealtimePolling(interval = 5000) {
  if (intervalId) return

  const fetchStatus = async () => {
    try {
      const res = await axios.get('/realtime/status', { withCredentials: true })
      const data = res.data

      if (!lastState) {
        lastState = data
        failingCount = 0
        return
      }

      // Check products
      if (data.products_last && data.products_last !== lastState.products_last) {
        console.debug('[Realtime] products changed, reloading')
        Inertia.reload({ only: ['products'] })
      }

      // Check subscriptions: only react to final (non-pending) subscription changes
      if (data.subscriptions_last_final && data.subscriptions_last_final !== lastState.subscriptions_last_final) {
        console.debug('[Realtime] subscriptions final state changed, reloading')
        Inertia.reload()
      }

      lastState = data
      failingCount = 0
    } catch (e: any) {
      // If 401 or 403, user is not authenticated - stop polling
      if (e.response?.status === 401 || e.response?.status === 403) {
        console.log('[Realtime] Not authenticated, stopping polling')
        stopRealtimePolling()
        return
      }

      failingCount += 1
      console.log('[Realtime] poll failed', e.response?.status || e.message)
      // Exponential backoff: if failures pile up, stop polling to avoid hammering
      if (failingCount > 10) {
        console.log('[Realtime] Too many failures, stopping polling')
        stopRealtimePolling()
      }
    }
  }

  // Run immediately then set interval
  fetchStatus()
  intervalId = window.setInterval(fetchStatus, interval)
}

export function stopRealtimePolling() {
  if (intervalId) {
    clearInterval(intervalId)
    intervalId = null
    lastState = null
    failingCount = 0
  }
}
