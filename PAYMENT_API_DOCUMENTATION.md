# Payment API Documentation

## Overview
This document describes the payment integration APIs available in the POS system, including M-Pesa STK Push, Till Payments, Card Payments, Bank Transfers, and Cash Payments.

---

## Configuration

### M-Pesa Setup

Add these environment variables to your `.env` file:

```env
MPESA_ENVIRONMENT=sandbox                           # or 'production'
MPESA_CONSUMER_KEY=your_consumer_key_here
MPESA_CONSUMER_SECRET=your_consumer_secret_here
MPESA_SHORTCODE=174379                             # Your paybill/till number
MPESA_PASSKEY=your_passkey_here
MPESA_CALLBACK_URL=https://yourdomain.com/api/payments/mpesa/callback
```

### Getting M-Pesa Credentials

1. **Sandbox (Testing)**:
   - Visit: https://developer.safaricom.co.ke/
   - Create an app
   - Get Consumer Key & Consumer Secret
   - Use test credentials from Daraja API documentation

2. **Production**:
   - Contact Safaricom to get production credentials
   - Complete KYC and business verification
   - Receive live paybill/till number and passkey

---

## API Endpoints

Base URL: `http://127.0.0.1:8000/api/payments`

---

## 1. M-Pesa STK Push

Initiates an M-Pesa payment request that prompts the customer's phone.

### Endpoint
```
POST /api/payments/mpesa/stk-push
```

### Request Body
```json
{
    "phone_number": "0712345678",
    "amount": 500,
    "sale_id": 123,
    "account_reference": "SALE-123"
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| phone_number | string | Yes | Customer's M-Pesa number (07XX or 254XXX) |
| amount | number | Yes | Amount to charge (minimum 1) |
| sale_id | integer | No | Associated sale ID |
| account_reference | string | No | Reference for the transaction |

### Response (Success)
```json
{
    "success": true,
    "message": "Payment request sent. Please check your phone.",
    "data": {
        "checkout_request_id": "ws_CO_22012026143045...",
        "merchant_request_id": "12345-67890-1"
    }
}
```

### Response (Error)
```json
{
    "success": false,
    "message": "Invalid phone number format. Use 07XX XXX XXX or 254XXX XXX XXX",
    "error": null
}
```

### Usage Example (JavaScript)
```javascript
const initiateMpesaPayment = async (phoneNumber, amount, saleId) => {
    try {
        const response = await fetch('/api/payments/mpesa/stk-push', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                phone_number: phoneNumber,
                amount: amount,
                sale_id: saleId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('STK Push sent:', result.data.checkout_request_id);
            // Start polling for payment status
            pollPaymentStatus(result.data.checkout_request_id);
        } else {
            console.error('Payment failed:', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
    }
};
```

---

## 2. Check M-Pesa Payment Status

Query the status of an STK Push request.

### Endpoint
```
POST /api/payments/mpesa/check-status
```

### Request Body
```json
{
    "checkout_request_id": "ws_CO_22012026143045..."
}
```

### Response
```json
{
    "success": true,
    "data": {
        "ResponseCode": "0",
        "ResponseDescription": "The service request has been accepted successfully",
        "ResultCode": "0",
        "ResultDesc": "The service request is processed successfully."
    }
}
```

### Usage Example
```javascript
const checkPaymentStatus = async (checkoutRequestId) => {
    const response = await fetch('/api/payments/mpesa/check-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ checkout_request_id: checkoutRequestId })
    });
    
    return await response.json();
};

// Poll every 3 seconds for 60 seconds
const pollPaymentStatus = (checkoutRequestId) => {
    let attempts = 0;
    const maxAttempts = 20;
    
    const interval = setInterval(async () => {
        attempts++;
        
        const status = await checkPaymentStatus(checkoutRequestId);
        
        if (status.data.ResultCode === '0') {
            clearInterval(interval);
            console.log('Payment successful!');
        } else if (attempts >= maxAttempts) {
            clearInterval(interval);
            console.log('Payment timeout');
        }
    }, 3000);
};
```

---

## 3. M-Pesa Till Payment

Record a payment made directly to your till number.

### Endpoint
```
POST /api/payments/mpesa/till-payment
```

### Request Body
```json
{
    "transaction_code": "QAB2C3D4E5",
    "phone_number": "254712345678",
    "amount": 1500,
    "sale_id": 123
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| transaction_code | string | Yes | M-Pesa confirmation code |
| phone_number | string | Yes | Customer's phone number |
| amount | number | Yes | Amount paid |
| sale_id | integer | No | Associated sale ID |

### Response
```json
{
    "success": true,
    "message": "Till payment recorded successfully",
    "transaction_code": "QAB2C3D4E5",
    "amount": 1500,
    "phone_number": "254712345678"
}
```

---

## 4. Card Payment

Process a credit/debit card payment.

### Endpoint
```
POST /api/payments/card
```

### Request Body
```json
{
    "card_number": "4111111111111111",
    "expiry_month": 12,
    "expiry_year": 2026,
    "cvv": "123",
    "cardholder_name": "John Doe",
    "amount": 2500,
    "sale_id": 123
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| card_number | string | Yes | 16-digit card number |
| expiry_month | integer | Yes | Card expiry month (1-12) |
| expiry_year | integer | Yes | Card expiry year |
| cvv | string | Yes | 3-digit security code |
| cardholder_name | string | Yes | Name on card |
| amount | number | Yes | Amount to charge |
| sale_id | integer | No | Associated sale ID |

### Response
```json
{
    "success": true,
    "message": "Card payment processed successfully",
    "transaction_id": "CARD-659ABC123DEF",
    "amount": 2500
}
```

---

## 5. Bank Transfer

Record a bank transfer payment.

### Endpoint
```
POST /api/payments/bank-transfer
```

### Request Body
```json
{
    "reference_number": "FT26012212345",
    "amount": 5000,
    "bank_name": "Equity Bank",
    "account_number": "1234567890",
    "sale_id": 123
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| reference_number | string | Yes | Bank transaction reference |
| amount | number | Yes | Amount transferred |
| bank_name | string | No | Bank name |
| account_number | string | No | Account number |
| sale_id | integer | No | Associated sale ID |

### Response
```json
{
    "success": true,
    "message": "Bank transfer recorded successfully",
    "reference_number": "FT26012212345",
    "amount": 5000
}
```

---

## 6. Cash Payment

Record a cash payment.

### Endpoint
```
POST /api/payments/cash
```

### Request Body
```json
{
    "amount": 1000,
    "received_amount": 1500,
    "sale_id": 123
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| amount | number | Yes | Amount to pay |
| received_amount | number | Yes | Amount received from customer |
| sale_id | integer | No | Associated sale ID |

### Response
```json
{
    "success": true,
    "message": "Cash payment recorded successfully",
    "change": 500
}
```

---

## M-Pesa Callback

The M-Pesa callback endpoint receives payment notifications from Safaricom.

### Endpoint
```
POST /api/payments/mpesa/callback
```

### Important Notes:
- This endpoint does NOT require authentication
- Safaricom sends payment results here
- Must be publicly accessible (use ngrok for local testing)
- Configure the URL in `MPESA_CALLBACK_URL` environment variable

### Callback Payload Example
```json
{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "12345-67890-1",
            "CheckoutRequestID": "ws_CO_22012026143045...",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "CallbackMetadata": {
                "Item": [
                    {
                        "Name": "Amount",
                        "Value": 500
                    },
                    {
                        "Name": "MpesaReceiptNumber",
                        "Value": "QAB2C3D4E5"
                    },
                    {
                        "Name": "PhoneNumber",
                        "Value": 254712345678
                    }
                ]
            }
        }
    }
}
```

---

## Error Codes

### M-Pesa Result Codes
| Code | Description |
|------|-------------|
| 0 | Success |
| 1 | Insufficient funds |
| 17 | User cancelled transaction |
| 26 | System busy |
| 1032 | Request cancelled by user |
| 2001 | Invalid initiator information |

### HTTP Status Codes
| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized |
| 404 | Not Found |
| 500 | Internal Server Error |

---

## Testing

### Test Credentials (Sandbox)

```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_test_consumer_key
MPESA_CONSUMER_SECRET=your_test_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_test_passkey
```

### Test Phone Numbers
- **Success**: 254708374149
- **Insufficient Funds**: 254708374150
- **User Cancelled**: 254708374151

### Local Testing with ngrok

1. Install ngrok: `npm install -g ngrok`
2. Start ngrok: `ngrok http 8000`
3. Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)
4. Update `.env`:
   ```env
   MPESA_CALLBACK_URL=https://abc123.ngrok.io/api/payments/mpesa/callback
   ```
5. Test payments!

---

## Security Best Practices

1. **Never expose credentials**: Keep API keys in `.env` file
2. **Use HTTPS**: Always use HTTPS in production
3. **Validate amounts**: Check amounts before processing
4. **Verify callbacks**: Validate M-Pesa callbacks are genuine
5. **Log transactions**: Keep detailed logs of all payments
6. **Handle errors**: Implement proper error handling
7. **Test thoroughly**: Test all payment scenarios

---

## Complete Integration Example

```vue
<script setup>
import { ref } from 'vue';

const phoneNumber = ref('');
const amount = ref(0);
const paymentMethod = ref('MPESA');
const processing = ref(false);

const processPayment = async () => {
    processing.value = true;
    
    try {
        if (paymentMethod.value === 'MPESA') {
            await processMpesaPayment();
        } else if (paymentMethod.value === 'CASH') {
            await processCashPayment();
        }
    } finally {
        processing.value = false;
    }
};

const processMpesaPayment = async () => {
    const response = await fetch('/api/payments/mpesa/stk-push', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            phone_number: phoneNumber.value,
            amount: amount.value
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert('Check your phone to complete payment');
        // Poll for status
    } else {
        alert('Payment failed: ' + result.message);
    }
};
</script>
```

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- M-Pesa documentation: https://developer.safaricom.co.ke/
- Contact: support@yourpos.com

---

**Last Updated**: January 22, 2026

