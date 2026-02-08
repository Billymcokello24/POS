#!/bin/bash

# Base URL - adjust if port differs
URL="http://127.0.0.1:8000/api/whatsapp/webhook"
PHONE="254700000001" # Test Phone Number

echo "---------------------------------------------------"
echo "🧪 Testing WhatsApp Bot Flow"
echo "---------------------------------------------------"

# Helper function to send message
send_message() {
    local msg="$1"
    echo -e "\n📨 Sending: '$msg'"
    curl -s -X POST "$URL" \
        -H "Content-Type: application/json" \
        -d "{\"Body\": \"$msg\", \"From\": \"whatsapp:$PHONE\"}" | jq .
}

# 1. Reset State (Cancel)
send_message "Cancel"

# 2. Test Registration Flow
echo -e "\n--- 1. Testing Registration ---"
send_message "Register"
send_message "Bot Tester"       # Name
send_message "bot@test.com"     # Email
send_message "Bot Business"     # Business Name
send_message "password123"      # Password

# 3. Test Login Flow
echo -e "\n--- 2. Testing Login ---"
send_message "Logout"
send_message "Login"
send_message "bot@test.com"     # Email
send_message "password123"      # Password

# 4. Test Dashboard
echo -e "\n--- 3. Testing Dashboard ---"
send_message "Dashboard"

echo -e "\n✅ Test Complete"
