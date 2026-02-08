<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Business;

class SessionManager
{
    private const SESSION_TTL = 86400; // 24 hours
    private const CONTEXT_TTL = 3600; // 1 hour for temporary context

    /**
     * Create a new session for a user.
     */
    public function createSession(string $phone, int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $business = $user->currentBusiness;
        
        // Get role information
        $roleInfo = $this->getUserRoleInfo($user, $business?->id);
        
        $sessionData = [
            'user_id' => $userId,
            'business_id' => $business?->id,
            'subscription_status' => $this->getSubscriptionStatus($business),
            'role' => $user->role,
            'is_super_admin' => $user->is_super_admin,
            'role_name' => $roleInfo['name'] ?? null,
            'role_display_name' => $roleInfo['display_name'] ?? null,
            'role_level' => $roleInfo['level'] ?? 0,
            'permissions' => $roleInfo['permissions'] ?? [],
            'created_at' => now()->toDateTimeString(),
            'expires_at' => now()->addSeconds(self::SESSION_TTL)->toDateTimeString()
        ];

        Cache::put($this->getSessionKey($phone), $sessionData, self::SESSION_TTL);
    }

    /**
     * Get session data for a phone number.
     */
    public function getSession(string $phone): ?array
    {
        return Cache::get($this->getSessionKey($phone));
    }

    /**
     * Check if a session exists and is valid.
     */
    public function hasSession(string $phone): bool
    {
        return Cache::has($this->getSessionKey($phone));
    }

    /**
     * Destroy a session.
     */
    public function destroySession(string $phone): void
    {
        Cache::forget($this->getSessionKey($phone));
        Cache::forget($this->getContextKey($phone));
    }

    /**
     * Get the authenticated user from session.
     */
    public function getUser(string $phone): ?User
    {
        $session = $this->getSession($phone);
        if (!$session) {
            return null;
        }

        return User::find($session['user_id']);
    }

    /**
     * Get the active business from session.
     */
    public function getBusiness(string $phone): ?Business
    {
        $session = $this->getSession($phone);
        if (!$session || !$session['business_id']) {
            return null;
        }

        return Business::find($session['business_id']);
    }

    /**
     * Switch active business for multi-business users.
     */
    public function switchBusiness(string $phone, int $businessId): bool
    {
        $session = $this->getSession($phone);
        if (!$session) {
            return false;
        }

        $user = User::find($session['user_id']);
        if (!$user) {
            return false;
        }

        // Verify user has access to this business
        if (!$user->businesses()->where('businesses.id', $businessId)->exists()) {
            return false;
        }

        $business = Business::find($businessId);
        $session['business_id'] = $businessId;
        $session['subscription_status'] = $this->getSubscriptionStatus($business);

        Cache::put($this->getSessionKey($phone), $session, self::SESSION_TTL);
        return true;
    }

    /**
     * Store temporary context data (cart, form data, etc.).
     */
    public function setContext(string $phone, string $key, $value): void
    {
        $contextKey = $this->getContextKey($phone);
        $context = Cache::get($contextKey, []);
        $context[$key] = $value;
        Cache::put($contextKey, $context, self::CONTEXT_TTL);
    }

    /**
     * Get context data.
     */
    public function getContext(string $phone, ?string $key = null)
    {
        $context = Cache::get($this->getContextKey($phone), []);
        
        if ($key === null) {
            return $context;
        }

        return $context[$key] ?? null;
    }

    /**
     * Clear specific context key or all context.
     */
    public function clearContext(string $phone, ?string $key = null): void
    {
        if ($key === null) {
            Cache::forget($this->getContextKey($phone));
        } else {
            $contextKey = $this->getContextKey($phone);
            $context = Cache::get($contextKey, []);
            unset($context[$key]);
            Cache::put($contextKey, $context, self::CONTEXT_TTL);
        }
    }

    /**
     * Check if user has permission for an operation.
     */
    public function hasPermission(string $phone, string $permission): bool
    {
        $session = $this->getSession($phone);
        if (!$session) {
            return false;
        }

        // SuperAdmins have all permissions for platform management
        if ($session['is_super_admin'] ?? false) {
            return true;
        }

        // Check cached permissions first
        if (isset($session['permissions']) && is_array($session['permissions'])) {
            return in_array($permission, $session['permissions']);
        }

        // Fallback to database check
        $user = $this->getUser($phone);
        return $user ? $user->hasPermission($permission) : false;
    }

    /**
     * Check subscription status.
     */
    public function hasActiveSubscription(string $phone): bool
    {
        $session = $this->getSession($phone);
        if (!$session) {
            return false;
        }

        return $session['subscription_status'] === 'active';
    }

    /**
     * Get all businesses for a user.
     */
    public function getUserBusinesses(string $phone): array
    {
        $user = $this->getUser($phone);
        if (!$user) {
            return [];
        }

        return $user->businesses()->get()->map(function ($business) {
            return [
                'id' => $business->id,
                'name' => $business->name,
                'is_active' => $business->is_active
            ];
        })->toArray();
    }

    private function getSessionKey(string $phone): string
    {
        return 'wa_session_' . $phone;
    }

    private function getContextKey(string $phone): string
    {
        return 'wa_context_' . $phone;
    }

    private function getSubscriptionStatus(?Business $business): string
    {
        if (!$business) {
            return 'none';
        }

        $activeSub = $business->activeSubscription()->first();
        return $activeSub ? 'active' : 'expired';
    }

    /**
     * Get user's role information.
     */
    private function getUserRoleInfo(User $user, ?int $businessId): array
    {
        if (!$businessId) {
            return [];
        }

        $role = $user->roles()
            ->wherePivot('business_id', $businessId)
            ->first();

        if (!$role) {
            return [];
        }

        $permissions = $role->permissions()->pluck('name')->toArray();

        return [
            'name' => $role->name,
            'display_name' => $role->display_name,
            'level' => $role->level,
            'permissions' => $permissions
        ];
    }

    /**
     * Check if user is a SuperAdmin.
     */
    public function isSuperAdmin(string $phone): bool
    {
        $session = $this->getSession($phone);
        return $session['is_super_admin'] ?? false;
    }

    /**
     * Check if user is a Business Admin.
     */
    public function isBusinessAdmin(string $phone): bool
    {
        $session = $this->getSession($phone);
        return ($session['role_name'] ?? '') === 'admin';
    }

    /**
     * Get role information.
     */
    public function getRoleInfo(string $phone): array
    {
        $session = $this->getSession($phone);
        if (!$session) {
            return [];
        }

        return [
            'name' => $session['role_name'] ?? null,
            'display_name' => $session['role_display_name'] ?? null,
            'level' => $session['role_level'] ?? 0,
            'is_super_admin' => $session['is_super_admin'] ?? false
        ];
    }
}
