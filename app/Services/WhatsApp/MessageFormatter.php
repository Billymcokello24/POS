<?php

namespace App\Services\WhatsApp;

class MessageFormatter
{
    /**
     * Format the main menu.
     */
    public function formatMainMenu(bool $hasMultipleBusinesses = false): string
    {
        $menu = "🏪 *ModernPOS Main Menu*\n\n";
        $menu .= "Please select an operation:\n\n";
        $menu .= "1️⃣ Sales\n";
        $menu .= "2️⃣ Inventory\n";
        $menu .= "3️⃣ Customers\n";
        $menu .= "4️⃣ Staff Management\n";
        $menu .= "5️⃣ Reports\n";
        $menu .= "6️⃣ Payments/Subscriptions\n";
        $menu .= "7️⃣ Account Settings\n";
        $menu .= "8️⃣ Help/Support\n";
        
        if ($hasMultipleBusinesses) {
            $menu .= "9️⃣ Switch Business\n";
        }
        
        $menu .= "0️⃣ Logout\n\n";
        $menu .= "_Type the number or name (e.g., '1' or 'Sales')_";
        
        return $menu;
    }

    /**
     * Format role-based menu with dynamic options.
     */
    public function formatRoleBasedMenu(string $userName, ?string $businessName, string $roleName, string $roleBadge, array $options): string
    {
        $menu = "Welcome, *{$userName}*! 👋\n";
        
        if ($businessName) {
            $menu .= "Business: *{$businessName}*\n";
        }
        
        $menu .= "Role: {$roleName} {$roleBadge}\n\n";
        $menu .= "🏪 *ModernPOS Main Menu*\n\n";
        $menu .= "Please select an operation:\n\n";
        
        foreach ($options as $number => $option) {
            $emoji = $this->getNumberEmoji($number);
            $menu .= "{$emoji} {$option['name']}\n";
        }
        
        $menu .= "0️⃣ Logout\n\n";
        $menu .= "_Type the number or name_";
        
        return $menu;
    }

    /**
     * Get number emoji for menu.
     */
    private function getNumberEmoji(string $number): string
    {
        $emojis = [
            '1' => '1️⃣', '2' => '2️⃣', '3' => '3️⃣', '4' => '4️⃣', '5' => '5️⃣',
            '6' => '6️⃣', '7' => '7️⃣', '8' => '8️⃣', '9' => '9️⃣', '0' => '0️⃣'
        ];
        
        return $emojis[$number] ?? $number . '.';
    }

    /**
     * Format a submenu.
     */
    public function formatSubmenu(string $title, array $options): string
    {
        $menu = "📋 *{$title}*\n\n";
        
        foreach ($options as $index => $option) {
            $number = $index + 1;
            $menu .= "{$number}️⃣ {$option}\n";
        }
        
        $menu .= "0️⃣ Back to Main Menu\n\n";
        $menu .= "_Type the number or option name_";
        
        return $menu;
    }

    /**
     * Format a list with pagination.
     */
    public function formatList(string $title, array $items, int $page = 1, int $perPage = 5): string
    {
        $total = count($items);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        $list = "📄 *{$title}*\n\n";
        
        foreach ($pageItems as $index => $item) {
            $number = $offset + $index + 1;
            $list .= "{$number}. {$item}\n";
        }
        
        $list .= "\n_Page {$page} of {$totalPages}_\n";
        
        if ($page < $totalPages) {
            $list .= "Type 'Next' for more\n";
        }
        if ($page > 1) {
            $list .= "Type 'Previous' to go back\n";
        }
        
        $list .= "Type '0' to return to menu";
        
        return $list;
    }

    /**
     * Format a success message.
     */
    public function formatSuccess(string $message): string
    {
        return "✅ *Success*\n\n{$message}";
    }

    /**
     * Format an error message.
     */
    public function formatError(string $message): string
    {
        return "❌ *Error*\n\n{$message}\n\nType 'Cancel' to abort or try again.";
    }

    /**
     * Format a multi-step form step.
     */
    public function formatStep(string $title, int $currentStep, int $totalSteps, string $prompt): string
    {
        $stepInfo = "📝 *{$title}* (Step {$currentStep}/{$totalSteps})\n\n";
        return $stepInfo . $prompt . "\n\n_Type 'Cancel' to abort_";
    }

    /**
     * Format a form confirmation.
     */
    public function formatFormConfirmation(string $title, array $data): string
    {
        $text = "📋 *Confirm {$title}*\n\n";
        foreach ($data as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $text .= "*{$label}:* {$value}\n";
        }
        $text .= "\nDoes everything look correct?\n";
        $text .= "Type *'Yes'* to save or *'No'* to restart.";
        return $text;
    }

    /**
     * Format a confirmation prompt.
     */
    public function formatConfirmation(string $message, array $details = []): string
    {
        $text = "⚠️ *Confirmation Required*\n\n{$message}\n\n";
        
        if (!empty($details)) {
            foreach ($details as $key => $value) {
                $text .= "*{$key}:* {$value}\n";
            }
            $text .= "\n";
        }
        
        $text .= "Type 'Yes' to confirm or 'No' to cancel.";
        
        return $text;
    }

    /**
     * Format a welcome message.
     */
    public function formatWelcome(string $userName = null): string
    {
        $greeting = $userName ? "Welcome back, *{$userName}*! 👋" : "Welcome to *ModernPOS*! 👋";
        
        return "{$greeting}\n\nYour business management assistant is ready.\n\nType 'Menu' to see available options.";
    }

    /**
     * Format a sales receipt.
     */
    public function formatReceipt(array $sale): string
    {
        $receipt = "🧾 *Sales Receipt*\n\n";
        $receipt .= "Receipt #: {$sale['sale_number']}\n";
        $receipt .= "Date: {$sale['date']}\n";
        $receipt .= "Customer: {$sale['customer']}\n\n";
        $receipt .= "*Items:*\n";
        
        foreach ($sale['items'] as $item) {
            $receipt .= "• {$item['name']} x{$item['quantity']} - {$item['total']}\n";
        }
        
        $receipt .= "\n*Subtotal:* {$sale['subtotal']}\n";
        if (isset($sale['tax'])) {
            $receipt .= "*Tax:* {$sale['tax']}\n";
        }
        $receipt .= "*Total:* {$sale['total']}\n";
        $receipt .= "*Payment:* {$sale['payment_method']}\n\n";
        $receipt .= "Thank you for your business! 🙏";
        
        return $receipt;
    }

    /**
     * Format a dashboard summary.
     */
    public function formatDashboard(array $stats): string
    {
        $dashboard = "📊 *Business Dashboard*\n\n";
        $dashboard .= "*{$stats['business_name']}*\n\n";
        
        $dashboard .= "📅 *Today's Performance*\n";
        $dashboard .= "Sales: {$stats['today_sales']}\n";
        $dashboard .= "Orders: {$stats['today_orders']}\n\n";
        
        $dashboard .= "📉 *Yesterday*\n";
        $dashboard .= "Sales: {$stats['yesterday_sales']}\n\n";

        $dashboard .= "📆 *This Month*\n";
        $dashboard .= "Sales: {$stats['month_sales']}\n";
        $dashboard .= "Growth: " . ($stats['growth'] > 0 ? "📈 +" : "📉 ") . "{$stats['growth']}%\n\n";
        
        if (isset($stats['low_stock']) && $stats['low_stock'] > 0) {
            $dashboard .= "⚠️ *Alerts*\n";
            $dashboard .= "Low Stock Items: {$stats['low_stock']}\n\n";
        }
        
        $dashboard .= "_Type 'Menu' for more options_";
        
        return $dashboard;
    }

    /**
     * Format a specialized Sales Report.
     */
    public function formatSalesReport(array $stats): string
    {
        $report = "💰 *Sales Report Summary*\n\n";
        $report .= "*{$stats['business_name']}*\n\n";
        $report .= "• Today's Sales: {$stats['today_sales']}\n";
        $report .= "• Today's Count: {$stats['today_count']}\n\n";
        $report .= "• Monthly Sales: {$stats['month_sales']}\n";
        $report .= "• Monthly Count: {$stats['month_count']}\n\n";
        $report .= "_Type '0' to return to menu_";
        return $report;
    }

    /**
     * Format an Inventory Report.
     */
    public function formatInventoryReport(array $stats): string
    {
        $report = "📦 *Inventory Report Summary*\n\n";
        $report .= "*{$stats['business_name']}*\n\n";
        $report .= "• Total Products: {$stats['total_products']}\n";
        $report .= "• Total Stock Items: {$stats['total_items']}\n\n";
        
        if ($stats['low_stock_count'] > 0) {
            $report .= "⚠️ *Low Stock Alert ({$stats['low_stock_count']})*\n";
            foreach ($stats['low_stock_items'] as $item) {
                $report .= "• {$item}\n";
            }
            if ($stats['low_stock_count'] > 5) {
                $report .= "...and " . ($stats['low_stock_count'] - 5) . " more.\n";
            }
            $report .= "\n";
        } else {
            $report .= "✅ All stock levels healthy!\n\n";
        }
        
        $report .= "_Type '0' for main menu_";
        return $report;
    }

    /**
     * Format a Customer Report.
     */
    public function formatCustomerReport(array $stats): string
    {
        $report = "👥 *Customer Report Summary*\n\n";
        $report .= "*{$stats['business_name']}*\n\n";
        $report .= "• Total Customers: {$stats['total_customers']}\n\n";
        
        if (!empty($stats['top_spenders'])) {
            $report .= "🏆 *Top Spenders*\n";
            foreach ($stats['top_spenders'] as $spender) {
                $report .= "• {$spender}\n";
            }
            $report .= "\n";
        }
        
        $report .= "_Type '0' for main menu_";
        return $report;
    }

    /**
     * Format product information.
     */
    public function formatProduct(array $product): string
    {
        $info = "📦 *Product Details*\n\n";
        $info .= "*Name:* {$product['name']}\n";
        $info .= "*SKU:* {$product['sku']}\n";
        $info .= "*Price:* {$product['price']}\n";
        $info .= "*Stock:* {$product['stock']}\n";
        
        if (isset($product['category'])) {
            $info .= "*Category:* {$product['category']}\n";
        }
        
        return $info;
    }

    /**
     * Format a help message.
     */
    public function formatHelp(): string
    {
        $help = "❓ *Help & Support*\n\n";
        $help .= "*Quick Commands:*\n";
        $help .= "• Menu - Show main menu\n";
        $help .= "• Cancel - Cancel current operation\n";
        $help .= "• Back - Go back one step\n";
        $help .= "• Logout - End session\n\n";
        $help .= "*Need Assistance?*\n";
        $help .= "Type 'Support' to contact our team.\n\n";
        $help .= "_Type 'Menu' to return_";
        
        return $help;
    }

    /**
     * Format subscription status.
     */
    public function formatSubscriptionStatus($subscription, string $currency = 'KES'): string
    {
        if (!$subscription) {
            return "💳 *Subscription Status*\n\n" .
                   "❌ No active subscription\n\n" .
                   "Upgrade to unlock premium features!\n\n" .
                   "_Type 'Upgrade' to view plans_";
        }

        $status = "💳 *Subscription Status*\n\n";
        $status .= "*Plan:* {$subscription->plan_name}\n";
        $status .= "*Amount:* {$currency} " . number_format($subscription->amount, 2) . "\n";
        
        // Status with emoji
        $statusEmoji = match($subscription->status) {
            'active' => '✅',
            'pending' => '⏳',
            'expired' => '❌',
            'suspended' => '⚠️',
            default => '❓'
        };
        $status .= "*Status:* {$statusEmoji} " . ucfirst($subscription->status) . "\n";
        
        if ($subscription->plan_name) {
            $status .= "*Plan:* {$subscription->plan_name}\n";
        }
        
        if ($subscription->starts_at) {
            $status .= "*Started:* " . $subscription->starts_at->format('M d, Y') . "\n";
        }
        
        if ($subscription->ends_at) {
            $daysLeft = now()->diffInDays($subscription->ends_at, false);
            $status .= "*Expires:* " . $subscription->ends_at->format('M d, Y');
            
            if ($daysLeft > 0) {
                $status .= " ({$daysLeft} days left)\n";
            } else {
                $status .= " (Expired)\n";
            }
        }
        
        return $status;
    }

    /**
     * Format subscription history.
     */
    public function formatSubscriptionHistory($payments, string $currency = 'KES'): string
    {
        if (empty($payments) || $payments->isEmpty()) {
            return "📜 *Payment History*\n\n" .
                   "No payment records found.\n\n" .
                   "_Type '0' to return to menu_";
        }

        $history = "📜 *Payment History*\n\n";
        
        foreach ($payments->take(10) as $index => $payment) {
            $number = $index + 1;
            $statusEmoji = $payment->status === 'completed' ? '✅' : '⏳';
            
            $history .= "{$number}. {$statusEmoji} {$payment->plan_name}\n";
            $history .= "   {$currency} " . number_format($payment->amount, 2);
            $history .= " - " . $payment->created_at->format('M d, Y') . "\n";
            
            if ($payment->mpesa_receipt) {
                $history .= "   Receipt: {$payment->mpesa_receipt}\n";
            }
            $history .= "\n";
        }
        
        $history .= "_Showing last " . min(10, $payments->count()) . " payments_\n";
        $history .= "_Type '0' to return to menu_";
        
        return $history;
    }

    /**
     * Format available plans list.
     */
    public function formatPlanList($plans, string $currency = 'KES'): string
    {
        if (empty($plans) || $plans->isEmpty()) {
            return "📋 *Available Plans*\n\n" .
                   "No plans available at the moment.\n\n" .
                   "_Type '0' to return to menu_";
        }

        $list = "📋 *Available Plans*\n\n";
        $list .= "Choose a plan to upgrade:\n\n";
        
        foreach ($plans as $index => $plan) {
            $number = $index + 1;
            $list .= "{$number}️⃣ *{$plan->name}*\n";
            $list .= "   Monthly: {$currency} " . number_format($plan->price_monthly, 2) . "\n";
            
            if ($plan->price_yearly) {
                $list .= "   Yearly: {$currency} " . number_format($plan->price_yearly, 2) . "\n";
            }
            
            if ($plan->description) {
                $list .= "   " . substr($plan->description, 0, 50) . "...\n";
            }
            
            $list .= "\n";
        }
        
        $list .= "0️⃣ Back to Menu\n\n";
        $list .= "_Type the number to select a plan_";
        
        return $list;
    }

    /**
     * Format plan details.
     */
    public function formatPlanDetails($plan, string $currency = 'KES'): string
    {
        $details = "📋 *{$plan->name}*\n\n";
        
        if ($plan->description) {
            $details .= "{$plan->description}\n\n";
        }
        
        $details .= "*Pricing:*\n";
        $details .= "Monthly: {$currency} " . number_format($plan->price_monthly, 2) . "\n";
        
        if ($plan->price_yearly) {
            $savings = ($plan->price_monthly * 12) - $plan->price_yearly;
            $details .= "Yearly: {$currency} " . number_format($plan->price_yearly, 2);
            $details .= " (Save {$currency} " . number_format($savings, 2) . ")\n";
        }
        
        $details .= "\n*Features:*\n";
        if ($plan->max_users) {
            $details .= "• Users: {$plan->max_users}\n";
        }
        if ($plan->max_products) {
            $details .= "• Products: {$plan->max_products}\n";
        }
        if ($plan->max_employees) {
            $details .= "• Employees: {$plan->max_employees}\n";
        }
        
        $details .= "\n_Type 'Subscribe' to continue or '0' to cancel_";
        
        return $details;
    }

    /**
     * Format subscription metrics for SuperAdmin.
     */
    public function formatSubscriptionMetrics(array $metrics, string $currency = 'KES'): string
    {
        $report = "📊 *Subscription Metrics*\n\n";
        
        $report .= "*Overview:*\n";
        $report .= "Total Subscriptions: {$metrics['total']}\n";
        $report .= "✅ Active: {$metrics['active']}\n";
        $report .= "⏳ Pending: {$metrics['pending']}\n";
        $report .= "❌ Expired: {$metrics['expired']}\n";
        $report .= "⚠️ Suspended: {$metrics['suspended']}\n\n";
        
        if (isset($metrics['revenue'])) {
            $report .= "*Revenue:*\n";
            $report .= "This Month: {$currency} " . number_format($metrics['revenue']['month'], 2) . "\n";
            $report .= "Total: {$currency} " . number_format($metrics['revenue']['total'], 2) . "\n\n";
        }
        
        if (isset($metrics['recent'])) {
            $report .= "*Recent Activity:*\n";
            $report .= "New Today: {$metrics['recent']['today']}\n";
            $report .= "New This Week: {$metrics['recent']['week']}\n\n";
        }
        
        $report .= "_Type '0' to return to menu_";
        
        return $report;
    }

    /**
     * Format subscription list for SuperAdmin.
     */
    public function formatSubscriptionList($subscriptions, int $page = 1, int $total = 0): string
    {
        if (empty($subscriptions) || $subscriptions->isEmpty()) {
            return "📋 *All Subscriptions*\n\n" .
                   "No subscriptions found.\n\n" .
                   "_Type '0' to return to menu_";
        }

        $list = "📋 *All Subscriptions* (Page {$page})\n\n";
        
        foreach ($subscriptions as $index => $sub) {
            $number = ($page - 1) * 10 + $index + 1;
            $statusEmoji = match($sub->status) {
                'active' => '✅',
                'pending' => '⏳',
                'expired' => '❌',
                'suspended' => '⚠️',
                default => '❓'
            };
            
            $list .= "{$number}. {$statusEmoji} {$sub->business->name}\n";
            $list .= "   Plan: {$sub->plan_name}\n";
            $list .= "   Expires: " . ($sub->ends_at ? $sub->ends_at->format('M d, Y') : 'N/A') . "\n\n";
        }
        
        $list .= "_Showing " . $subscriptions->count() . " of {$total}_\n";
        $list .= "_Type 'Next' for more or '0' for menu_";
        
        return $list;
    }

    /**
     * Format payment initiation message.
     */
    public function formatPaymentInitiation(string $phone, float $amount, string $currency = 'KES'): string
    {
        return "💳 *Payment Initiated*\n\n" .
               "Amount: {$currency} " . number_format($amount, 2) . "\n" .
               "Phone: {$phone}\n\n" .
               "📱 Check your phone for M-Pesa prompt\n" .
               "Enter your PIN to complete payment\n\n" .
               "_Type 'Status' to check payment status_";
    }

    /**
     * Format payment status check.
     */
    public function formatPaymentStatus(string $status, ?string $receipt = null): string
    {
        $message = "💳 *Payment Status*\n\n";
        
        $statusEmoji = match($status) {
            'completed', 'success' => '✅',
            'pending' => '⏳',
            'failed' => '❌',
            default => '❓'
        };
        
        $message .= "{$statusEmoji} Status: " . ucfirst($status) . "\n\n";
        
        if ($receipt) {
            $message .= "Receipt: {$receipt}\n\n";
        }
        
        if ($status === 'completed' || $status === 'success') {
            $message .= "Your subscription has been activated! 🎉\n\n";
        } elseif ($status === 'pending') {
            $message .= "Waiting for payment confirmation...\n\n";
            $message .= "_Type 'Status' to check again_";
        } else {
            $message .= "Payment was not successful.\n\n";
            $message .= "_Type 'Retry' to try again or '0' for menu_";
        }
        
        return $message;
    }

    /**
     * Format staff list.
     */
    public function formatStaffList(array $data): string
    {
        $list = "👥 *Staff Management*\n\n";
        $list .= "*{$data['business_name']}*\n\n";
        
        if (empty($data['staff'])) {
            $list .= "No staff members found.\n";
        } else {
            foreach ($data['staff'] as $index => $staff) {
                $statusEmoji = $staff['is_active'] ? '✅' : '❌';
                $list .= ($index + 1) . ". {$statusEmoji} *{$staff['name']}*\n";
                $list .= "   Role: {$staff['role']}\n";
                $list .= "   Status: " . ($staff['is_active'] ? 'Active' : 'Inactive') . "\n\n";
            }
        }
        
        $list .= "_Type '0' for menu_";
        return $list;
    }

    /**
     * Format business settings/profile.
     */
    public function formatBusinessSettings(array $data): string
    {
        $settings = "⚙️ *Business Profile*\n\n";
        $settings .= "*Name:* {$data['name']}\n";
        $settings .= "*Phone:* {$data['phone']}\n";
        $settings .= "*Email:* {$data['email']}\n";
        $settings .= "*Address:* " . ($data['address'] ?: 'Not set') . "\n\n";
        
        $settings .= "📊 *Subscription*\n";
        $settings .= "Current Plan: {$data['plan_name']}\n";
        $settings .= "Status: " . ($data['is_active'] ? '✅ Active' : '❌ Inactive') . "\n\n";
        
        $settings .= "_Type '0' to return_";
        return $settings;
    }
}
