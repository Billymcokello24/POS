<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { ShoppingCart, Scan, Trash2, CreditCard, Users, Smartphone, Banknote, Building2, Wallet } from 'lucide-vue-next'

// Get currency from page props
const page = usePage()
const currency = computed(() => {
  const curr = page.props.currency
  return typeof curr === 'function' ? curr() : curr || 'KES'  // Default to KES (Kenyan Shilling)
})

// Get CSRF token
const csrfToken = computed(() => {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
})

// Currency formatting function
const formatCurrency = (amount: number | string): string => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  const currencyCode = currency.value

  const symbols: Record<string, string> = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'INR': '₹', 'KES': 'KSh', 'TZS': 'TSh', 'UGX': 'USh', 'ZAR': 'R', 'NGN': '₦',
  }

  const symbol = symbols[currencyCode] || currencyCode + ' '
  return `${symbol}${num.toFixed(2)}`
}

const props = defineProps<{
  customers: Array<any>
}>()

const barcode = ref('')
const barcodeInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const searchResults = ref<Array<any>>([])
const showSearchResults = ref(false)
const searchContainer = ref<HTMLElement | null>(null)
const cart = ref<Array<any>>([])
const selectedCustomer = ref<number | null>(null)
const showPaymentModal = ref(false)

const payments = ref<Array<{ method: string; amount: number; reference?: string }>>([])

// Close search results when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
    showSearchResults.value = false
  }
}

// Auto-focus barcode input on mount
onMounted(() => {
  setTimeout(() => {
    const inputElement = barcodeInput.value as HTMLInputElement | null
    if (inputElement && typeof inputElement.focus === 'function') {
      inputElement.focus()
    }
  }, 100)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
const cartTotal = computed(() => {
  return cart.value.reduce((total, item) => {
    return total + (item.quantity * item.unit_price)
  }, 0)
})

const cartTax = computed(() => {
  return cart.value.reduce((total, item) => {
    if (item.tax_rate) {
      const itemTotal = item.quantity * item.unit_price
      return total + (itemTotal * (item.tax_rate / 100))
    }
    return total
  }, 0)
})

const grandTotal = computed(() => {
  return cartTotal.value + cartTax.value
})

const totalPaid = computed(() => {
  return payments.value.reduce((sum, p) => sum + p.amount, 0)
})

const changeAmount = computed(() => {
  return Math.max(0, totalPaid.value - grandTotal.value)
})

const scanBarcode = async () => {
  if (!barcode.value) return

  try {
    const response = await fetch(`/api/products/scan?barcode=${encodeURIComponent(barcode.value)}`)

    if (!response.ok) {
      // Only show alert if product truly not found (404)
      if (response.status === 404) {
        alert('Product not found!')
      }
      barcode.value = ''
      return
    }

    const product = await response.json()

    if (product && product.id) {
      addToCart(product)
      barcode.value = ''
      // Focus back on barcode input for next scan
      setTimeout(() => {
        const inputElement = barcodeInput.value as HTMLInputElement | null
        if (inputElement && typeof inputElement.focus === 'function') {
          inputElement.focus()
        }
      }, 100)
    } else {
      barcode.value = ''
    }
  } catch (error) {
    console.error('Scan error:', error)
    barcode.value = ''
  }
}

const searchProducts = async () => {
  if (!searchQuery.value) {
    searchResults.value = []
    showSearchResults.value = false
    return
  }

  try {
    const response = await fetch(`/api/products/search?q=${encodeURIComponent(searchQuery.value)}`)

    if (!response.ok) {
      searchResults.value = []
      showSearchResults.value = false
      return
    }

    const products = await response.json()
    searchResults.value = products
    showSearchResults.value = products.length > 0
  } catch (error) {
    console.error('Search failed:', error)
    searchResults.value = []
    showSearchResults.value = false
  }
}

const selectSearchResult = (product: any) => {
  addToCart(product)
  searchQuery.value = ''
  searchResults.value = []
  showSearchResults.value = false
  // Focus back on barcode input
  setTimeout(() => {
    const inputElement = barcodeInput.value as HTMLInputElement | null
    if (inputElement && typeof inputElement.focus === 'function') {
      inputElement.focus()
    }
  }, 100)
}

const addToCart = (product: any) => {
  const existingItem = cart.value.find(item => item.product_id === product.id)

  if (existingItem) {
    existingItem.quantity++
    // Show feedback
    console.log(`Increased quantity of ${product.name} to ${existingItem.quantity}`)
  } else {
    cart.value.push({
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      quantity: 1,
      unit_price: Number(product.selling_price),
      tax_rate: product.tax_configuration?.rate || 0,
      available_quantity: Number(product.quantity),
    })
    // Show feedback
    console.log(`Added ${product.name} to cart at $${Number(product.selling_price).toFixed(2)}`)
  }
}

const removeFromCart = (index: number) => {
  cart.value.splice(index, 1)
}

const updateQuantity = (index: number, quantity: number) => {
  if (quantity > 0 && quantity <= cart.value[index].available_quantity) {
    cart.value[index].quantity = quantity
  }
}

// Payment processing state
const paymentMethod = ref<string>('')
const mpesaPhone = ref('')
const mpesaTransactionCode = ref('')
const cardNumber = ref('')
const cardExpMonth = ref('')
const cardExpYear = ref('')
const cardCVV = ref('')
const cardholderName = ref('')
const bankReference = ref('')
const bankName = ref('')
const cashReceived = ref(0)
const processingPayment = ref(false)
const paymentCheckoutId = ref('')

const openPaymentModal = (method: string) => {
  paymentMethod.value = method
  showPaymentModal.value = true

  // Pre-fill amount with remaining balance
  const remaining = grandTotal.value - totalPaid.value
  if (method === 'CASH') {
    cashReceived.value = remaining
  }
}

const closePaymentModal = () => {
  showPaymentModal.value = false
  paymentMethod.value = ''
  mpesaPhone.value = ''
  mpesaTransactionCode.value = ''
  cardNumber.value = ''
  cardExpMonth.value = ''
  cardExpYear.value = ''
  cardCVV.value = ''
  cardholderName.value = ''
  bankReference.value = ''
  bankName.value = ''
  cashReceived.value = 0
  paymentCheckoutId.value = ''
}

const processPayment = async () => {
  processingPayment.value = true

  try {
    if (paymentMethod.value === 'MPESA') {
      await processMpesaSTKPush()
    } else if (paymentMethod.value === 'MPESA_TILL') {
      await processMpesaTillPayment()
    } else if (paymentMethod.value === 'CARD') {
      await processCardPayment()
    } else if (paymentMethod.value === 'BANK_TRANSFER') {
      await processBankTransfer()
    } else if (paymentMethod.value === 'CASH') {
      await processCashPayment()
    }
  } catch (error) {
    console.error('Payment error:', error)
    alert('Payment failed: ' + (error as any).message)
  } finally {
    processingPayment.value = false
  }
}

const processMpesaSTKPush = async () => {
  if (!mpesaPhone.value) {
    alert('Please enter M-Pesa phone number')
    return
  }

  const remaining = grandTotal.value - totalPaid.value

  try {
    console.log('Initiating M-Pesa STK Push...')
    console.log('Amount:', remaining)
    console.log('Phone:', mpesaPhone.value)

    const response = await fetch('/api/payments/mpesa/stk-push', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        phone_number: mpesaPhone.value,
        amount: remaining,
        account_reference: 'POS-SALE-' + Date.now()
      })
    })

    console.log('Response status:', response.status)

    if (!response.ok) {
      const errorText = await response.text()
      console.error('Response error:', errorText)

      // Try to parse as JSON
      let errorMessage = 'M-Pesa payment failed'
      let errorType = 'unknown'
      try {
        const errorData = JSON.parse(errorText)
        errorMessage = errorData.message || errorData.error || errorMessage
        errorType = errorData.error || errorType
      } catch (e) {
        // If not JSON, use the text
        errorMessage = errorText.substring(0, 200) // First 200 chars
      }

      // Show specific message for connection timeout
      if (errorType === 'Connection timeout' || errorMessage.includes('Connection to M-Pesa timed out')) {
        alert('❌ Cannot Connect to M-Pesa\n\n' +
              'Your server cannot reach Safaricom M-Pesa API.\n\n' +
              'Possible causes:\n' +
              '• Firewall blocking outbound HTTPS connections\n' +
              '• No internet connection from server\n' +
              '• Network configuration issues\n\n' +
              'Temporary Solution:\n' +
              'Use "M-Pesa Till Payment" option instead:\n' +
              '1. Customer pays to Till 3581295 directly\n' +
              '2. Get M-Pesa confirmation code\n' +
              '3. Enter code manually in system\n\n' +
              'Contact your system administrator to fix network access.')
      } else {
        alert('M-Pesa Error: ' + errorMessage)
      }

      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const result = await response.json()
    console.log('M-Pesa Response:', result)

    if (result.success) {
      paymentCheckoutId.value = result.data.checkout_request_id
      alert(result.message || 'Payment request sent! Please check your phone to complete payment.')

      // Start polling for payment status
      pollMpesaStatus(result.data.checkout_request_id, remaining)
    } else {
      alert('M-Pesa payment failed: ' + (result.message || 'Unknown error'))
    }
  } catch (error: unknown) {
    console.error('M-Pesa STK Push error:', error)

    // Provide more helpful error messages
    const errorMessage = error instanceof Error ? error.message : String(error)
    if (errorMessage.includes('Failed to fetch')) {
      alert('Network Error: Cannot reach the payment server.\n\n' +
            'Please check:\n' +
            '1. You are connected to the internet\n' +
            '2. The server is running\n' +
            '3. Try refreshing the page\n\n' +
            'If using ngrok, visit: http://127.0.0.1:8000 instead')
    } else {
      alert('Payment Error: ' + errorMessage)
    }
    throw error
  }
}

const pollMpesaStatus = (checkoutRequestId: string, amount: number) => {
  let attempts = 0
  const maxAttempts = 40 // Poll for 120 seconds (40 * 3 seconds) - extended for slow networks

  console.log('Starting M-Pesa status polling for:', checkoutRequestId)
  console.log('Will poll for up to 2 minutes...')

  // M-Pesa result codes mapping
  const mpesaErrorCodes: Record<string, string> = {
    '0': 'Success',
    '1': 'Insufficient Balance',
    '1032': 'Request cancelled by user',
    '1037': 'Transaction pending (waiting for customer)',
    '2001': 'Wrong PIN entered',
    '2002': 'Agent number and Store number mismatch',
    '2006': 'PIN blocked',
    '2007': 'SIM Toolkit menu timeout',
    '2029': 'Transaction cancelled by customer',
    '4001': 'System error',
    '4031': 'Customer MSISDN not found',
    '4032': 'Invalid Bill Reference Number',
    '4999': 'Transaction still under processing'
  }

  // Codes that mean "still processing" - don't fail, keep polling
  const pendingCodes = ['1037', '4999']

  // Codes that mean user cancelled - these are final failures
  const cancelledCodes = ['1032', '2029']

  const interval = setInterval(async () => {
    attempts++
    console.log(`Polling attempt ${attempts}/${maxAttempts}`)

    try {
      const response = await fetch('/api/payments/mpesa/check-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken.value,
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ checkout_request_id: checkoutRequestId })
      })

      if (!response.ok) {
        console.error('Status check HTTP error:', response.status)
        // Don't stop polling on HTTP errors, might be temporary
        if (attempts >= maxAttempts) {
          clearInterval(interval)
          closePaymentModal()
          alert('⏱️ Payment Verification Timeout\n\n' +
                'Could not confirm payment status after 2 minutes.\n\n' +
                'Please check your M-Pesa messages:\n' +
                '• If payment was DEDUCTED: Note the transaction code\n' +
                '• Click "M-Pesa Till Payment" to record it manually\n' +
                '• If NOT deducted: Try payment again')
        }
        return
      }

      const result = await response.json()
      console.log('Status check result:', result)

      // Check if we got a successful response
      if (!result.success) {
        console.warn('Query returned success=false:', result.message)
        // Don't fail - continue polling, query might be timing out
        if (attempts >= maxAttempts) {
          clearInterval(interval)
          closePaymentModal()
          alert('⏱️ Cannot Verify Payment Status\n\n' +
                'The system cannot check if payment completed.\n\n' +
                'This may be due to network issues.\n\n' +
                'Please check your M-Pesa messages and use "M-Pesa Till Payment" if money was deducted.')
        }
        return
      }

      // Check the result code
      const resultCode = result.data?.ResultCode

      if (resultCode === '0') {
        // Success!
        clearInterval(interval)
        console.log('✅ Payment successful!')

        // Add payment to list
        payments.value.push({
          method: 'MPESA',
          amount: amount,
          reference: result.data.MpesaReceiptNumber || checkoutRequestId
        })

        closePaymentModal()
        alert('✅ M-Pesa Payment Completed!\n\n' +
              'Receipt: ' + (result.data.MpesaReceiptNumber || checkoutRequestId) + '\n' +
              'Amount: ' + formatCurrency(amount))

      } else if (resultCode && pendingCodes.includes(resultCode.toString())) {
        // Still pending - codes like 1037, 4999, 1032 mean keep polling
        console.log(`Transaction still pending (code ${resultCode}), will check again...`)
        // Don't stop polling, continue to next iteration

      } else if (resultCode) {
        // Failed with a definitive error code
        clearInterval(interval)
        const errorDesc = result.data.ResultDesc || 'Unknown error'
        const friendlyMessage = mpesaErrorCodes[resultCode] || errorDesc

        console.error('Payment failed with code:', resultCode, '-', friendlyMessage)

        closePaymentModal()

        // Show user-friendly error message based on error code
        if (cancelledCodes.includes(resultCode.toString())) {
          const errorDesc = result.data.ResultDesc || ''

          // Check if it's actual cancellation or M-Pesa system error
          if (errorDesc.includes('unresolved reason')) {
            alert('❌ M-Pesa Error\n\n' +
                  'The payment could not be processed.\n\n' +
                  'Possible reasons:\n' +
                  '• Phone number is not registered with M-Pesa\n' +
                  '• Network connectivity issues\n' +
                  '• Phone is switched off or out of coverage\n' +
                  '• M-Pesa account has restrictions\n\n' +
                  'Please verify:\n' +
                  '1. Phone number is correct\n' +
                  '2. M-Pesa account is active\n' +
                  '3. Phone has network coverage\n\n' +
                  'Or use a different payment method.')
          } else {
            alert('❌ Payment Cancelled\n\n' +
                  'The customer cancelled the payment on their phone.\n\n' +
                  'Please try again or use a different payment method.')
          }
        } else if (resultCode === '1') {
          alert('❌ Insufficient Balance\n\n' +
                'The M-Pesa account does not have enough funds.\n\n' +
                'Please top up and try again.')
        } else if (resultCode === '2001') {
          alert('❌ Wrong PIN\n\n' +
                'Incorrect M-Pesa PIN was entered.\n\n' +
                'Please try again with the correct PIN.')
        } else if (resultCode === '2006') {
          alert('❌ PIN Blocked\n\n' +
                'The M-Pesa PIN has been blocked.\n\n' +
                'Please contact Safaricom customer service: 0722 000 000')
        } else if (resultCode === '2007') {
          alert('⏱️ Request Timed Out\n\n' +
                'Customer did not respond to the M-Pesa prompt in time.\n\n' +
                'Please try again.')
        } else {
          alert(`❌ M-Pesa Payment Failed\n\n` +
                `Error: ${friendlyMessage}\n\n` +
                `Please try again or use a different payment method.`)
        }
      } else {
        // No result code yet - continue polling
        console.log('No result code yet, continuing to poll...')
      }

      // Check if we've reached max attempts
      if (attempts >= maxAttempts) {
        clearInterval(interval)
        closePaymentModal()
        alert('⏱️ Payment Verification Timeout (2 minutes)\n\n' +
              'Could not confirm payment status.\n\n' +
              '📱 Please check your M-Pesa messages:\n\n' +
              '✓ If payment WAS deducted:\n' +
              '  • Note the transaction code (e.g., QAB2C3D4E5)\n' +
              '  • Click "M-Pesa Till Payment"\n' +
              '  • Enter the code to record payment\n\n' +
              '✗ If payment was NOT deducted:\n' +
              '  • Try payment again\n' +
              '  • Or use a different payment method')
      }

    } catch (error) {
      console.error('Status check error:', error)
      // Log but don't stop polling - might be temporary network issue
      if (attempts >= maxAttempts) {
        clearInterval(interval)
        closePaymentModal()
        alert('❌ Network Error During Verification\n\n' +
              'Could not verify payment status due to network issues.\n\n' +
              'Please check your M-Pesa messages and use "M-Pesa Till Payment" if money was deducted.')
      }
    }
  }, 3000) // Check every 3 seconds
}

const processMpesaTillPayment = async () => {
  if (!mpesaTransactionCode.value || !mpesaPhone.value) {
    alert('Please enter transaction code and phone number')
    return
  }

  const remaining = grandTotal.value - totalPaid.value

  const response = await fetch('/api/payments/mpesa/till-payment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken.value,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      transaction_code: mpesaTransactionCode.value,
      phone_number: mpesaPhone.value,
      amount: remaining
    })
  })

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`)
  }

  const result = await response.json()

  if (result.success) {
    payments.value.push({
      method: 'MPESA',
      amount: remaining,
      reference: mpesaTransactionCode.value
    })

    closePaymentModal()
    alert('M-Pesa till payment recorded successfully!')
  } else {
    alert('Failed to record payment: ' + result.message)
  }
}

const processCardPayment = async () => {
  if (!cardNumber.value || !cardExpMonth.value || !cardExpYear.value || !cardCVV.value || !cardholderName.value) {
    alert('Please fill in all card details')
    return
  }

  const remaining = grandTotal.value - totalPaid.value

  const response = await fetch('/api/payments/card', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken.value,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      card_number: cardNumber.value.replace(/\s/g, ''),
      expiry_month: parseInt(cardExpMonth.value),
      expiry_year: parseInt(cardExpYear.value),
      cvv: cardCVV.value,
      cardholder_name: cardholderName.value,
      amount: remaining
    })
  })

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`)
  }

  const result = await response.json()

  if (result.success) {
    payments.value.push({
      method: 'CARD',
      amount: remaining,
      reference: result.transaction_id
    })

    closePaymentModal()
    alert('Card payment processed successfully!')
  } else {
    alert('Card payment failed: ' + result.message)
  }
}

const processBankTransfer = async () => {
  if (!bankReference.value) {
    alert('Please enter bank transfer reference number')
    return
  }

  const remaining = grandTotal.value - totalPaid.value

  const response = await fetch('/api/payments/bank-transfer', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken.value,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      reference_number: bankReference.value,
      amount: remaining,
      bank_name: bankName.value || null
    })
  })

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`)
  }

  const result = await response.json()

  if (result.success) {
    payments.value.push({
      method: 'BANK_TRANSFER',
      amount: remaining,
      reference: bankReference.value
    })

    closePaymentModal()
    alert('Bank transfer recorded successfully!')
  } else {
    alert('Failed to record transfer: ' + result.message)
  }
}

const processCashPayment = async () => {
  const remaining = grandTotal.value - totalPaid.value

  if (cashReceived.value < remaining) {
    alert(`Insufficient cash! Need ${formatCurrency(remaining - cashReceived.value)} more.`)
    return
  }

  try {
    const response = await fetch('/api/payments/cash', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value,
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        amount: remaining,
        received_amount: cashReceived.value
      })
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error('Cash payment error:', errorText)
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const result = await response.json()

    if (result.success) {
      payments.value.push({
        method: 'CASH',
        amount: remaining,
        reference: 'CASH'
      })

      closePaymentModal()

      if (result.change > 0) {
        alert(`Cash payment recorded! Change: ${formatCurrency(result.change)}`)
      } else {
        alert('Cash payment recorded successfully!')
      }
    } else {
      alert('Failed to record payment: ' + result.message)
    }
  } catch (error) {
    console.error('Cash payment error:', error)
    throw error
  }
}

const removePayment = (index: number) => {
  payments.value.splice(index, 1)
}

const completeSale = async () => {
  if (cart.value.length === 0) {
    alert('Cart is empty! Add items before completing sale.')
    return
  }

  if (payments.value.length === 0) {
    alert('Please add at least one payment method!')
    return
  }

  if (totalPaid.value < grandTotal.value) {
    alert(`Insufficient payment! Need $${(grandTotal.value - totalPaid.value).toFixed(2)} more.`)
    return
  }

  const form = useForm({
    customer_id: selectedCustomer.value,
    items: cart.value.map(item => ({
      product_id: item.product_id,
      quantity: item.quantity,
      unit_price: item.unit_price,
      discount_amount: 0,
    })),
    payments: payments.value.map(p => ({
      payment_method: p.method,
      amount: p.amount,
      reference_number: p.reference || null,
    })),
    discount_amount: 0,
  })

  form.post('/sales', {
    preserveScroll: true,
    onSuccess: (page: any) => {
      console.log('Full page response:', page)
      console.log('Page props:', page.props)
      console.log('Flash data:', page.props?.flash)

      // Get sale ID from flash data - handle both function and value
      let saleId = page.props?.flash?.saleId

      // If saleId is a function, call it
      if (typeof saleId === 'function') {
        saleId = saleId()
      }

      console.log('Extracted sale ID:', saleId)

      // Clear the cart and reset form
      cart.value = []
      payments.value = []
      selectedCustomer.value = null
      showPaymentModal.value = false

      // Open receipt if sale ID is available
      if (saleId) {
        const receiptUrl = `/sales/${saleId}/receipt`
        console.log('Opening receipt at:', receiptUrl)

        // Open in new window
        const receiptWindow = window.open(receiptUrl, '_blank')

        if (receiptWindow) {
          console.log('Receipt window opened successfully')
        } else {
          console.error('Failed to open receipt window - popup might be blocked')
          alert('Receipt window blocked! Please allow popups and try again.')
        }

        // Also show success message
        setTimeout(() => {
          alert('Sale completed successfully! Receipt opened in new tab.')
        }, 500)
      } else {
        console.error('No sale ID received in flash data')
        alert('Sale completed but receipt could not be generated. Sale ID missing.')
      }

      // Focus back on barcode input for next sale
      setTimeout(() => {
        const inputElement = barcodeInput.value as HTMLInputElement | null
        if (inputElement && typeof inputElement.focus === 'function') {
          inputElement.focus()
        }
      }, 1000)
    },
    onError: (errors: any) => {
      console.error('Sale failed:', errors)
      alert('Failed to complete sale: ' + JSON.stringify(errors))
    },
  })
}
</script>

<template>
  <AppLayout title="Point of Sale">
    <div class="min-h-screen bg-gradient-to-br from-cyan-50 via-blue-50 to-indigo-50 p-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Colorful Header -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 p-8 text-white shadow-2xl">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
              <div class="rounded-xl bg-white/20 backdrop-blur p-3">
                <ShoppingCart class="h-8 w-8" />
              </div>
              <div>
                <h1 class="text-4xl font-bold">Point of Sale</h1>
                <p class="text-cyan-100 text-lg mt-1">Fast & Easy Checkout Terminal</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <!-- Product Search & Cart -->
          <div class="lg:col-span-2 space-y-4">
            <!-- Barcode Scanner -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-cyan-50 to-blue-50">
                <CardTitle class="flex items-center gap-2 text-xl">
                  <div class="rounded-lg bg-cyan-100 p-2">
                    <Scan class="h-5 w-5 text-cyan-600" />
                  </div>
                  Quick Scan
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <div class="flex gap-2">
                  <Input
                    ref="barcodeInput"
                    v-model="barcode"
                    placeholder="🔍 Scan or enter barcode..."
                    @keyup.enter="scanBarcode"
                    autofocus
                    class="flex-1 h-12 border-2 focus:border-cyan-500"
                  />
                  <Button
                    @click="scanBarcode"
                    class="h-12 px-6 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700"
                  >
                    <Scan class="h-5 w-5 mr-2" />
                    Scan
                  </Button>
                </div>
              </CardContent>
            </Card>

            <!-- Product Search -->
            <Card class="border-0 shadow-xl bg-white">
              <CardContent class="pt-6">
                <div ref="searchContainer" class="relative">
                  <div class="flex gap-2">
                    <Input
                      v-model="searchQuery"
                      placeholder="🔎 Search products by name or SKU..."
                      @keyup="searchProducts"
                      @keyup.enter="searchProducts"
                      class="flex-1 h-12 border-2 focus:border-blue-500"
                    />
                    <Button
                      @click="searchProducts"
                      class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                    >
                      Search
                    </Button>
                  </div>

                  <!-- Search Results Dropdown -->
                  <div
                    v-if="showSearchResults && searchResults.length > 0"
                    class="absolute top-full left-0 right-0 mt-2 bg-white border-2 border-blue-200 rounded-xl shadow-2xl z-50 max-h-96 overflow-y-auto"
                  >
                    <div
                      v-for="product in searchResults"
                      :key="product.id"
                      @click="selectSearchResult(product)"
                      class="p-4 hover:bg-blue-50 cursor-pointer border-b border-gray-100 transition-colors"
                    >
                      <div class="flex items-center justify-between">
                        <div class="flex-1">
                          <div class="font-semibold text-lg text-slate-900">{{ product.name }}</div>
                          <div class="text-sm text-slate-600 mt-1">
                            <Badge variant="outline" class="mr-2">{{ product.sku }}</Badge>
                            <span v-if="product.barcode" class="text-xs">Barcode: {{ product.barcode }}</span>
                          </div>
                        </div>
                        <div class="text-right ml-4">
                          <div class="text-xl font-bold text-blue-600">{{ formatCurrency(product.selling_price) }}</div>
                          <div class="text-xs text-slate-500">
                            Stock: {{ product.quantity }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- No Results Message -->
                  <div
                    v-if="showSearchResults && searchResults.length === 0 && searchQuery"
                    class="absolute top-full left-0 right-0 mt-2 bg-white border-2 border-gray-200 rounded-xl shadow-xl p-4 text-center text-gray-500 z-50"
                  >
                    <p>No products found for "{{ searchQuery }}"</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Cart -->
            <Card class="border-0 shadow-2xl bg-white">
              <CardHeader class="border-b bg-gradient-to-r from-blue-50 to-indigo-50">
                <CardTitle class="flex items-center gap-2 text-2xl">
                  <div class="rounded-lg bg-blue-100 p-2">
                    <ShoppingCart class="h-6 w-6 text-blue-600" />
                  </div>
                  Shopping Cart
                  <Badge class="ml-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white">
                    {{ cart.length }} items
                  </Badge>
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <div v-if="cart.length === 0" class="py-12 text-center">
                  <ShoppingCart class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                  <p class="text-gray-500 text-lg">Cart is empty</p>
                  <p class="text-gray-400 text-sm mt-2">Scan or search for products to add</p>
                </div>
                <div v-else class="space-y-3">
                  <div
                    v-for="(item, index) in cart"
                    :key="index"
                    class="flex items-center justify-between rounded-xl border-2 border-blue-100 p-4 bg-gradient-to-r from-white to-blue-50 hover:border-blue-300 transition-all"
                  >
                    <div class="flex-1">
                      <div class="font-semibold text-lg text-slate-900">{{ item.name }}</div>
                      <div class="text-sm text-slate-600 mt-1">
                        <Badge variant="outline" class="mr-2">{{ item.sku }}</Badge>
                        {{ formatCurrency(item.unit_price) }} each
                      </div>
                    </div>
                    <div class="flex items-center gap-3">
                      <Input
                        type="number"
                        :model-value="item.quantity"
                        @update:model-value="(val) => updateQuantity(index, Number(val))"
                        :max="item.available_quantity"
                        min="1"
                        class="w-20 h-10 border-2 text-center font-bold"
                      />
                      <div class="w-28 text-right">
                        <div class="text-2xl font-bold text-blue-600">
                          {{ formatCurrency(item.quantity * item.unit_price) }}
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        @click="removeFromCart(index)"
                        class="hover:bg-red-100 hover:text-red-600"
                      >
                        <Trash2 class="h-5 w-5" />
                      </Button>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Order Summary & Payment -->
          <div class="space-y-4">
            <!-- Customer Selection -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-purple-50 to-pink-50">
                <CardTitle class="flex items-center gap-2">
                  <div class="rounded-lg bg-purple-100 p-2">
                    <Users class="h-5 w-5 text-purple-600" />
                  </div>
                  Customer
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6">
                <Select v-model="selectedCustomer">
                  <SelectTrigger class="h-12 border-2">
                    <SelectValue placeholder="👤 Walk-in Customer" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="null">Walk-in Customer</SelectItem>
                    <SelectItem
                      v-for="customer in customers"
                      :key="customer.id"
                      :value="customer.id"
                    >
                      {{ customer.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </CardContent>
            </Card>

            <!-- Order Summary -->
            <Card class="border-0 shadow-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
              <CardHeader>
                <CardTitle class="text-white text-xl">Order Summary</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex justify-between text-lg">
                  <span class="text-blue-100">Subtotal:</span>
                  <span class="font-semibold">{{ formatCurrency(cartTotal) }}</span>
                </div>
                <div class="flex justify-between text-lg">
                  <span class="text-blue-100">Tax:</span>
                  <span class="font-semibold">{{ formatCurrency(cartTax) }}</span>
                </div>
                <Separator class="bg-white/20" />
                <div class="flex justify-between text-2xl font-bold">
                  <span>Total:</span>
                  <span>{{ formatCurrency(grandTotal) }}</span>
                </div>
              </CardContent>
            </Card>

            <!-- Payment Methods -->
            <Card class="border-0 shadow-xl bg-white">
              <CardHeader class="bg-gradient-to-r from-green-50 to-emerald-50">
                <CardTitle class="flex items-center gap-2">
                  <div class="rounded-lg bg-green-100 p-2">
                    <CreditCard class="h-5 w-5 text-green-600" />
                  </div>
                  Payment Methods
                </CardTitle>
              </CardHeader>
              <CardContent class="pt-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <Button
                    @click="openPaymentModal('CASH')"
                    variant="outline"
                    class="h-14 border-2 hover:bg-green-50 hover:border-green-500 flex flex-col items-center gap-1"
                  >
                    <Banknote class="h-6 w-6 text-green-600" />
                    <span class="text-xs font-semibold">Cash</span>
                  </Button>
                  <Button
                    @click="openPaymentModal('MPESA')"
                    variant="outline"
                    class="h-14 border-2 hover:bg-purple-50 hover:border-purple-500 flex flex-col items-center gap-1"
                  >
                    <Smartphone class="h-6 w-6 text-purple-600" />
                    <span class="text-xs font-semibold">M-Pesa STK</span>
                  </Button>
                  <Button
                    @click="openPaymentModal('MPESA_TILL')"
                    variant="outline"
                    class="h-14 border-2 hover:bg-indigo-50 hover:border-indigo-500 flex flex-col items-center gap-1"
                  >
                    <Wallet class="h-6 w-6 text-indigo-600" />
                    <span class="text-xs font-semibold">M-Pesa Till</span>
                  </Button>
                  <Button
                    @click="openPaymentModal('CARD')"
                    variant="outline"
                    class="h-14 border-2 hover:bg-blue-50 hover:border-blue-500 flex flex-col items-center gap-1"
                  >
                    <CreditCard class="h-6 w-6 text-blue-600" />
                    <span class="text-xs font-semibold">Card</span>
                  </Button>
                  <Button
                    @click="openPaymentModal('BANK_TRANSFER')"
                    variant="outline"
                    class="h-14 border-2 hover:bg-orange-50 hover:border-orange-500 flex flex-col items-center gap-1 col-span-2"
                  >
                    <Building2 class="h-6 w-6 text-orange-600" />
                    <span class="text-xs font-semibold">Bank Transfer</span>
                  </Button>
                </div>

                <div v-if="payments.length > 0" class="space-y-3">
                  <Separator />
                  <div
                    v-for="(payment, index) in payments"
                    :key="index"
                    class="flex items-center justify-between p-3 bg-slate-50 rounded-lg"
                  >
                    <Badge class="bg-blue-100 text-blue-800">{{ payment.method }}</Badge>
                    <div class="flex items-center gap-2">
                      <Input
                        v-model.number="payment.amount"
                        type="number"
                        step="0.01"
                        class="w-28 h-10 text-right font-bold border-2"
                      />
                      <Button
                        variant="ghost"
                        size="icon"
                        @click="removePayment(index)"
                        class="hover:bg-red-100"
                      >
                        <Trash2 class="h-4 w-4 text-red-600" />
                      </Button>
                    </div>
                  </div>
                  <Separator />
                  <div class="flex justify-between font-semibold text-lg">
                    <span>Paid:</span>
                    <span class="text-green-600">{{ formatCurrency(totalPaid) }}</span>
                  </div>
                  <div v-if="changeAmount > 0" class="flex justify-between text-xl font-bold">
                    <span>Change:</span>
                    <span class="text-emerald-600">{{ formatCurrency(changeAmount) }}</span>
                  </div>
                </div>

                <Button
                  @click="completeSale"
                  :disabled="cart.length === 0 || totalPaid < grandTotal"
                  class="w-full h-14 text-lg bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700"
                  size="lg"
                >
                  <CreditCard class="mr-2 h-6 w-6" />
                  Complete Sale
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Modal -->
    <Dialog :open="showPaymentModal" @update:open="closePaymentModal">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle class="text-2xl flex items-center gap-2">
            <CreditCard class="h-6 w-6" />
            Process Payment
          </DialogTitle>
          <DialogDescription>
            Amount to pay: <span class="font-bold text-lg text-blue-600">{{ formatCurrency(grandTotal - totalPaid) }}</span>
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-4">
          <!-- Cash Payment -->
          <div v-if="paymentMethod === 'CASH'" class="space-y-4">
            <div class="rounded-lg bg-green-50 p-4 border-2 border-green-200">
              <div class="flex items-center gap-2 mb-3">
                <Banknote class="h-5 w-5 text-green-600" />
                <h3 class="font-semibold text-green-900">Cash Payment</h3>
              </div>

              <div class="space-y-3">
                <div>
                  <Label>Amount to Pay</Label>
                  <Input
                    :value="formatCurrency(grandTotal - totalPaid)"
                    disabled
                    class="bg-white font-bold text-lg"
                  />
                </div>

                <div>
                  <Label>Cash Received</Label>
                  <Input
                    v-model.number="cashReceived"
                    type="number"
                    step="0.01"
                    placeholder="Enter amount received"
                    class="font-bold text-lg"
                  />
                </div>

                <div v-if="cashReceived >= (grandTotal - totalPaid)" class="bg-emerald-100 p-3 rounded-lg">
                  <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-emerald-900">Change:</span>
                    <span class="text-xl font-bold text-emerald-600">
                      {{ formatCurrency(cashReceived - (grandTotal - totalPaid)) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- M-Pesa STK Push -->
          <div v-if="paymentMethod === 'MPESA'" class="space-y-4">
            <div class="rounded-lg bg-purple-50 p-4 border-2 border-purple-200">
              <div class="flex items-center gap-2 mb-3">
                <Smartphone class="h-5 w-5 text-purple-600" />
                <h3 class="font-semibold text-purple-900">M-Pesa STK Push</h3>
              </div>

              <div class="space-y-3">
                <!-- Amount Display -->
                <div class="bg-purple-100 p-4 rounded-lg border-2 border-purple-300">
                  <div class="text-sm text-purple-700 mb-1">Amount to Pay</div>
                  <div class="text-3xl font-bold text-purple-900">
                    {{ formatCurrency(grandTotal - totalPaid) }}
                  </div>
                </div>

                <div>
                  <Label>Customer Phone Number</Label>
                  <Input
                    v-model="mpesaPhone"
                    type="tel"
                    placeholder="07XX XXX XXX or 254XXX XXX XXX"
                    class="font-semibold text-lg h-12"
                  />
                  <p class="text-xs text-purple-600 mt-1">📱 Customer will receive payment prompt on their phone</p>
                </div>
              </div>
            </div>
          </div>

          <!-- M-Pesa Till Payment -->
          <div v-if="paymentMethod === 'MPESA_TILL'" class="space-y-4">
            <div class="rounded-lg bg-indigo-50 p-4 border-2 border-indigo-200">
              <div class="flex items-center gap-2 mb-3">
                <Wallet class="h-5 w-5 text-indigo-600" />
                <h3 class="font-semibold text-indigo-900">M-Pesa Till Payment</h3>
              </div>

              <div class="space-y-3">
                <!-- Amount Display -->
                <div class="bg-indigo-100 p-4 rounded-lg border-2 border-indigo-300">
                  <div class="text-sm text-indigo-700 mb-1">Amount to Confirm</div>
                  <div class="text-3xl font-bold text-indigo-900">
                    {{ formatCurrency(grandTotal - totalPaid) }}
                  </div>
                </div>

                <div>
                  <Label>M-Pesa Transaction Code</Label>
                  <Input
                    v-model="mpesaTransactionCode"
                    type="text"
                    placeholder="e.g., QAB2C3D4E5"
                    class="uppercase font-mono font-bold text-lg h-12"
                  />
                  <p class="text-xs text-indigo-600 mt-1">💳 Enter the M-Pesa confirmation code from customer</p>
                </div>

                <div>
                  <Label>Customer Phone Number</Label>
                  <Input
                    v-model="mpesaPhone"
                    type="tel"
                    placeholder="Customer's phone number"
                    class="font-semibold text-lg h-12"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Card Payment -->
          <div v-if="paymentMethod === 'CARD'" class="space-y-4">
            <div class="rounded-lg bg-blue-50 p-4 border-2 border-blue-200">
              <div class="flex items-center gap-2 mb-3">
                <CreditCard class="h-5 w-5 text-blue-600" />
                <h3 class="font-semibold text-blue-900">Card Payment</h3>
              </div>

              <div class="space-y-3">
                <!-- Amount Display -->
                <div class="bg-blue-100 p-4 rounded-lg border-2 border-blue-300">
                  <div class="text-sm text-blue-700 mb-1">Amount to Charge</div>
                  <div class="text-3xl font-bold text-blue-900">
                    {{ formatCurrency(grandTotal - totalPaid) }}
                  </div>
                </div>

                <div>
                  <Label>Card Number</Label>
                  <Input
                    v-model="cardNumber"
                    type="text"
                    placeholder="1234 5678 9012 3456"
                    maxlength="16"
                    class="font-mono text-lg h-12"
                  />
                </div>

                <div class="grid grid-cols-3 gap-2">
                  <div>
                    <Label>Month</Label>
                    <Input
                      v-model="cardExpMonth"
                      type="text"
                      placeholder="MM"
                      maxlength="2"
                    />
                  </div>
                  <div>
                    <Label>Year</Label>
                    <Input
                      v-model="cardExpYear"
                      type="text"
                      placeholder="YYYY"
                      maxlength="4"
                    />
                  </div>
                  <div>
                    <Label>CVV</Label>
                    <Input
                      v-model="cardCVV"
                      type="text"
                      placeholder="123"
                      maxlength="3"
                      class="font-mono"
                    />
                  </div>
                </div>

                <div>
                  <Label>Cardholder Name</Label>
                  <Input
                    v-model="cardholderName"
                    type="text"
                    placeholder="JOHN DOE"
                    class="uppercase text-lg h-12"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Bank Transfer -->
          <div v-if="paymentMethod === 'BANK_TRANSFER'" class="space-y-4">
            <div class="rounded-lg bg-orange-50 p-4 border-2 border-orange-200">
              <div class="flex items-center gap-2 mb-3">
                <Building2 class="h-5 w-5 text-orange-600" />
                <h3 class="font-semibold text-orange-900">Bank Transfer</h3>
              </div>

              <div class="space-y-3">
                <!-- Amount Display -->
                <div class="bg-orange-100 p-4 rounded-lg border-2 border-orange-300">
                  <div class="text-sm text-orange-700 mb-1">Amount Transferred</div>
                  <div class="text-3xl font-bold text-orange-900">
                    {{ formatCurrency(grandTotal - totalPaid) }}
                  </div>
                </div>

                <div>
                  <Label>Bank Transaction Reference</Label>
                  <Input
                    v-model="bankReference"
                    type="text"
                    placeholder="FT26012212345"
                    class="uppercase font-mono font-bold text-lg h-12"
                  />
                </div>

                <div>
                  <Label>Bank Name (Optional)</Label>
                  <Input
                    v-model="bankName"
                    type="text"
                    placeholder="e.g., Equity Bank"
                    class="text-lg h-12"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            @click="closePaymentModal"
            :disabled="processingPayment"
          >
            Cancel
          </Button>
          <Button
            @click="processPayment"
            :disabled="processingPayment"
            class="bg-gradient-to-r from-green-600 to-emerald-600"
          >
            {{ processingPayment ? 'Processing...' : 'Process Payment' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

