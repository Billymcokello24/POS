import { Inertia } from '@inertiajs/inertia'

let es: EventSource | null = null
let reconnectAttempt = 0

export function startBusinessSse() {
  try {
    if (!('EventSource' in window)) return

    // Only start once
    if (es) return

    es = new EventSource('/sse/business-stream', { withCredentials: true } as any)

    es.addEventListener('open', () => {
      console.debug('[SSE] connected')
      reconnectAttempt = 0
    })

    es.addEventListener('error', (e) => {
      console.warn('[SSE] error', e)
      if (es && es.readyState === EventSource.CLOSED) {
        es.close()
        es = null
        scheduleReconnect()
      }
    })

    es.addEventListener('business:update', (ev: any) => {
      try {
        const data = typeof ev.data === 'string' ? JSON.parse(ev.data) : ev.data
        console.debug('[SSE] business:update', data)
        const current = window.location.pathname

        // Product changes: reload product list only when on products index
        if (data.type && data.type.startsWith('product') && current.startsWith('/products')) {
          Inertia.reload({ only: ['products'] })
          return
        }

        // Subscription changes: avoid reloading the subscription page for 'pending' events
        // because that can close the payment modal before the STK prompt shows. Only reload
        // for final states like 'subscription.finalized', 'subscription.approved' or errors.
        if (data.type && data.type.startsWith('subscription')) {
          const finalEvents = ['subscription.finalized', 'subscription.approved', 'subscription.cancelled', 'subscription.updated']
          if (finalEvents.includes(data.type)) {
            if (current.startsWith('/subscription') || current.startsWith('/admin/subscriptions')) {
              Inertia.reload()
              return
            }
            Inertia.reload()
            return
          }
          // For non-final subscription events (e.g., 'subscription.pending'), do not reload to avoid
          // disrupting an in-progress payment modal. Optionally a toast can be shown here.
          return
        }

        // Fallback: reload current Inertia page to reflect other types of business updates
        Inertia.reload()
      } catch (err) {
        console.error('[SSE] failed to handle message', err)
      }
    })
  } catch (e) {
    console.warn('[SSE] failed to start', e)
  }
}

function scheduleReconnect() {
  reconnectAttempt = Math.min(6, reconnectAttempt + 1)
  const delay = Math.pow(2, reconnectAttempt) * 1000
  console.debug('[SSE] reconnect scheduled in', delay)
  setTimeout(() => {
    startBusinessSse()
  }, delay)
}

export function stopBusinessSse() {
  if (es) {
    es.close()
    es = null
  }
}
