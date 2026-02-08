# How to Test the WhatsApp Bot

## Option 1: Using cURL (Simplest - Test Locally Right Now)

The server is already running on `http://127.0.0.1:8000`. You can test the bot using cURL commands:

### Quick Test Commands

```bash
# 1. Start a conversation
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Hi", "From": "whatsapp:+254700123456"}'

# 2. Register a new business
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Register", "From": "whatsapp:+254700123456"}'

# 3. Enter your name
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "John Doe", "From": "whatsapp:+254700123456"}'

# 4. Enter email
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "john@example.com", "From": "whatsapp:+254700123456"}'

# 5. Enter business name
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "My Test Shop", "From": "whatsapp:+254700123456"}'

# 6. Enter password
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "password123", "From": "whatsapp:+254700123456"}'

# 7. View main menu
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{"Body": "Menu", "From": "whatsapp:+254700123456"}'
```

### Interactive Testing Script

Or use the automated test script:

```bash
cd /home/billy/Desktop/back-up/POS\ -\ 2
./scripts/test-whatsapp-comprehensive.sh
```

---

## Option 2: Using Postman or Insomnia (Visual Testing)

1. **Open Postman/Insomnia**
2. **Create a new POST request**:
   - URL: `http://127.0.0.1:8000/api/whatsapp/webhook`
   - Method: `POST`
   - Headers: `Content-Type: application/json`
   - Body (raw JSON):
     ```json
     {
       "Body": "Hi",
       "From": "whatsapp:+254700123456"
     }
     ```
3. **Send the request** and see the bot's response
4. **Continue the conversation** by changing the "Body" field

---

## Option 3: Using Real WhatsApp (Twilio Sandbox - Free)

### Step 1: Set Up Twilio Sandbox

1. Go to [Twilio Console](https://www.twilio.com/console)
2. Sign up for a free account
3. Navigate to: **Messaging** → **Try it out** → **Send a WhatsApp message**
4. You'll see a sandbox number (e.g., `+1 415 523 8886`)
5. Join the sandbox by sending the join code (e.g., "join <word>") to that number from your WhatsApp

### Step 2: Expose Your Local Server

Since your bot is running locally, you need to expose it to the internet. Use **ngrok**:

```bash
# Install ngrok (if not installed)
# Download from: https://ngrok.com/download

# Run ngrok to expose port 8000
ngrok http 8000
```

You'll get a public URL like: `https://abc123.ngrok.io`

### Step 3: Configure Twilio Webhook

1. In Twilio Console, go to your WhatsApp Sandbox settings
2. Set the **"When a message comes in"** webhook to:
   ```
   https://abc123.ngrok.io/api/whatsapp/webhook
   ```
3. Method: `POST`
4. Save the configuration

### Step 4: Test on Real WhatsApp!

Now send messages to the Twilio sandbox number from your WhatsApp:
- Send: `Hi`
- The bot will respond!
- Follow the conversation flow

---

## Option 4: Deploy to Production (Meta WhatsApp Business API)

For production use with your own WhatsApp Business number:

1. **Set up Meta WhatsApp Business Account**
2. **Get API credentials**
3. **Configure webhook** to point to your production server
4. **Add authentication** (verify webhook signature)

---

## Testing Workflows

Once connected, try these conversation flows:

### Registration Flow
```
You: Hi
Bot: Welcome! 1) Login 2) Register
You: Register
Bot: Enter your Full Name:
You: John Doe
Bot: Enter your Email:
You: john@test.com
Bot: Enter Business Name:
You: My Shop
Bot: Choose a Password:
You: password123
Bot: Registration Complete!
```

### Sales Flow
```
You: Menu
Bot: [Shows main menu]
You: 1
Bot: [Sales menu]
You: 1
Bot: Enter product name or SKU:
You: [Product name]
Bot: Added! Add another or type 'Done'
You: Done
Bot: Select customer...
```

### Check Inventory
```
You: Menu
You: 2
Bot: [Inventory menu]
You: 3
Bot: [Shows stock levels]
```

### View Reports
```
You: Menu
You: 5
Bot: [Reports menu]
You: 1
Bot: [Shows sales report with today's and monthly stats]
```

---

## Quick Reference

**Global Commands** (work anywhere):
- `Hi` - Start/restart
- `Menu` - Main menu
- `Cancel` - Cancel current operation
- `Logout` - End session
- `Help` - Show help

**Phone Number Format**: Use any format, e.g., `whatsapp:+254700123456`

---

## Troubleshooting

**Server not running?**
```bash
cd /home/billy/Desktop/back-up/POS\ -\ 2
php artisan serve --port=8000
```

**Clear cache if needed:**
```bash
php artisan cache:clear
```

**Check logs:**
```bash
tail -f storage/logs/laravel.log
```
