<script setup lang="ts">
/* eslint-disable import/order */
import { usePage } from '@inertiajs/vue3'
import { ShoppingCart, Scan, Trash2, CreditCard, Users, Smartphone, Banknote, Building2, Wallet, CheckCircle, XCircle, Loader2 } from 'lucide-vue-next'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { postJsonWithSanctum } from '@/lib/sanctum'
import axios from '@/axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import AppLayout from '@/layouts/AppLayout.vue'

// Get currency from page props
const page = usePage()
const currency = computed(() => {
    const curr = page.props.currency
    return typeof curr === 'function' ? curr() : curr || 'KES'  // Default to KES (Kenyan Shilling)
})

// CSRF helpers are available via resources/js/lib/sanctum and axios is configured globally
// Provide a file-local alias used throughout this file
const safePost = (url: string, payload: any, extraHeaders: Record<string,string> = {}) => postJsonWithSanctum(url, payload, extraHeaders)

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

const { customers } = defineProps<{
    customers: Array<any>
}>()

const barcode = ref('')
const barcodeInput = ref<HTMLInputElement | null>(null)
// Prevent rapid duplicate adds: map product_id => timestamp when it was last added
const recentlyAdded = new Map<number, number>()
// Guard for scan in progress
const scanning = ref(false)

const searchQuery = ref('')
const searchResults = ref<Array<any>>([])
const showSearchResults = ref(false)
const searchContainer = ref<HTMLElement | null>(null)
const cart = ref<Array<any>>([])
const selectedCustomer = ref<number | null>(null)
const showPaymentModal = ref(false)

const payments = ref<Array<{ method: string; amount: number; reference?: string }>>([])
// Prevent duplicate sale submissions
const saleSubmitting = ref(false)
// Payment checkout id for polling
const paymentCheckoutId = ref('')

// New: keep a synchronous window reference so popups are not blocked
const receiptWindowRef = ref<Window | null>(null)

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
// VAT rate (16%)
const vatRate = 0.16

// Cart total (prices are VAT-inclusive)
const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => {
        return total + (item.quantity * item.unit_price)
    }, 0)
})

// Since prices are VAT-inclusive, reverse-calculate:
// Net Amount = Total / (1 + VAT rate)
// VAT Amount = Total - Net Amount
const netAmount = computed(() => {
    return cartTotal.value / (1 + vatRate)
})

const vatAmount = computed(() => {
    return cartTotal.value - netAmount.value
})

// Grand total equals cart total (VAT already included)
const grandTotal = computed(() => {
    return cartTotal.value
})

const totalPaid = computed(() => {
    return payments.value.reduce((sum, p) => sum + p.amount, 0)
})

const changeAmount = computed(() => {
    return Math.max(0, totalPaid.value - grandTotal.value)
})

const scanBarcode = async () => {
    if (!barcode.value) return
    if (scanning.value) return // already scanning
    scanning.value = true

    try {
        const response = await axios.get(`/api/products/scan?barcode=${encodeURIComponent(barcode.value)}`)

        const product = response.data

        if (!product || !product.id) {
            // Only show alert if product truly not found (null response)
            alert('Product not found!')
            barcode.value = ''
            return
        }

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
    } finally {
        // small throttle to avoid duplicate scanner events
        setTimeout(() => { scanning.value = false }, 300)
    }
}

const searchProducts = async () => {
    if (!searchQuery.value) {
        searchResults.value = []
        showSearchResults.value = false
        return
    }

    try {
        const res = await axios.get(`/api/products/search?q=${encodeURIComponent(searchQuery.value)}`)
        const products = res.data

        if (!products) {
            searchResults.value = []
            showSearchResults.value = false
            return
        }

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
    const now = Date.now()
    const last = recentlyAdded.get(product.id) || 0
    // if added within last 1s, ignore (scanner sometimes emits duplicates)
    if (now - last < 1000) {
        console.log(`Ignoring rapid duplicate add for ${product.name}`)
        return
    }
    recentlyAdded.set(product.id, now)
    // cleanup after 2s
    setTimeout(() => recentlyAdded.delete(product.id), 2000)

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
// Prevent concurrent MPESA STK pushes
const processingMpesa = ref(false)
// Modal states for preload and success
const paymentSuccess = ref(false)
const paymentSuccessData = ref<{ receipt?: string; amount?: number } | null>(null)
const paymentError = ref<string | null>(null)

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
    // reset modal states
    paymentSuccess.value = false
    paymentSuccessData.value = null
    paymentError.value = null
    // reset processing flags
    processingPayment.value = false
    processingMpesa.value = false
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
        processingPayment.value = false
        return
    }

    if (processingMpesa.value) {
        console.warn('STK push already in progress, ignoring duplicate request')
        return
    }
    processingMpesa.value = true

    const remaining = grandTotal.value - totalPaid.value

    try {
        console.log('Initiating M-Pesa STK Push...')
        console.log('Amount:', remaining)
        console.log('Phone:', mpesaPhone.value)

        // Use safePost helper which ensures the sanctum csrf-cookie and retries on 419.
        const response = await safePost('/api/payments/mpesa/stk-push', {
            phone_number: mpesaPhone.value,
            amount: remaining,
            account_reference: 'POS-SALE-' + Date.now()
        })

        console.log('Response status:', response.status)

        if (!response.ok) {
            const errorText = await response.text()

            // Try to parse as JSON
            let errorMessage = 'M-Pesa payment failed'
            let errorType = 'unknown'
            try {
                const errorData = JSON.parse(errorText)
                errorMessage = errorData.message || errorData.error || errorMessage
                errorType = errorData.error || errorType
            } catch {
                // If not JSON, use the text
                errorMessage = errorText.substring(0, 200) // First 200 chars
            }

            // Show specific message for connection timeout
            if (errorType === 'Connection timeout' || errorMessage.includes('Connection to M-Pesa timed out')) {
                paymentError.value = '❌ Cannot Connect to M-Pesa\n\nYour server cannot reach Safaricom M-Pesa API.\n\nPossible causes:\n• Firewall blocking outbound HTTPS connections\n• No internet connection from server\n• Network configuration issues\n\nTemporary Solution:\nUse "M-Pesa Till Payment" option instead'
            } else {
                paymentError.value = 'M-Pesa Error: ' + errorMessage
            }

            throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }

        const result = await response.json()
        console.log('M-Pesa Response:', result)

        if (result.success) {
            paymentCheckoutId.value = result.data.checkout_request_id
            // Keep processing state to show preloader
            paymentSuccess.value = false
            paymentError.value = null

            // Start polling for payment status
            pollMpesaStatus(result.data.checkout_request_id, remaining)
        } else {
            paymentError.value = result.message || 'M-Pesa payment failed'
            processingPayment.value = false
        }
    } catch (error: unknown) {
        console.error('M-Pesa STK Push error:', error)

        // Provide more helpful error messages
        const errorMessage = error instanceof Error ? error.message : String(error)
        if (errorMessage.includes('Failed to fetch')) {
            paymentError.value = 'Network Error: Cannot reach the payment server.\n\nPlease check:\n1. You are connected to the internet\n2. The server is running\n3. Try refreshing the page'
        } else if (!paymentError.value) {
            paymentError.value = 'Payment Error: ' + errorMessage
        }
        processingPayment.value = false
        throw error
    } finally {
        processingMpesa.value = false
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
            const response = await safePost('/api/payments/mpesa/check-status', { checkout_request_id: checkoutRequestId })

            if (!response.ok) {
                console.error('Status check HTTP error:', response.status)
                // Don't stop polling on HTTP errors, might be temporary
                if (attempts >= maxAttempts) {
                    clearInterval(interval)
                    paymentError.value = 'Payment verification timed out. Please check your M-Pesa messages.'
                    processingPayment.value = false
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
                    paymentError.value = 'Cannot verify payment status. Please check your M-Pesa messages.'
                    processingPayment.value = false
                }
                return
            }

            // Check the result code
            const resultCode = result.data?.ResultCode

            if (resultCode === '0') {
                // Success!
                clearInterval(interval)
                console.log('✅ Payment successful!')

                // Add payment to list (dedupe)
                const receipt = result.data.MpesaReceiptNumber || checkoutRequestId
                const already = payments.value.some(p => p.reference === receipt || p.reference === checkoutRequestId)
                if (!already) {
                    payments.value.push({ method: 'MPESA', amount: amount, reference: receipt })
                } else {
                    console.log('Duplicate payment detected, skipping push')
                }

                // Set success state to show tick in modal
                paymentSuccessData.value = { receipt: receipt, amount: amount }
                paymentSuccess.value = true
                paymentError.value = null
                processingPayment.value = false

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

                // Mark error and keep modal open to show friendly message
                if (cancelledCodes.includes(resultCode.toString())) {
                    const errorDesc = result.data.ResultDesc || ''

                    // Check if it's actual cancellation or M-Pesa system error
                    if (errorDesc.includes('unresolved reason')) {
                        paymentError.value = 'The payment could not be processed. Possible reasons include:\n• Phone number is not registered with M-Pesa\n• Network connectivity issues\n• Phone is switched off or out of coverage\n• M-Pesa account has restrictions\n\nPlease verify the phone number and try again or use a different payment method.'
                    } else {
                        paymentError.value = 'Payment cancelled by customer'
                    }
                } else if (resultCode === '1') {
                    paymentError.value = 'Insufficient Balance'
                } else if (resultCode === '2001') {
                    paymentError.value = 'Wrong PIN'
                } else if (resultCode === '2006') {
                    paymentError.value = 'PIN Blocked'
                } else if (resultCode === '2007') {
                    paymentError.value = 'Request Timed Out'
                } else {
                    paymentError.value = `M-Pesa Payment Failed: ${friendlyMessage}`
                }
                processingPayment.value = false
            } else {
                // No result code yet - continue polling
                console.log('No result code yet, continuing to poll...')
            }

            // Check if we've reached max attempts
            if (attempts >= maxAttempts) {
                clearInterval(interval)
                paymentError.value = 'Payment verification timed out. Please check your M-Pesa messages.'
                processingPayment.value = false
            }

        } catch (error) {
            console.error('Status check error:', error)
            // Log but don't stop polling - might be temporary network issue
            if (attempts >= maxAttempts) {
                clearInterval(interval)
                paymentError.value = 'Network error during verification. Please check your M-Pesa messages.'
                processingPayment.value = false
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

    const response = await safePost('/api/payments/mpesa/till-payment', {
        transaction_code: mpesaTransactionCode.value,
        phone_number: mpesaPhone.value,
        amount: remaining
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

    const response = await safePost('/api/payments/card', {
        card_number: cardNumber.value.replace(/\s/g, ''),
        expiry_month: parseInt(cardExpMonth.value),
        expiry_year: parseInt(cardExpYear.value),
        cvv: cardCVV.value,
        cardholder_name: cardholderName.value,
        amount: remaining
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

    const response = await safePost('/api/payments/bank-transfer', {
        reference_number: bankReference.value,
        amount: remaining,
        bank_name: bankName.value || null
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
        const response = await safePost('/api/payments/cash', {
            amount: remaining,
            received_amount: cashReceived.value
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
    if (saleSubmitting.value) {
        console.warn('Sale submission already in progress, ignoring duplicate call')
        return
    }
    saleSubmitting.value = true

    if (cart.value.length === 0) {
        alert('Cart is empty! Add items before completing sale.')
        saleSubmitting.value = false
        return
    }

    if (payments.value.length === 0) {
        alert('Please add at least one payment method!')
        saleSubmitting.value = false
        return
    }

    if (totalPaid.value < grandTotal.value) {
        alert(`Insufficient payment! Need $${(grandTotal.value - totalPaid.value).toFixed(2)} more.`)
        saleSubmitting.value = false
        return
    }

    // For instant printing we call the quick API which returns a base64 PDF.
    const payload = {
        customer_id: selectedCustomer.value,
        items: cart.value.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
        })),
        payments: payments.value.map(p => ({
            payment_method: p.method,
            amount: p.amount,
            reference_number: p.reference || null,
        })),
    }

    try {
        // Open popup synchronously BEFORE the async request to avoid popup blockers
        const popup = receiptWindowRef.value && !receiptWindowRef.value.closed ? receiptWindowRef.value : window.open('', '_blank')
        if (popup) {
            try { popup.document.write('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Printing...</title></head><body><p style="font-family: sans-serif; padding:24px;">Preparing receipt...</p></body></html>') } catch(e){ console.debug(e) }
        }

        const resp = await safePost('/api/sales/quick', payload)

        if (!resp.ok) {
            const t = await resp.text()
            throw new Error(`HTTP ${resp.status}: ${t}`)
        }

        const data = await resp.json()
        if (!data.success || !data.pdf_base64) {
            throw new Error(data.message || 'No PDF returned')
        }

        // Convert base64 to blob URL and navigate popup to it so browser shows PDF immediately
        const binary = atob(data.pdf_base64)
        const len = binary.length
        const bytes = new Uint8Array(len)
        for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i)
        const blob = new Blob([bytes], { type: 'application/pdf' })
        const url = URL.createObjectURL(blob)

        if (popup && !popup.closed) {
            popup.location.href = url

            // Try to trigger print in the popup when it's loaded
            const tryPrint = () => {
                try {
                    if (popup.document && popup.document.readyState === 'complete') {
                        popup.focus()
                        setTimeout(() => {
                            try { popup.print() } catch (e) { console.error('Print in popup failed', e) }
                        }, 150)
                        return true
                    }
                } catch (err) { console.debug(err) }
                return false
            }

            const interval = setInterval(() => { if (tryPrint()) clearInterval(interval) }, 250)
            setTimeout(() => clearInterval(interval), 12000)
        } else {
            // Fallback: open in new tab
            const w = window.open(url, '_blank')
            if (!w) alert('Popup blocked: please allow popups to print the receipt')
        }

        // Clear cart and reset UI
        cart.value = []
        payments.value = []
        selectedCustomer.value = null
        showPaymentModal.value = false
        saleSubmitting.value = false

    } catch (error: any) {
        console.error('Quick sale error:', error)
        saleSubmitting.value = false
        alert('Failed to complete sale: ' + (error?.message || String(error)))
    }
}
</script>

<template>
    <AppLayout title="Point of Sale">
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-6">
            <div class="mx-auto w-[90%] space-y-6">
                <!-- Professional Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900 flex items-center gap-3">
                            <div class="rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 p-3 text-white shadow-lg">
                                <ShoppingCart class="h-8 w-8" />
                            </div>
                            Point of Sale
                        </h1>
                        <p class="text-lg text-gray-600 mt-2">Fast & efficient checkout terminal</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Product Search & Cart -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Barcode Scanner -->
                        <Card class="border-0 shadow-lg bg-white">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-xl font-bold">
                                    <Scan class="h-5 w-5 text-blue-600" />
                                    Quick Scan
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="flex gap-3">
                                    <Input
                                        ref="barcodeInput"
                                        v-model="barcode"
                                        placeholder="Scan or enter barcode..."
                                        @keyup.enter="scanBarcode"
                                        autofocus
                                        class="flex-1 h-12 text-base border-2 border-gray-200 focus:border-blue-600"
                                    />
                                    <Button
                                        @click="scanBarcode"
                                        class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md"
                                    >
                                        <Scan class="h-5 w-5 mr-2" />
                                        Scan
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Product Search -->
                        <Card class="border-0 shadow-lg bg-white">
                            <CardHeader>
                                <CardTitle class="text-xl font-bold text-gray-900">Search Products</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div ref="searchContainer" class="relative">
                                    <div class="flex gap-3">
                                        <Input
                                            v-model="searchQuery"
                                            placeholder="Search products by name or SKU..."
                                            @keyup="searchProducts"
                                            @keyup.enter="searchProducts"
                                            class="flex-1 h-12 text-base border-2 border-gray-200 focus:border-blue-600"
                                        />
                                        <Button
                                            @click="searchProducts"
                                            class="h-12 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md"
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
                                            class="p-4 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-all"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="font-semibold text-lg text-gray-900">{{ product.name }}</div>
                                                    <div class="text-sm text-gray-600 mt-1">
                                                        <Badge variant="outline" class="mr-2">{{ product.sku }}</Badge>
                                                        <span v-if="product.barcode" class="text-xs">Barcode: {{ product.barcode }}</span>
                                                    </div>
                                                </div>
                                                <div class="text-right ml-4">
                                                    <div class="text-xl font-bold text-blue-600">{{ formatCurrency(product.selling_price) }}</div>
                                                    <div class="text-xs text-gray-500">
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
                        <Card class="border-0 shadow-lg bg-white">
                            <CardHeader class="py-3">
                                <CardTitle class="flex items-center justify-between text-lg font-bold">
                  <span class="flex items-center gap-2">
                    <ShoppingCart class="h-4 w-4 text-blue-600" />
                    Shopping Cart
                  </span>
                                    <Badge variant="secondary" class="text-sm px-2 py-0.5">
                                        {{ cart.length }} {{ cart.length === 1 ? 'item' : 'items' }}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="py-2">
                                <div v-if="cart.length === 0" class="py-10 text-center">
                                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 mb-3">
                                        <ShoppingCart class="h-7 w-7 text-gray-400" />
                                    </div>
                                    <p class="text-gray-600 font-medium text-base">Cart is empty</p>
                                    <p class="text-xs text-gray-500 mt-1">Scan or search for products to add</p>
                                </div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="(item, index) in cart"
                                        :key="index"
                                        class="flex items-center justify-between rounded-lg border border-gray-200 p-2 hover:border-blue-300 hover:bg-blue-50/50 transition-all"
                                    >
                                        <div class="flex-1">
                                            <div class="font-semibold text-sm text-gray-900">{{ item.name }}</div>
                                            <div class="text-xs text-gray-600 mt-0.5 flex items-center gap-1">
                                                <Badge variant="outline" class="text-xs px-1 py-0">{{ item.sku }}</Badge>
                                                <span>{{ formatCurrency(item.unit_price) }} each</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <Input
                                                type="number"
                                                :model-value="item.quantity"
                                                @update:model-value="(val) => updateQuantity(index, Number(val))"
                                                :max="item.available_quantity"
                                                min="1"
                                                class="w-14 h-8 border text-center text-sm font-bold rounded"
                                            />
                                            <div class="min-w-[80px] text-right">
                                                <div class="text-base font-bold text-blue-600">
                                                    {{ formatCurrency(item.quantity * item.unit_price) }}
                                                </div>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="removeFromCart(index)"
                                                class="h-8 w-8 p-0 border-red-200 text-red-500 hover:bg-red-50 hover:border-red-400 hover:text-red-600"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Order Summary & Payment -->
                    <div class="space-y-6">
                        <!-- Customer Selection -->
                        <Card class="border-0 shadow-lg bg-white">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-xl font-bold">
                                    <Users class="h-5 w-5 text-purple-600" />
                                    Customer
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Select v-model="selectedCustomer">
                                    <SelectTrigger class="h-12 border-2 border-gray-200">
                                        <SelectValue placeholder="Walk-in Customer" />
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
                        <Card class="border-0 shadow-lg overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white">
                            <CardHeader class="py-3">
                                <CardTitle class="text-white text-lg font-bold">Order Summary</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 py-3">
                                <!-- VAT Inclusive Breakdown -->
                                <div class="bg-white/10 rounded-lg p-2 space-y-1">
                                    <div class="text-xs text-blue-200 text-center font-semibold uppercase tracking-wide">VAT Breakdown (16% Inclusive)</div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-blue-100">Subtotal (excl. VAT):</span>
                                        <span class="font-semibold">{{ formatCurrency(netAmount) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-blue-100">VAT (16%):</span>
                                        <span class="font-semibold">{{ formatCurrency(vatAmount) }}</span>
                                    </div>
                                </div>
                                <Separator class="bg-white/30" />
                                <div class="flex justify-between text-xl font-bold">
                                    <span>Total (incl. VAT):</span>
                                    <span>{{ formatCurrency(grandTotal) }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Payment Methods -->
                        <Card class="border-0 shadow-lg bg-white">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-xl font-bold">
                                    <CreditCard class="h-5 w-5 text-green-600" />
                                    Payment Methods
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <Button
                                        @click="openPaymentModal('CASH')"
                                        variant="outline"
                                        class="h-16 border-2 border-gray-200 hover:bg-green-50 hover:border-green-500 flex flex-col items-center gap-2 transition-all"
                                    >
                                        <Banknote class="h-6 w-6 text-green-600" />
                                        <span class="text-sm font-semibold">Cash</span>
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
                <!-- Show form when NOT processing and NOT success and NOT error -->
                <div v-if="!processingPayment && !paymentSuccess && !paymentError">
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
                </div>

                <!-- Processing/Preloader State -->
                <div v-if="processingPayment && !paymentSuccess && !paymentError" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="relative mb-6">
                        <!-- Animated spinner with gradient -->
                        <div class="relative">
                            <Loader2 class="h-20 w-20 text-purple-600 animate-spin" />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <Smartphone class="h-8 w-8 text-purple-400" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Processing Payment</h3>
                    <p class="text-center text-gray-600 mb-4">
                        Please wait while we process your M-Pesa payment...
                    </p>

                    <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4 w-full max-w-sm">
                        <p class="text-sm text-purple-800 text-center font-medium">
                            📱 Customer should check their phone for the M-Pesa prompt
                        </p>
                    </div>

                    <div class="mt-6 flex items-center gap-2">
                        <div class="w-2 h-2 bg-purple-600 rounded-full animate-pulse"></div>
                        <div class="w-2 h-2 bg-purple-600 rounded-full animate-pulse delay-100"></div>
                        <div class="w-2 h-2 bg-purple-600 rounded-full animate-pulse delay-200"></div>
                    </div>
                </div>

                <!-- Success State -->
                <div v-if="paymentSuccess" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="mb-6 relative">
                        <!-- Success checkmark with animation -->
                        <div class="relative">
                            <div class="absolute inset-0 bg-green-100 rounded-full animate-ping opacity-75"></div>
                            <div class="relative bg-gradient-to-br from-green-500 to-emerald-600 rounded-full p-4">
                                <CheckCircle class="h-16 w-16 text-white" stroke-width="2.5" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-green-800 mb-2">Payment Successful! 🎉</h3>
                    <p class="text-center text-gray-600 mb-6">
                        Your M-Pesa payment has been processed successfully
                    </p>

                    <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4 w-full max-w-sm space-y-2 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700">Receipt Number:</span>
                            <span class="font-mono font-bold text-green-900">{{ paymentSuccessData?.receipt }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700">Amount Paid:</span>
                            <span class="font-bold text-green-900">{{ formatCurrency(paymentSuccessData?.amount ?? 0) }}</span>
                        </div>
                    </div>

                    <Button
                        @click="closePaymentModal"
                        class="w-full max-w-sm h-12 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold"
                    >
                        Done
                    </Button>
                </div>

                <!-- Error State -->
                <div v-if="paymentError" class="flex flex-col items-center justify-center py-12 px-6">
                    <div class="mb-6 relative">
                        <!-- Error icon with animation -->
                        <div class="relative">
                            <div class="absolute inset-0 bg-red-100 rounded-full animate-pulse"></div>
                            <div class="relative bg-gradient-to-br from-red-500 to-rose-600 rounded-full p-4">
                                <XCircle class="h-16 w-16 text-white" stroke-width="2.5" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-2xl font-bold text-red-800 mb-2">Payment Failed</h3>

                    <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4 w-full max-w-sm mb-6">
                        <p class="text-sm text-red-800 text-center whitespace-pre-line">
                            {{ paymentError }}
                        </p>
                    </div>

                    <div class="flex gap-3 w-full max-w-sm">
                        <Button
                            @click="closePaymentModal"
                            variant="outline"
                            class="flex-1 h-12 border-2 border-red-300 text-red-700 hover:bg-red-50"
                        >
                            Close
                        </Button>
                        <Button
                            @click="() => { paymentError = null; processingPayment = false; }"
                            class="flex-1 h-12 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700"
                        >
                            Try Again
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
@keyframes delay-100 {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

@keyframes delay-200 {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.animate-pulse.delay-100 {
    animation: delay-100 1.5s ease-in-out infinite;
    animation-delay: 0.1s;
}

.animate-pulse.delay-200 {
    animation: delay-200 1.5s ease-in-out infinite;
    animation-delay: 0.2s;
}
</style>
