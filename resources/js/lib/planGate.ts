import { ref, computed } from 'vue'

const currentPlanId = ref<number | null>(null)

export const setPlanId = (id: number | null) => { currentPlanId.value = id === null ? null : Number(id) }
export const getPlanId = () => currentPlanId.value

const PENDING_KEY = 'pending_plan_v1'

export const setPendingPlan = (planId: number, ttlMs = 30_000) => {
  try {
    const payload = { plan_id: Number(planId), ts: Date.now(), ttl: ttlMs }
    localStorage.setItem(PENDING_KEY, JSON.stringify(payload))
  } catch (e) { /* noop */ }
}

export const getPendingPlan = (): { plan_id: number; ts: number; ttl: number } | null => {
  try {
    const raw = localStorage.getItem(PENDING_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed.ts !== 'number') return null
    const expires = (parsed.ts || 0) + (parsed.ttl || 0)
    if (Date.now() > expires) { localStorage.removeItem(PENDING_KEY); return null }
    return parsed
  } catch (e) { return null }
}

export const clearPendingPlan = () => { try { localStorage.removeItem(PENDING_KEY) } catch (e) { /* noop */ } }

export const isFeatureEnabled = (plans: any[], feature: string | number) => {
  try {
    const planId = currentPlanId.value
    if (!planId) return false
    const plan = (plans || []).find((p: any) => Number(p.id) === Number(planId))
    if (!plan) return false
    const features = plan.features || []
    return features.some((f: any) => {
      if (typeof f === 'string' || typeof f === 'number') return String(f) === String(feature)
      return String(f.id || f.name || f.title) === String(feature)
    })
  } catch (e) { return false }
}

export const usePlanGate = () => ({
  currentPlanId: computed(() => currentPlanId.value),
  isPlan: (planId: number) => computed(() => currentPlanId.value === planId),
})

