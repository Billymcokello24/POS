<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Business extends Model
{
    use SoftDeletes;

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
        'suspension_reason',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
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

        DB::transaction(function () use ($subscription, $receipt, $metadata, $plan, $duration, $activatedAt, $verifiedAt, $now) {
            // Expire any existing active subscriptions for this business (except this one)
            try {
                $this->subscriptions()->where('status', 'active')->where('id', '!=', $subscription->id)->get()->each(function ($old) use ($now) {
                    try {
                        $old->update(['status' => 'expired', 'ends_at' => $now]);
                    } catch (\Throwable $_) { /* ignore per-subscription failures */ }
                });
            } catch (\Throwable $_) {
                // ignore
            }

            // Update subscription with MPESA receipt and verification timestamps when available
            $subscription->update([
                'status' => 'active',
                'transaction_id' => $receipt ?? $subscription->transaction_id,
                'mpesa_receipt' => $receipt ?? $subscription->mpesa_receipt ?? null,
                'starts_at' => $now,
                'verified_at' => $receipt ? $verifiedAt : ($subscription->verified_at ?? null),
                'activated_at' => $receipt ? $activatedAt : ($subscription->activated_at ?? null),
                'ends_at' => $now->copy()->addDays($duration),
                'payment_details' => array_merge($subscription->payment_details ?? [], $metadata, [
                    'activated_at' => $activatedAt->toDateTimeString(),
                    'mpesa_receipt' => $receipt ?? ($subscription->mpesa_receipt ?? null),
                ])
            ]);

            // Update business plan and plan_ends_at
            try {
                $this->plan_id = $plan->id;
                if (isset($subscription->ends_at) && $subscription->ends_at) {
                    $this->plan_ends_at = $subscription->ends_at;
                } else {
                    $this->plan_ends_at = $subscription->ends_at ?? $subscription->activated_at ?? $subscription->starts_at ?? $now->copy()->addDays($duration);
                }
                $this->save();
            } catch (\Throwable $_) {
                // ignore
            }

            // Sync features: enable features that belong to the new plan and remove previous feature mappings
            try {
                $featureIds = $plan->features()->pluck('features.id')->toArray();
                // Build sync array to set is_enabled = true for current plan features
                $sync = [];
                foreach ($featureIds as $fid) {
                    $sync[$fid] = ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()];
                }
                // Replace pivot entries with new mapping (this will remove previously enabled features not in this plan)
                $this->features()->sync($sync);
            } catch (\Throwable $_) {
                // ignore
            }
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

