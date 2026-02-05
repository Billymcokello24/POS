<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Category;
use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\ImportCompleteNotification;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes for large imports
    public $tries = 2;
    public $backoff = 60;

    protected $rows;
    protected $targetBusinessIds;
    protected $userId;

    public $imported = 0;
    public $updated = 0;
    public $errors = [];
    public $warnings = [];

    /**
     * Create a new job instance.
     */
    public function __construct(array $rows, array $targetBusinessIds, int $userId)
    {
        $this->rows = $rows;
        $this->targetBusinessIds = $targetBusinessIds;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting product import', [
                'total_rows' => count($this->rows),
                'businesses' => $this->targetBusinessIds,
                'user_id' => $this->userId,
            ]);

            foreach ($this->targetBusinessIds as $businessId) {
                $this->importForBusiness($businessId);
            }

            // Notify user of completion
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new ImportCompleteNotification(
                    'Products',
                    $this->imported,
                    $this->updated,
                    count($this->errors),
                    count($this->warnings)
                ));
            }

            Log::info('Product import completed', [
                'imported' => $this->imported,
                'updated' => $this->updated,
                'errors' => count($this->errors),
                'warnings' => count($this->warnings),
            ]);
        } catch (\Exception $e) {
            Log::error('Product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Import products for a specific business
     */
    protected function importForBusiness(int $businessId): void
    {
        $business = Business::find($businessId);
        if (!$business) {
            $this->errors[] = "Business #{$businessId} not found";
            return;
        }

        foreach ($this->rows as $index => $row) {
            try {
                $this->processRow($row, $businessId, $index);
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                Log::warning('Product import row error', [
                    'row' => $index + 2,
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single row
     */
    protected function processRow(array $row, int $businessId, int $index): void
    {
        $name = trim($row['name'] ?? '');
        if (empty($name)) {
            throw new \Exception("Name is required");
        }

        // Get or create category
        $categoryId = null;
        if (!empty($row['category'])) {
            $category = Category::firstOrCreate(
                ['name' => trim($row['category']), 'business_id' => $businessId],
                ['is_active' => true]
            );
            $categoryId = $category->id;
        }

        // Prepare product data
        $data = [
            'name' => $name,
            'business_id' => $businessId,
            'category_id' => $categoryId,
            'description' => $row['description'] ?? null,
            'sku' => !empty($row['sku']) ? trim($row['sku']) : 'SKU-' . Str::upper(Str::random(8)),
            'barcode' => $row['barcode'] ?? null,
            'cost_price' => floatval($row['cost_price'] ?? 0),
            'selling_price' => floatval($row['selling_price'] ?? 0),
            'quantity' => intval($row['quantity'] ?? 0),
            'reorder_level' => intval($row['reorder_level'] ?? 0),
            'unit' => $row['unit'] ?? null,
            'track_inventory' => true,
            'is_active' => true,
        ];

        // Check if product exists (by SKU or name)
        $existing = Product::where('business_id', $businessId)
            ->where(function ($q) use ($data) {
                $q->where('sku', $data['sku'])
                    ->orWhere('name', $data['name']);
            })
            ->first();

        if ($existing) {
            $existing->update($data);
            $this->updated++;

            if ($data['quantity'] > 0) {
                $existing->increaseStock($data['quantity'], 'IN', 'Import Update', $this->userId);
            }
        } else {
            $product = Product::create($data);
            $this->imported++;

            if ($data['quantity'] > 0) {
                $product->increaseStock($data['quantity'], 'IN', 'Initial Import', $this->userId);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Product import job failed permanently', [
            'error' => $exception->getMessage(),
            'imported' => $this->imported,
            'updated' => $this->updated,
            'errors' => count($this->errors),
        ]);

        // Notify user of failure
        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new \App\Notifications\ImportFailedNotification(
                'Products',
                $exception->getMessage()
            ));
        }
    }
}
