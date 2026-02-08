<?php

namespace App\Services\WhatsApp;

use App\Models\User;

class PermissionChecker
{
    private SessionManager $session;

    public function __construct(SessionManager $sessionManager)
    {
        $this->session = $sessionManager;
    }

    /**
     * Check if user can access sales features.
     */
    public function canAccessSales(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false; // SuperAdmins don't access business features
        }

        return $this->session->hasPermission($phone, 'view_sales');
    }

    /**
     * Check if user can create sales.
     */
    public function canCreateSales(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'create_sales');
    }

    /**
     * Check if user can refund sales.
     */
    public function canRefundSales(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'refund_sales');
    }

    /**
     * Check if user can access inventory features.
     */
    public function canAccessInventory(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'view_inventory');
    }

    /**
     * Check if user can adjust inventory.
     */
    public function canAdjustInventory(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'adjust_inventory');
    }

    /**
     * Check if user can access products.
     */
    public function canAccessProducts(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'view_products');
    }

    /**
     * Check if user can create/edit products.
     */
    public function canManageProducts(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'create_products') ||
               $this->session->hasPermission($phone, 'edit_products');
    }

    /**
     * Check if user can access customers.
     */
    public function canAccessCustomers(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'view_customers');
    }

    /**
     * Check if user can create customers.
     */
    public function canCreateCustomers(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'create_customers');
    }

    /**
     * Check if user can access reports.
     */
    public function canAccessReports(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'view_reports');
    }

    /**
     * Check if user can access staff management.
     */
    public function canAccessStaff(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'create_users');
    }

    /**
     * Check if user can access settings.
     */
    public function canAccessSettings(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false;
        }

        return $this->session->hasPermission($phone, 'view_settings');
    }

    /**
     * Check if user can edit settings.
     */
    public function canEditSettings(string $phone): bool
    {
        return $this->session->hasPermission($phone, 'edit_settings');
    }

    /**
     * Check if user can access subscriptions.
     */
    public function canAccessSubscriptions(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return true; // SuperAdmins can view all subscriptions
        }

        return $this->session->isBusinessAdmin($phone);
    }

    /**
     * Check if user can access SuperAdmin features.
     */
    public function canAccessSuperAdminFeatures(string $phone): bool
    {
        return $this->session->isSuperAdmin($phone);
    }

    /**
     * Check if user can manage subscriptions (upgrade/downgrade).
     */
    public function canManageSubscriptions(string $phone): bool
    {
        if ($this->session->isSuperAdmin($phone)) {
            return false; // SuperAdmins manage via different interface
        }

        return $this->session->isBusinessAdmin($phone);
    }

    /**
     * Check if user can view all subscriptions (SuperAdmin only).
     */
    public function canViewAllSubscriptions(string $phone): bool
    {
        return $this->session->isSuperAdmin($phone);
    }

    /**
     * Check if user can modify subscription status (SuperAdmin only).
     */
    public function canModifySubscriptionStatus(string $phone): bool
    {
        return $this->session->isSuperAdmin($phone);
    }

    /**
     * Get role badge emoji.
     */
    public function getRoleBadge(string $phone): string
    {
        if ($this->session->isSuperAdmin($phone)) {
            return '🔱';
        }

        $roleInfo = $this->session->getRoleInfo($phone);
        
        return match($roleInfo['name'] ?? '') {
            'admin' => '👑',
            'manager' => '📊',
            'cashier' => '💰',
            'stock_clerk' => '📦',
            default => '👤'
        };
    }

    /**
     * Get role display name.
     */
    public function getRoleDisplayName(string $phone): string
    {
        if ($this->session->isSuperAdmin($phone)) {
            return 'SuperAdmin';
        }

        $roleInfo = $this->session->getRoleInfo($phone);
        return $roleInfo['display_name'] ?? 'User';
    }

    /**
     * Get permission denied message.
     */
    public function getPermissionDeniedMessage(string $phone, string $feature): string
    {
        $roleName = $this->getRoleDisplayName($phone);
        $badge = $this->getRoleBadge($phone);

        return "❌ *Access Denied*\n\n" .
               "You don't have permission to access *{$feature}*.\n\n" .
               "Your role: {$roleName} {$badge}\n\n" .
               "Contact your administrator for access.\n\n" .
               "Type 'Menu' to return.";
    }

    /**
     * Get available menu options based on role.
     */
    public function getAvailableMenuOptions(string $phone): array
    {
        if ($this->session->isSuperAdmin($phone)) {
            return [
                '1' => ['name' => 'Business Management', 'key' => 'business_mgmt'],
                '2' => ['name' => 'Subscription Overview', 'key' => 'subscriptions'],
                '3' => ['name' => 'Support Tickets', 'key' => 'support'],
                '4' => ['name' => 'System Reports', 'key' => 'system_reports'],
                '5' => ['name' => 'Manage Admins', 'key' => 'manage_admins'],
            ];
        }

        $options = [];
        $index = 1;

        if ($this->canAccessSales($phone)) {
            $options[(string)$index++] = ['name' => 'Sales', 'key' => 'sales'];
        }

        if ($this->canAccessInventory($phone)) {
            $options[(string)$index++] = ['name' => 'Inventory', 'key' => 'inventory'];
        }

        if ($this->canAccessCustomers($phone)) {
            $options[(string)$index++] = ['name' => 'Customers', 'key' => 'customers'];
        }

        if ($this->canAccessStaff($phone)) {
            $options[(string)$index++] = ['name' => 'Staff Management', 'key' => 'staff'];
        }

        if ($this->canAccessReports($phone)) {
            $options[(string)$index++] = ['name' => 'Reports', 'key' => 'reports'];
        }

        if ($this->canAccessSubscriptions($phone) && !$this->session->isSuperAdmin($phone)) {
            $options[(string)$index++] = ['name' => 'Payments/Subscriptions', 'key' => 'subscriptions'];
        }

        if ($this->canAccessSettings($phone)) {
            $options[(string)$index++] = ['name' => 'Account Settings', 'key' => 'settings'];
        }

        // Help is always available
        $options[(string)$index++] = ['name' => 'Help/Support', 'key' => 'help'];

        // Multi-business switching
        $businesses = $this->session->getUserBusinesses($phone);
        if (count($businesses) > 1) {
            $options[(string)$index++] = ['name' => 'Switch Business', 'key' => 'switch'];
        }

        return $options;
    }
}
