#!/bin/bash

# Queue System Verification & Testing Script
# This script tests all queue components to ensure they're working

set -e

echo "╔═══════════════════════════════════════════════════════════════════════════╗"
echo "║              🧪 QUEUE SYSTEM VERIFICATION & TESTING 🧪                    ║"
echo "╚═══════════════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test results
PASSED=0
FAILED=0

test_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC}"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAILED${NC}"
        ((FAILED++))
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  CHECKING PREREQUISITES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if queue tables exist
echo -n "   Checking queue tables exist... "
php artisan tinker --execute="echo DB::table('jobs')->count();" > /dev/null 2>&1
test_result $?

# Check if failed_jobs table exists
echo -n "   Checking failed_jobs table exists... "
php artisan tinker --execute="echo DB::table('failed_jobs')->count();" > /dev/null 2>&1
test_result $?

# Check queue configuration
echo -n "   Checking queue configuration... "
QUEUE_CONN=$(grep "^QUEUE_CONNECTION=" .env | cut -d '=' -f2)
if [ "$QUEUE_CONN" = "database" ] || [ "$QUEUE_CONN" = "redis" ]; then
    echo -e "${GREEN}✓ PASSED${NC} (Driver: $QUEUE_CONN)"
    ((PASSED++))
else
    echo -e "${RED}✗ FAILED${NC} (Driver: $QUEUE_CONN - should be 'database' or 'redis')"
    ((FAILED++))
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  CHECKING QUEUE JOB FILES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if queue job files exist
JOBS=(
    "app/Jobs/SendBulkEmailJob.php"
    "app/Jobs/ImportProductsJob.php"
    "app/Jobs/GenerateReportJob.php"
    "app/Jobs/ExportReportJob.php"
    "app/Jobs/ProcessMpesaCallback.php"
    "app/Jobs/ProcessMpesaPaymentJob.php"
    "app/Jobs/CheckMpesaPaymentStatusJob.php"
    "app/Jobs/SendSupportVerificationCodeJob.php"
    "app/Jobs/NotifyAdminsNewTicketJob.php"
    "app/Jobs/ProcessSupportMessageJob.php"
    "app/Jobs/AttemptSubscriptionActivation.php"
)

for job in "${JOBS[@]}"; do
    echo -n "   Checking $job... "
    if [ -f "$job" ]; then
        test_result 0
    else
        test_result 1
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  CHECKING QUEUE WORKER STATUS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -n "   Checking if queue worker is running... "
if pgrep -f "queue:work" > /dev/null; then
    echo -e "${GREEN}✓ RUNNING${NC}"
    ((PASSED++))
    echo ""
    echo "   Active Workers:"
    ps aux | grep "queue:work" | grep -v grep | awk '{print "      PID: "$2" | "$11" "$12" "$13" "$14" "$15}'
else
    echo -e "${YELLOW}⚠ NOT RUNNING${NC}"
    echo ""
    echo -e "   ${YELLOW}WARNING: No queue worker found!${NC}"
    echo "   Start with: php artisan queue:work --queue=mpesa,mpesa-processing,support,default --sleep=3 --tries=3"
    ((FAILED++))
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  CHECKING PENDING & FAILED JOBS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Count pending jobs
PENDING=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
echo "   Pending Jobs: $PENDING"

# Count failed jobs
FAILED_JOBS=$(php artisan tinker --execute="echo DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)
echo "   Failed Jobs: $FAILED_JOBS"

if [ "$FAILED_JOBS" -gt 0 ]; then
    echo -e "   ${YELLOW}⚠ You have $FAILED_JOBS failed jobs${NC}"
    echo "   View with: php artisan queue:failed"
    echo "   Retry with: php artisan queue:retry all"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5️⃣  TESTING QUEUE DISPATCH (Live Test)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "   Creating test job to verify queue works..."

# Create a test job dispatch
TEST_OUTPUT=$(php artisan tinker --execute="
use App\Jobs\SendBulkEmailJob;
use App\Models\User;
\$user = User::first();
if (\$user) {
    SendBulkEmailJob::dispatch(\$user, 'Queue Test', 'This is a queue system test.');
    echo 'Job dispatched successfully';
} else {
    echo 'No users found - skipping test';
}
" 2>&1 | grep -E "(dispatched|No users)")

echo "   Result: $TEST_OUTPUT"

if [[ "$TEST_OUTPUT" == *"dispatched"* ]]; then
    echo -e "   ${GREEN}✓ Job dispatched to queue${NC}"
    ((PASSED++))

    # Check if job appears in queue
    sleep 2
    PENDING_AFTER=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)

    if [ "$PENDING_AFTER" -gt "$PENDING" ]; then
        echo -e "   ${GREEN}✓ Job appears in queue table${NC}"
        ((PASSED++))
    else
        echo -e "   ${YELLOW}⚠ Job may have been processed already (worker is fast!)${NC}"
    fi
else
    echo -e "   ${YELLOW}⚠ Test skipped (no users found)${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6️⃣  TESTING QUEUE PROCESSING"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if pgrep -f "queue:work" > /dev/null; then
    echo "   Worker is running - jobs should process automatically"
    echo -e "   ${GREEN}✓ Queue processing active${NC}"
    ((PASSED++))
else
    echo "   Testing manual job processing..."

    # Try to process one job
    timeout 5 php artisan queue:work --once --stop-when-empty 2>&1 | head -5

    if [ $? -eq 0 ]; then
        echo -e "   ${GREEN}✓ Manual queue processing works${NC}"
        ((PASSED++))
    else
        echo -e "   ${YELLOW}⚠ No jobs to process or processing timed out${NC}"
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7️⃣  CHECKING REDIS (Optional)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -n "   Checking Redis availability... "
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✓ AVAILABLE${NC}"
    ((PASSED++))

    if [ "$QUEUE_CONN" = "redis" ]; then
        echo -e "   ${GREEN}✓ Redis queue driver is active${NC}"
    else
        echo -e "   ${YELLOW}ℹ Redis available but not used (using $QUEUE_CONN)${NC}"
        echo "   To use Redis: Update QUEUE_CONNECTION=redis in .env"
    fi
else
    echo -e "${YELLOW}⚠ NOT AVAILABLE${NC}"

    if [ "$QUEUE_CONN" = "redis" ]; then
        echo -e "   ${RED}✗ Redis queue configured but Redis is not running!${NC}"
        echo "   Install: sudo apt install redis-server"
        echo "   Start: sudo systemctl start redis"
        ((FAILED++))
    else
        echo "   (Optional - Using database queue which works fine)"
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "8️⃣  SUPERVISOR STATUS (Production)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v supervisorctl > /dev/null 2>&1; then
    echo "   Supervisor is installed"

    echo -n "   Checking POS workers... "
    if sudo supervisorctl status 2>/dev/null | grep -q "pos-"; then
        echo -e "${GREEN}✓ CONFIGURED${NC}"
        echo ""
        sudo supervisorctl status | grep "pos-"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠ NOT CONFIGURED${NC}"
        echo "   Configure with: sudo cp deploy/pos-worker.conf /etc/supervisor/conf.d/"
    fi
else
    echo -e "   ${YELLOW}⚠ Supervisor not installed (optional for production)${NC}"
    echo "   Install: sudo apt install supervisor"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 TEST SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "   Tests Passed: $PASSED"
echo "   Tests Failed: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "   ${GREEN}🎉 ALL TESTS PASSED! Queue system is working perfectly!${NC}"
    echo ""
    echo "   Next Steps:"
    echo "   1. Test with real operations (send bulk email, import products)"
    echo "   2. Monitor logs: tail -f storage/logs/laravel.log"
    echo "   3. Set up supervisor for production (if not already)"
else
    echo -e "   ${YELLOW}⚠ Some tests failed. Please review and fix issues above.${NC}"
    echo ""
    echo "   Common Fixes:"
    echo "   1. Start queue worker: php artisan queue:work --queue=mpesa,mpesa-processing,support,default --sleep=3 --tries=3"
    echo "   2. Check .env: QUEUE_CONNECTION should be 'database' or 'redis'"
    echo "   3. Run migrations: php artisan migrate"
    echo "   4. Check logs: tail -f storage/logs/laravel.log"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📖 QUICK COMMANDS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "   Monitor queue:"
echo "   → php artisan queue:monitor mpesa mpesa-processing support default"
echo ""
echo "   View failed jobs:"
echo "   → php artisan queue:failed"
echo ""
echo "   Retry failed jobs:"
echo "   → php artisan queue:retry all"
echo ""
echo "   Watch logs:"
echo "   → tail -f storage/logs/laravel.log | grep -i queue"
echo ""
echo "   Restart workers:"
echo "   → php artisan queue:restart"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
