<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Business extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'business_type',
        'address',
        'phone',
        'email',
        'tax_id',
        'logo',
        'receipt_prefix',
        'currency',
        'timezone',
        'is_active',
        'plan_id',
        'plan_ends_at',
        'subscription_updated_at',
        'active_features',
        'suspension_reason',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'active_features' => 'array',
        'subscription_updated_at' => 'datetime',
        'plan_ends_at' => 'datetime',
    ];

    /**
     * Mutator to encrypt sensitive MPESA fields when saving settings.
     * We encrypt consumer_secret and passkey if they are present and not already encrypted.
     */
    public function setSettingsAttribute($value)
    {
        // Ensure $value is an array; avoid json_decode(null)
        if ($value === null) {
            $settings = [];
        } else {
            $settings = is_array($value) ? $value : (json_decode($value, true) ?? []);
        }

        if (isset($settings['mpesa']) && is_array($settings['mpesa'])) {
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $key) {
                if (array_key_exists($key, $settings['mpesa']) && $settings['mpesa'][$key] !== null) {
                    $val = $settings['mpesa'][$key];
                    // If the value is already encrypted (decryptable), skip encrypting again
                    try {
                        Crypt::decryptString($val);
                        // decrypt succeeded -> it's already encrypted, leave as is
                    } catch (\Exception $e) {
                        // not encrypted -> encrypt
                        try {
                            $settings['mpesa'][$key] = Crypt::encryptString((string) $val);
                        } catch (\Exception $inner) {
                            // If encryption fails for any reason, fall back to storing plaintext (rare)
                            // but log would be preferable; keep plaintext to avoid data loss
                            $settings['mpesa'][$key] = (string) $val;
                        }
                    }
                }
            }
        }

        $this->attributes['settings'] = json_encode($settings);
    }

    /**
     * Accessor to decrypt sensitive MPESA fields when reading settings.
     */
    public function getSettingsAttribute($value)
    {
        $settings = is_array($value) ? $value : (json_decode($value, true) ?? []);

        if (isset($settings['mpesa']) && is_array($settings['mpesa'])) {
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $key) {
                if (array_key_exists($key, $settings['mpesa']) && $settings['mpesa'][$key] !== null) {
                    $val = $settings['mpesa'][$key];
                    try {
                        $decrypted = Crypt::decryptString($val);
                        $settings['mpesa'][$key] = $decrypted;
                    } catch (\Exception $e) {
                        // If decrypt fails, keep the original value (it might be plaintext)
                        $settings['mpesa'][$key] = $val;
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * Return MPESA settings array or null
     */
    public function mpesa()
    {
        return $this->settings['mpesa'] ?? null;
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function taxConfigurations(): HasMany
    {
        return $this->hasMany(TaxConfiguration::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest();
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'business_feature')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function hasFeature(string $featureKey): bool
    {
        return in_array($featureKey, $this->getEnabledFeatureKeys());
    }

    public function getEnabledFeatureKeys(): array
    {
        // Must have an active subscription to access ANY protected feature
        if (!$this->activeSubscription()->exists()) {
            return [];
        }

        // If we have cached features, use them for speed
        if ($this->active_features && is_array($this->active_features)) {
            return $this->active_features;
        }

        return \App\Models\Feature::where(function ($query) {
            $query->whereHas('businesses', function ($q) {
                $q->where('businesses.id', $this->id)
                    ->where('business_feature.is_enabled', true);
            });

            if ($this->plan_id) {
                $query->orWhereHas('plans', function ($q) {
                    $q->where('plans.id', $this->plan_id);
                });
            }
        })->pluck('key')->unique()->values()->toArray();
    }

    public function refreshFeatures(): void
    {
        $plan = $this->plan;
        if (!$plan) {
            // If no plan, clear features
            $this->features()->detach();
            $this->update(['active_features' => []]);
            return;
        }

        $features = $plan->features;
        $featureIds = $features->pluck('id')->toArray();
        $slugs = $features->pluck('key')->toArray(); // 'key' is used in hasFeature

        $sync = [];
        $now = now();
        foreach ($featureIds as $id) {
            $sync[$id] = ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        
        $this->features()->sync($sync);
        $this->update(['active_features' => $slugs]);
    }

    public function activateSubscription(Subscription $subscription, ?string $receipt = null, array $metadata = []): void
    {
        $plan = $subscription->plan;

        if (!$plan && $subscription->plan_name) {
            $plan = Plan::where('name', 'like', $subscription->plan_name)->first();
        }

        if (!$plan) {
            throw new \Exception("Activation failed: Plan '{$subscription->plan_name}' not found for business '{$this->name}'.");
        }

        $billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $duration = $billingCycle === 'yearly' ? 365 : 30;

        // Prepare metadata and timestamps
        $now = now();
        $activatedAt = $now;
        $verifiedAt = $now;

        DB::transaction(function () use ($subscription, $receipt, $metadata, $plan, $duration, $activatedAt, $now) {
            // 1. Comparison logic for Scheduled Downgrades
            $currentPlan = $this->plan;
            $currentPrice = $currentPlan ? (float)$currentPlan->price_monthly : 0;
            $newPrice = (float)$plan->price_monthly;
            $isUpgrade = $newPrice >= $currentPrice;

            // 2. Transition OLD attempts
            $this->subscriptions()
                ->where('id', '!=', $subscription->id)
                ->whereIn('status', [Subscription::STATUS_PENDING])
                ->update([
                    'status' => 'expired', 
                    'ends_at' => $now,
                    'is_active' => false
                ]);

            // 3. Handle Plan Transition Date (Point 11: Schedule downgrades at boundary)
            if ($isUpgrade) {
                // Upgrades are immediate: Expire current active SUBSCR
                $this->subscriptions()
                    ->where('id', '!=', $subscription->id)
                    ->whereIn('status', ['active', 'trialing'])
                    ->update(['status' => 'expired', 'ends_at' => $now, 'is_active' => false]);
                
                $subscriptionStarts = $now;
                $subscriptionEnds = $now->copy()->addDays($duration);
                
                // Switch business plan immediately
                $this->update([
                    'plan_id' => $plan->id,
                    'plan_ends_at' => $subscriptionEnds
                ]);
                $this->refreshFeatures();
            } else {
                // Downgrades are scheduled: current remains active until it ends
                $activeSub = $this->subscriptions()
                    ->where('id', '!=', $subscription->id)
                    ->whereIn('status', ['active', 'trialing'])
                    ->orderBy('ends_at', 'desc')
                    ->first();

                if ($activeSub && $activeSub->ends_at?->isFuture()) {
                    $subscriptionStarts = $activeSub->ends_at; // Start when previous ends
                    $subscriptionEnds = $subscriptionStarts->copy()->addDays($duration);
                } else {
                    $subscriptionStarts = $now;
                    $subscriptionEnds = $now->copy()->addDays($duration);
                    $this->update([
                        'plan_id' => $plan->id,
                        'plan_ends_at' => $subscriptionEnds
                    ]);
                    $this->refreshFeatures();
                }
            }

            // 4. Update core subscription record
            $subscription->update([
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'transaction_id' => $receipt ?? $subscription->transaction_id,
                'mpesa_receipt' => $receipt ?? $subscription->mpesa_receipt ?? null,
                'starts_at' => $subscriptionStarts,
                'ends_at' => $subscriptionEnds,
                'verified_at' => $now,
                'verified_by' => $metadata['approved_by'] ?? 'system',
                'payment_confirmed_at' => $now,
                'activated_at' => $now,
                'payment_details' => array_merge($subscription->payment_details ?? [], $metadata, [
                    'activated_at' => $now->toDateTimeString(),
                    'auto_verified' => true,
                    'saas_synced' => true,
                    'logic' => $isUpgrade ? 'immediate_upgrade' : 'scheduled_downgrade'
                ])
            ]);
        });
    }

    /**
     * Check if the business is within its plan limits for a resource.
     * $resource: 'products' or 'users'
     */
    public function withinPlanLimits(string $resource): bool
    {
        if (!$this->plan) return true; // Default to allowing if no plan (trial/manual)

        if ($resource === 'products') {
            $limit = $this->plan->max_products;
            if ($limit === 0) return true; // Unlimited
            return $this->products()->count() < $limit;
        }

        if ($resource === 'users') {
            $limit = $this->plan->max_users;
            if ($limit === 0) return true; // Unlimited
            return $this->users()->count() < $limit;
        }

        if ($resource === 'employees') {
            $limit = $this->plan->max_employees;
            if ($limit === 0) return true; // Unlimited
            // Assuming business has employees() relation. Let me check.
            if (method_exists($this, 'employees')) {
                return $this->employees()->count() < $limit;
            }
            return true;
        }

        return true;
    }

    public function generateSaleNumber(): string
    {
        $prefix = $this->receipt_prefix ?? 'POS';
        $lastSale = $this->sales()->latest('id')->first();
        $nextNumber = $lastSale ? ((int) substr($lastSale->sale_number, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

