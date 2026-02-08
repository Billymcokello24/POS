#!/bin/bash

# Business Delete Functionality Test Script
# Run this after attempting to delete a business to verify it worked

echo "==================================================================="
echo "Business Delete Functionality Test"
echo "==================================================================="
echo ""

# Check if business ID is provided
if [ -z "$1" ]; then
    echo "Usage: ./test_delete.sh <business_id>"
    echo "Example: ./test_delete.sh 5"
    exit 1
fi

BUSINESS_ID=$1

echo "Testing deletion of Business ID: $BUSINESS_ID"
echo ""

# Check Laravel logs for deletion activity
echo "--- Checking Laravel Logs for Deletion Activity ---"
echo ""
tail -50 storage/logs/laravel.log | grep -A 5 "Business deletion" | tail -20
echo ""

# Check if business still exists in database
echo "--- Checking if Business Exists in Database ---"
echo ""
php artisan tinker --execute="
\$business = \App\Models\Business::withTrashed()->find($BUSINESS_ID);
if (\$business) {
    echo 'Business FOUND in database: ' . \$business->name . '\n';
    echo 'Deleted at: ' . (\$business->deleted_at ?: 'NOT DELETED') . '\n';
} else {
    echo '✅ Business ID $BUSINESS_ID completely REMOVED from database\n';
}
"
echo ""

# Check for orphaned related records
echo "--- Checking for Orphaned Records ---"
echo ""
php artisan tinker --execute="
echo 'Products: ' . \App\Models\Product::where('business_id', $BUSINESS_ID)->count() . '\n';
echo 'Sales: ' . \App\Models\Sale::where('business_id', $BUSINESS_ID)->count() . '\n';
echo 'Categories: ' . \App\Models\Category::where('business_id', $BUSINESS_ID)->count() . '\n';
echo 'Role_User entries: ' . DB::table('role_user')->where('business_id', $BUSINESS_ID)->count() . '\n';
"
echo ""

echo "==================================================================="
echo "Test Complete!"
echo "==================================================================="
echo ""
echo "✅ If business is NOT FOUND and all counts are 0, deletion was SUCCESSFUL"
echo "❌ If business still exists or counts > 0, deletion FAILED"
echo ""
