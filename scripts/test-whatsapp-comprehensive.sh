#!/bin/bash

# Comprehensive WhatsApp Bot Testing Script
# Tests all major workflows including Registration, Login, Sales, Inventory, Reports

URL="http://127.0.0.1:8000/api/whatsapp/webhook"
PHONE="254700000999" # Test Phone Number

echo "=================================================="
echo "🧪 WhatsApp Bot Comprehensive Testing"
echo "=================================================="

# Helper function to send message
send_message() {
    local msg="$1"
    local label="$2"
    echo -e "\n📨 ${label}: '$msg'"
    response=$(curl -s -X POST "$URL" \
        -H "Content-Type: application/json" \
        -d "{\"Body\": \"$msg\", \"From\": \"whatsapp:$PHONE\"}")
    echo "$response" | jq -r '.message' 2>/dev/null || echo "$response"
    sleep 1
}

# Reset state
send_message "Cancel" "Reset"

echo -e "\n=================================================="
echo "TEST 1: Registration Flow"
echo "=================================================="
send_message "Hi" "Welcome"
send_message "Register" "Start Registration"
send_message "Test User" "Enter Name"
send_message "testuser@whatsapp.test" "Enter Email"
send_message "WhatsApp Test Business" "Enter Business Name"
send_message "password123" "Enter Password"

echo -e "\n=================================================="
echo "TEST 2: Login Flow (After Logout)"
echo "=================================================="
send_message "Logout" "Logout"
send_message "Login" "Start Login"
send_message "testuser@whatsapp.test" "Enter Email"
send_message "password123" "Enter Password"

echo -e "\n=================================================="
echo "TEST 3: Main Menu Navigation"
echo "=================================================="
send_message "Menu" "Show Menu"

echo -e "\n=================================================="
echo "TEST 4: Sales Workflow - New Sale"
echo "=================================================="
send_message "1" "Select Sales"
send_message "1" "New Sale"
send_message "Done" "Try empty cart (should error)"
send_message "Cancel" "Cancel Sale"

echo -e "\n=================================================="
echo "TEST 5: Inventory Workflow"
echo "=================================================="
send_message "Menu" "Back to Menu"
send_message "2" "Select Inventory"
send_message "3" "Check Stock"

echo -e "\n=================================================="
echo "TEST 6: Customer Management"
echo "=================================================="
send_message "Menu" "Back to Menu"
send_message "Customers" "Select Customers"
send_message "1" "View Customers"

echo -e "\n=================================================="
echo "TEST 7: Reports - Sales Report"
echo "=================================================="
send_message "Menu" "Back to Menu"
send_message "5" "Select Reports"
send_message "1" "Sales Report"

echo -e "\n=================================================="
echo "TEST 8: Dashboard Quick Access"
echo "=================================================="
send_message "Dashboard" "View Dashboard"

echo -e "\n=================================================="
echo "TEST 9: Help System"
echo "=================================================="
send_message "Menu" "Back to Menu"
send_message "8" "Select Help"

echo -e "\n=================================================="
echo "TEST 10: Subscription Status"
echo "=================================================="
send_message "Menu" "Back to Menu"
send_message "6" "Select Payments/Subscriptions"

echo -e "\n=================================================="
echo "TEST 11: Error Handling"
echo "=================================================="
send_message "InvalidCommand" "Invalid Command"
send_message "Menu" "Recover to Menu"

echo -e "\n=================================================="
echo "TEST 12: Logout"
echo "=================================================="
send_message "Logout" "Logout"
send_message "Menu" "Try Menu (should fail)"

echo -e "\n=================================================="
echo "✅ All Tests Complete!"
echo "=================================================="
