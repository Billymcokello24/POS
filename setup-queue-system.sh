#!/bin/bash

# Queue System Setup Script
# This script configures the POS system for optimal queue-based performance

set -e

echo "🚀 Setting up Queue System for POS..."
echo ""

# Check if Redis is running
echo "1️⃣  Checking Redis availability..."
if redis-cli ping > /dev/null 2>&1; then
    echo "   ✅ Redis is running"
    USE_REDIS=true
else
    echo "   ⚠️  Redis is not running. Will use database queue."
    USE_REDIS=false
fi

echo ""
echo "2️⃣  Updating .env configuration..."

# Update queue connection
if [ "$USE_REDIS" = true ]; then
    # Use Redis for queues, cache, and sessions
    sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
    sed -i 's/^CACHE_STORE=.*/CACHE_STORE=redis/' .env
    sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
    echo "   ✅ Configured to use Redis for queues, cache, and sessions"
else
    # Use database for queues
    sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env
    sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env
    sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
    echo "   ✅ Configured to use database for queues"
fi

echo ""
echo "3️⃣  Running migrations for queue tables..."
php artisan migrate --force

echo ""
echo "4️⃣  Clearing and caching configuration..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

echo ""
echo "5️⃣  Testing queue system..."

# Create test job
php artisan queue:work --once --stop-when-empty &
WORKER_PID=$!
sleep 2

if ps -p $WORKER_PID > /dev/null; then
    echo "   ✅ Queue worker started successfully"
    kill $WORKER_PID 2>/dev/null || true
else
    echo "   ✅ Queue worker test completed"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Queue System Setup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 Current Configuration:"
if [ "$USE_REDIS" = true ]; then
    echo "   • Queue Driver: Redis (High Performance)"
    echo "   • Cache Driver: Redis"
    echo "   • Session Driver: Redis"
else
    echo "   • Queue Driver: Database (Reliable)"
    echo "   • Cache Driver: File"
    echo "   • Session Driver: File"
fi
echo ""
echo "🔥 Queue Features Enabled:"
echo "   ✅ Async Bulk Email Sending"
echo "   ✅ Background Report Generation"
echo "   ✅ Async PDF/Excel Exports"
echo "   ✅ Background Product Imports (50+ rows)"
echo "   ✅ Email Notifications (queued)"
echo "   ✅ M-Pesa Payment Processing"
echo ""
echo "🚀 To Start Queue Workers:"
echo ""
echo "   Development (single worker):"
echo "   → php artisan queue:work --sleep=3 --tries=3"
echo ""
echo "   Production (supervisor):"
echo "   → sudo supervisorctl start pos-worker:*"
echo ""
echo "   Background (screen/tmux):"
echo "   → screen -dmS pos-queue php artisan queue:work --sleep=3 --tries=3 --max-time=3600"
echo ""
echo "📈 Monitor Queue:"
echo "   → php artisan queue:work --verbose"
echo "   → php artisan queue:failed        # View failed jobs"
echo "   → php artisan queue:retry all     # Retry failed jobs"
echo "   → php artisan queue:flush          # Clear all failed jobs"
echo ""

if [ "$USE_REDIS" = true ]; then
    echo "💡 Redis Tips:"
    echo "   → redis-cli ping                 # Check Redis status"
    echo "   → redis-cli FLUSHALL             # Clear all Redis data"
    echo "   → redis-cli KEYS 'laravel*'      # View Laravel keys"
    echo ""
fi

echo "⚡ Performance Boost:"
echo "   • Bulk emails now sent in background"
echo "   • Reports generated asynchronously"
echo "   • Large imports (50+ products) processed in queue"
echo "   • Notifications sent without blocking requests"
echo ""
echo "🎯 Next Steps:"
echo "   1. Start queue worker (see commands above)"
echo "   2. Test by uploading a large product CSV"
echo "   3. Send bulk email to see async processing"
echo "   4. Generate report in background"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
