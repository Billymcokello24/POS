# 🔍 WhatsApp Bot Troubleshooting Guide

## Step-by-Step Diagnostic

Let's check each component systematically:

### ✅ Step 1: Verify Laravel Server is Running

**Check if running:**
```bash
ps aux | grep "php artisan serve" | grep -v grep
```

**Expected output:** Should show `php artisan serve --port=8000`

**If not running, start it:**
```bash
php artisan serve --port=8000
```

Or:
```bash
composer dev
```

---

### ✅ Step 2: Verify Ngrok is Running

**Check if running:**
```bash
curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'
```

**Expected output:** Should show `https://XXXX.ngrok-free.app`

**If not running, start it (in a NEW terminal):**
```bash
ngrok http 8000
```

**Copy the HTTPS URL** that appears (e.g., `https://c129-105-29-172-2.ngrok-free.app`)

---

### ✅ Step 3: Test Webhook Locally

**Test if your webhook responds:**
```bash
curl -X POST http://localhost:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Hi", "From": "whatsapp:+254759814390"}'
```

**Expected output:** Should return JSON with welcome message

**If this fails:** Your Laravel server has an issue

---

### ✅ Step 4: Test Webhook via Ngrok

**Get your ngrok URL first:**
```bash
curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'
```

**Then test it (replace YOUR-URL):**
```bash
curl -X POST https://YOUR-URL.ngrok-free.app/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Hi", "From": "whatsapp:+254759814390"}'
```

**Expected output:** Should return JSON with welcome message

**If this fails:** Ngrok isn't forwarding properly

---

### ✅ Step 5: Configure Twilio Webhook

**CRITICAL: This is where most people get stuck!**

1. **Go to Twilio Console:**
   - https://console.twilio.com

2. **Navigate to WhatsApp Sandbox:**
   - Click **Messaging** (left sidebar)
   - Click **Try it out**
   - Click **Send a WhatsApp message**

3. **Scroll down to "Sandbox Configuration"**

4. **Find the section: "WHEN A MESSAGE COMES IN"**

5. **Enter your webhook URL:**
   ```
   https://YOUR-NGROK-URL.ngrok-free.app/api/whatsapp/webhook
   ```
   
   Example:
   ```
   https://c129-105-29-172-2.ngrok-free.app/api/whatsapp/webhook
   ```

6. **Set HTTP Method to POST**
   - Make sure dropdown says **POST**, not GET

7. **CLICK SAVE!**
   - This is the most important step!

---

### ✅ Step 6: Verify Twilio Configuration

**Check Twilio Debugger:**
1. Go to: https://console.twilio.com/monitor/logs/debugger
2. Send "Hi" from WhatsApp
3. Look for recent webhook attempts
4. Check for errors

**Common errors:**
- ❌ **11200**: HTTP retrieval failure (wrong URL or server down)
- ❌ **11750**: TwiML response invalid (webhook not returning proper response)
- ❌ **21608**: Sandbox not joined

---

### ✅ Step 7: Check Laravel Logs

**Watch logs in real-time:**
```bash
tail -f storage/logs/laravel.log
```

**Then send "Hi" from WhatsApp**

**What to look for:**
- ✅ `WhatsApp message received` - Good! Twilio is reaching your server
- ❌ Nothing appears - Twilio isn't configured or isn't sending requests

---

### ✅ Step 8: Verify Sandbox Membership

**Make sure you joined the sandbox:**

1. Open WhatsApp
2. Send message to Twilio sandbox number (e.g., `+1 415 523 8886`)
3. Type: `join YOUR-SANDBOX-CODE`
4. Wait for confirmation: "You are all set!"

**Sandbox membership expires after 72 hours!**

If it's been more than 3 days, rejoin by sending `join YOUR-CODE` again.

---

## 🎯 Quick Diagnostic Checklist

Run these commands and check each one:

```bash
# 1. Is Laravel running?
ps aux | grep "php artisan serve" | grep -v grep

# 2. Is ngrok running?
curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'

# 3. Does webhook work locally?
curl -X POST http://localhost:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Hi", "From": "whatsapp:+254759814390"}'

# 4. Are requests reaching Laravel?
tail -20 storage/logs/laravel.log | grep -i whatsapp
```

---

## 🔧 Common Issues & Solutions

### Issue 1: "No response from bot"
**Cause:** Twilio webhook not configured
**Solution:** Go to Twilio Console and configure webhook URL (Step 5)

### Issue 2: "Ngrok URL changed"
**Cause:** Ngrok free tier gives new URL each restart
**Solution:** 
1. Get new URL: `curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'`
2. Update Twilio webhook with new URL
3. Click SAVE

### Issue 3: "Sandbox expired"
**Cause:** Sandbox membership lasts 72 hours
**Solution:** Rejoin by sending `join YOUR-CODE` to Twilio number

### Issue 4: "Port 8000 already in use"
**Cause:** Another process using port 8000
**Solution:**
```bash
# Kill process on port 8000
pkill -f "php artisan serve"

# Or use different port
php artisan serve --port=8001

# Then update ngrok
ngrok http 8001
```

---

## 📱 Testing Flow

**Complete test from scratch:**

1. **Start Laravel:**
   ```bash
   php artisan serve --port=8000
   ```

2. **Start ngrok (new terminal):**
   ```bash
   ngrok http 8000
   ```

3. **Get ngrok URL:**
   ```bash
   curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'
   ```

4. **Test webhook:**
   ```bash
   curl -X POST https://YOUR-NGROK-URL.ngrok-free.app/api/whatsapp/webhook \
     -H "Content-Type: application/json" \
     -d '{"Body": "Hi", "From": "whatsapp:+254759814390"}'
   ```

5. **Configure Twilio:**
   - Paste URL in Twilio Console
   - Method: POST
   - Click SAVE

6. **Send "Hi" from WhatsApp**

7. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 🆘 Still Not Working?

If you've done all the above and it still doesn't work, check:

1. **Firewall:** Make sure port 8000 isn't blocked
2. **Twilio Account:** Verify account is active
3. **Phone Number:** Make sure you're using the correct Twilio sandbox number
4. **Webhook Response:** Twilio expects specific response format (our code handles this)

---

## 📊 Success Indicators

You'll know it's working when:

✅ Ngrok shows incoming requests in dashboard (http://localhost:4040)
✅ Laravel logs show "WhatsApp message received"
✅ Twilio debugger shows successful webhook calls (200 status)
✅ You receive a response in WhatsApp

---

**Need help? Share:**
1. Output of: `curl -s http://localhost:4040/api/tunnels | jq`
2. Output of: `tail -20 storage/logs/laravel.log`
3. Screenshot of Twilio webhook configuration
