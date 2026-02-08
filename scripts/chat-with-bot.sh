#!/bin/bash

# Interactive WhatsApp Bot Tester
# This script lets you chat with the bot interactively

URL="http://127.0.0.1:8000/api/whatsapp/webhook"
PHONE="whatsapp:+254700999888"  # Your test phone number

echo "=================================================="
echo "💬 WhatsApp Bot Interactive Tester"
echo "=================================================="
echo ""
echo "Server: $URL"
echo "Phone: $PHONE"
echo ""
echo "Type your messages and press Enter to send."
echo "Type 'quit' or 'exit' to stop."
echo ""
echo "=================================================="
echo ""

# Send initial Hi message
echo "🤖 Bot: Sending 'Hi' to start..."
response=$(curl -s -X POST "$URL" \
    -H "Content-Type: application/json" \
    -d "{\"Body\": \"Hi\", \"From\": \"$PHONE\"}")
echo "$response" | jq -r '.message' 2>/dev/null || echo "$response"
echo ""

# Interactive loop
while true; do
    echo -n "You: "
    read user_input
    
    # Check for exit
    if [[ "$user_input" == "quit" ]] || [[ "$user_input" == "exit" ]]; then
        echo "Goodbye! 👋"
        break
    fi
    
    # Skip empty input
    if [[ -z "$user_input" ]]; then
        continue
    fi
    
    # Send message to bot
    response=$(curl -s -X POST "$URL" \
        -H "Content-Type: application/json" \
        -d "{\"Body\": \"$user_input\", \"From\": \"$PHONE\"}")
    
    # Display bot response
    echo ""
    echo "🤖 Bot:"
    echo "$response" | jq -r '.message' 2>/dev/null || echo "$response"
    echo ""
done
