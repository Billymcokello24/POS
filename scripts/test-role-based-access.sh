#!/bin/bash

# Role-Based Access Control Testing Script
# Tests different user roles and their permissions

URL="http://127.0.0.1:8000/api/whatsapp/webhook"

# Helper function to send message
send_message() {
    local msg="$1"
    local phone="$2"
    local label="$3"
    echo -e "\n📨 ${label}: '$msg'"
    response=$(curl -s -X POST "$URL" \
        -H "Content-Type: application/json" \
        -d "{\"Body\": \"$msg\", \"From\": \"whatsapp:$phone\"}")
    echo "$response" | jq -r '.message' 2>/dev/null || echo "$response"
    sleep 1
}

echo "=================================================="
echo "🧪 Role-Based Access Control Testing"
echo "=================================================="

# Test 1: Business Admin (Full Access)
echo -e "\n=================================================="
echo "TEST 1: Business Admin - Full Access"
echo "=================================================="
ADMIN_PHONE="254700111111"

send_message "Hi" "$ADMIN_PHONE" "Welcome"
send_message "Login" "$ADMIN_PHONE" "Start Login"
send_message "testuser@whatsapp.test" "$ADMIN_PHONE" "Enter Email"
send_message "password123" "$ADMIN_PHONE" "Enter Password"
send_message "Menu" "$ADMIN_PHONE" "Show Menu (should show all options)"
send_message "1" "$ADMIN_PHONE" "Access Sales (should work)"
send_message "Menu" "$ADMIN_PHONE" "Back to Menu"
send_message "4" "$ADMIN_PHONE" "Access Staff (should work for admin)"
send_message "Menu" "$ADMIN_PHONE" "Back to Menu"
send_message "Logout" "$ADMIN_PHONE" "Logout"

# Test 2: Create and test Cashier role
echo -e "\n=================================================="
echo "TEST 2: Cashier - Limited Access"
echo "=================================================="
echo "Note: You need to create a cashier user in the database first"
echo "Run: php artisan tinker"
echo "Then create cashier user with role 'cashier'"

# Test 3: Create and test Manager role
echo -e "\n=================================================="
echo "TEST 3: Manager - Moderate Access"
echo "=================================================="
echo "Note: You need to create a manager user in the database first"

# Test 4: Create and test Stock Clerk role
echo -e "\n=================================================="
echo "TEST 4: Stock Clerk - Inventory Only"
echo "=================================================="
echo "Note: You need to create a stock clerk user in the database first"

echo -e "\n=================================================="
echo "✅ Role Testing Guide Complete!"
echo "=================================================="
echo ""
echo "To test different roles, you need to:"
echo "1. Create users with different roles in the database"
echo "2. Assign them to a business with the appropriate role"
echo "3. Test login and menu access for each role"
echo ""
echo "Expected behavior:"
echo "- Admin: Sees all 8-9 menu options"
echo "- Manager: Sees Sales, Inventory, Customers, Reports, Help (5-6 options)"
echo "- Cashier: Sees Sales, View Products, Customers, Help (3-4 options)"
echo "- Stock Clerk: Sees Inventory, Products, Help (2-3 options)"
echo "- SuperAdmin: Sees Business Mgmt, Subscriptions, Support, Reports, Admins"
