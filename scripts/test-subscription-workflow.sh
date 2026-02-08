#!/bin/bash

# Test Subscription Workflow Fix
# This script tests that the subscription workflow maintains state correctly

BASE_URL="http://localhost:8000/api/whatsapp/webhook"
PHONE="whatsapp:+254759814390"

echo "🧪 Testing Subscription Workflow State Management"
echo "=================================================="
echo ""

# Helper function to send message
send_message() {
    local body="$1"
    local label="$2"
    
    echo "📤 Sending: $label ($body)"
    response=$(curl -s -X POST "$BASE_URL" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "Body=$body&From=$PHONE" 2>&1)
    
    # Extract message content
    message=$(echo "$response" | grep -oP '(?<=<Message>).*?(?=</Message>)' | head -1)
    echo "📥 Response:"
    echo "$message" | head -20
    echo ""
    echo "---"
    echo ""
    sleep 1
}

# Test Flow
echo "Step 1: Start conversation"
send_message "Hi" "Start"

echo "Step 2: Login (assuming user is already logged in)"
send_message "1" "Login option"

echo "Step 3: Select Subscriptions from main menu"
send_message "6" "Subscriptions"

echo "Step 4: Select 'View Available Plans' (option 2)"
send_message "2" "View Plans"

echo "Step 5: Select a plan (option 2 - Medium Business)"
send_message "2" "Select Plan 2"

echo "✅ Test Complete!"
echo ""
echo "Expected: Plan details should be shown"
echo "Bug: If it shows 'Inventory Management', the workflow state was lost"
