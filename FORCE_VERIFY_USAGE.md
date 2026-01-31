# Force Subscription Verification Script

## Overview

The `subscriptions:force-verify` command scans all successful M-Pesa payments and ensures they are properly linked to active, verified subscriptions. This is useful for:

- Reconciling subscriptions after system downtime
- Fixing subscriptions stuck in pending state due to missed callbacks
- Verifying all payments are properly activated

## Usage

### Basic Usage

```bash
php artisan subscriptions:force-verify
```

This will:
- Check all successful M-Pesa payments from the last 30 days
- Activate any pending subscriptions
- Display detailed statistics

### Options

#### Dry Run Mode (Recommended First)

```bash
php artisan subscriptions:force-verify --dry-run
```

Shows what would be done without making any changes. Always run this first to preview the results.

#### Custom Date Range

```bash
# Check last 7 days
php artisan subscriptions:force-verify --days=7

# Check last 90 days
php artisan subscriptions:force-verify --days=90
```

#### Check All Time

```bash
php artisan subscriptions:force-verify --all
```

Checks all successful payments regardless of date.

#### Combined Options

```bash
# Dry run for all time
php artisan subscriptions:force-verify --dry-run --all

# Dry run for last 7 days
php artisan subscriptions:force-verify --dry-run --days=7
```

## Output Example

```
╔════════════════════════════════════════════════════════════╗
║   Force Subscription Verification & Activation Sweep      ║
╚════════════════════════════════════════════════════════════╝

📊 Scanning for successful M-Pesa payments...
   Filtering: Last 30 days
   Found: 15 successful payments

🔄 Processing payments...
[████████████████████████████] 15/15

╔════════════════════════════════════════════════════════════╗
║                      RESULTS SUMMARY                       ║
╚════════════════════════════════════════════════════════════╝

+----------------------------+-------+
| Metric                     | Count |
+----------------------------+-------+
| Total Payments Checked     | 15    |
| Already Active/Verified    | 10    |
| Successfully Activated     | 4     |
| Failed to Activate         | 0     |
| Skipped (Not Subscription) | 1     |
+----------------------------+-------+

📋 Detailed Results:

+--------+----------------------+----------------+-------------+-------------+
| Status | Checkout ID          | Receipt        | Amount      | Business ID |
+--------+----------------------+----------------+-------------+-------------+
| ✅     | ws_CO_30012026...    | SBK1A2B3C4     | KES 2,500   | 5           |
| ✅     | ws_CO_30012026...    | SBK2D5E6F7     | KES 5,000   | 8           |
| ✅     | ws_CO_30012026...    | SBK3G8H9I0     | KES 10,000  | 12          |
| ✅     | ws_CO_30012026...    | SBK4J1K2L3     | KES 2,500   | 15          |
+--------+----------------------+----------------+-------------+-------------+

✅ Sweep complete!
```

## When to Use

### Recommended Scenarios

1. **After System Maintenance**
   - Run after server downtime to catch any missed callbacks
   
2. **Daily Reconciliation**
   - Schedule as a cron job to run daily
   ```bash
   # Add to Laravel scheduler
   $schedule->command('subscriptions:force-verify --days=1')->daily();
   ```

3. **Manual Troubleshooting**
   - When a customer reports payment was successful but subscription not activated
   - Use `--dry-run` first to see what would happen

4. **Initial Migration**
   - When first deploying the auto-activation system
   - Use `--all` to process all historical payments

### Safety Notes

- ✅ **Safe to run multiple times** - Already activated subscriptions are skipped
- ✅ **Idempotent** - Running multiple times won't create duplicates
- ✅ **Logged** - All errors are logged to Laravel logs
- ⚠️ **Always test with --dry-run first**

## Troubleshooting

### No Payments Found

```
No successful payments found to process.
```

**Possible causes:**
- No successful M-Pesa payments in the date range
- All payments already activated
- Try `--all` to check all time

### Failed to Activate

```
❌ Payment failed to activate
```

**Check:**
1. Laravel logs for detailed error messages
2. Ensure payment has `metadata.type = 'subscription'`
3. Verify business_id exists
4. Check if plan_id is valid

### Skipped Payments

```
Skipped (Not Subscription): 5
```

**Explanation:**
- These are M-Pesa payments for sales, not subscriptions
- Normal behavior - only subscription payments are processed
