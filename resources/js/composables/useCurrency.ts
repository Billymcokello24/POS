import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useCurrency() {
  const page = usePage()

  const currency = computed(() => {
    const curr = page.props.currency
    return typeof curr === 'function' ? curr() : curr || 'USD'
  })

  const formatCurrency = (amount: number | string): string => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount
    const currencyCode = currency.value

    // Currency symbols mapping
    const symbols: Record<string, string> = {
      'USD': '$',
      'EUR': '€',
      'GBP': '£',
      'JPY': '¥',
      'CNY': '¥',
      'INR': '₹',
      'KES': 'KSh',
      'TZS': 'TSh',
      'UGX': 'USh',
      'ZAR': 'R',
      'NGN': '₦',
    }

    const symbol = symbols[currencyCode] || currencyCode + ' '

    return `${symbol}${num.toFixed(2)}`
  }

  const getCurrencySymbol = (): string => {
    const currencyCode = currency.value

    const symbols: Record<string, string> = {
      'USD': '$',
      'EUR': '€',
      'GBP': '£',
      'JPY': '¥',
      'CNY': '¥',
      'INR': '₹',
      'KES': 'KSh',
      'TZS': 'TSh',
      'UGX': 'USh',
      'ZAR': 'R',
      'NGN': '₦',
    }

    return symbols[currencyCode] || currencyCode
  }

  return {
    currency,
    formatCurrency,
    getCurrencySymbol,
  }
}

