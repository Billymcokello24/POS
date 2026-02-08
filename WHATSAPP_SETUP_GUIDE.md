# WhatsApp Bot - Twilio Sandbox Setup Guide

## 🚀 Quick Setup (5 Minutes)

### Step 1: Create Twilio Account

1. Go to: https://www.twilio.com/try-twilio
2. Sign up for a **FREE** account
3. Verify your email and phone number

### Step 2: Access WhatsApp Sandbox

1. Log into Twilio Console: https://console.twilio.com
2. Navigate to: **Messaging** → **Try it out** → **Send a WhatsApp message**
3. You'll see a sandbox number like: `+1 415 523 8886`
4. You'll see a join code like: `join <your-code>`

### Step 3: Join the Sandbox from Your Phone

1. **Open WhatsApp** on your phone
2. **Send a message** to the Twilio sandbox number: `+1 415 523 8886`
3. **Type exactly**: `join <your-code>` (e.g., `join happy-tiger`)
4. You'll receive a confirmation message

### Step 4: Expose Your Local Server

We need to make your local Laravel server accessible from the internet so Twilio can send messages to it.

**Option A: Using ngrok (Recommended)**

```bash
# Install ngrok (if not installed)
# Download from: https://ngrok.com/download

# Start ngrok tunnel
ngrok http 8000
```

You'll see output like:
```
Forwarding  https://abc123.ngrok.io -> http://localhost:8000
```

**Copy the HTTPS URL** (e.g., `https://abc123.ngrok.io`)

**Option B: Using Cloudflare Tunnel**

```bash
# Install cloudflared
# Download from: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/

# Start tunnel
cloudflared tunnel --url http://localhost:8000
```

### Step 5: Configure Twilio Webhook

1. In Twilio Console, go to: **Messaging** → **Settings** → **WhatsApp sandbox settings**
2. Find: **"When a message comes in"**
3. Enter your webhook URL: `https://your-ngrok-url.ngrok.io/api/whatsapp/webhook`
   - Example: `https://abc123.ngrok.io/api/whatsapp/webhook`
4. Method: **POST**
5. Click **Save**

### Step 6: Test Your Bot!

1. **Open WhatsApp** on your phone
2. **Send a message** to the Twilio sandbox number
3. **Type**: `Hi`
4. You should see the bot respond! 🎉

## 📱 Example Conversation

```
You: Hi
Bot: Welcome to *ModernPOS*! 👋
     Your business management assistant.
     1️⃣ Login
     2️⃣ Register
     Type '1' or 'Login' to proceed.

You: Register
Bot: Welcome to ModernPOS Registration! 🎉
     Please enter your *Full Name*:

You: Billy Okello
Bot: Nice to meet you, *Billy Okello*! 👋
     Please enter your *Email Address*:

... and so on!
```

## 🔧 Troubleshooting

### Bot Not Responding?

1. **Check ngrok is running**:
   ```bash
   # You should see the tunnel URL
   curl http://localhost:4040/api/tunnels
   ```

2. **Check Laravel server is running**:
   ```bash
   # Should show "Development Server (http://127.0.0.1:8000) started"
   ps aux | grep "php artisan serve"
   ```

3. **Check webhook URL in Twilio**:
   - Must be HTTPS (ngrok provides this)
   - Must end with `/api/whatsapp/webhook`
   - Must be POST method

4. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Getting "Message Failed" Error?

- Verify you joined the sandbox correctly
- Check the webhook URL is correct
- Ensure ngrok tunnel is active

### Ngrok URL Changed?

Ngrok free tier gives you a new URL each time. You need to:
1. Copy the new ngrok URL
2. Update it in Twilio webhook settings
3. Restart your test

**Pro Tip**: Get a static ngrok URL with a paid plan ($8/month)

## 🎯 What You Can Do Now

Once connected, you can:
- ✅ Register new businesses via WhatsApp
- ✅ Login to existing accounts
- ✅ Process sales
- ✅ Check inventory
- ✅ View customers
- ✅ Generate reports
- ✅ All with role-based access!

## 📊 Monitoring

Watch real-time activity:
```bash
# Terminal 1: Laravel server
php artisan serve --port=8000

# Terminal 2: Ngrok tunnel
ngrok http 8000

# Terminal 3: Laravel logs
tail -f storage/logs/laravel.log
```

## 🔒 Security Note

For production, you should:
1. Add webhook signature verification
2. Use environment variables for credentials
3. Enable rate limiting
4. Use Meta WhatsApp Business API instead of sandbox

## 💰 Costs

- **Twilio Sandbox**: FREE forever
- **Ngrok Free**: FREE (new URL each restart)
- **Ngrok Paid**: $8/month (static URL)

## Next Steps

After testing with sandbox, you can upgrade to:
1. **Twilio Production** ($0.005 per message)
2. **Meta WhatsApp Business API** (FREE for first 1000 conversations/month)

---

**Ready to start? Let me know when you've:**
1. ✅ Created Twilio account
2. ✅ Joined the sandbox
3. ✅ Started ngrok
4. ✅ Configured webhook

Then send "Hi" to the bot and watch the magic! ✨
