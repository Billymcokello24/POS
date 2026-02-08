<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

class WorkflowManager
{
    private const STATE_TTL = 3600; // 1 hour

    /**
     * Set the current workflow state.
     */
    public function setState(string $phone, string $state, ?array $data = null): void
    {
        $stateKey = $this->getStateKey($phone);
        $existing = Cache::get($stateKey, ['data' => []]);
        
        $stateData = [
            'state' => $state,
            'data' => $data ?? $existing['data'],
            'updated_at' => now()->toDateTimeString()
        ];

        \Log::info("WA Workflow SetState: {$phone} -> {$state}", ['data' => $stateData['data']]);
        Cache::put($stateKey, $stateData, self::STATE_TTL);
    }

    /**
     * Get the current state.
     */
    public function getState(string $phone): ?string
    {
        $stateData = Cache::get($this->getStateKey($phone));
        return $stateData['state'] ?? null;
    }

    /**
     * Get workflow data.
     */
    public function getData(string $phone, ?string $key = null)
    {
        $stateData = Cache::get($this->getStateKey($phone));
        $data = $stateData['data'] ?? [];
        
        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }

    /**
     * Update workflow data.
     */
    public function updateData(string $phone, string $key, $value): void
    {
        $this->setData($phone, $key, $value);
    }

    /**
     * Set specific workflow data key.
     */
    public function setData(string $phone, string $key, $value): void
    {
        $stateKey = $this->getStateKey($phone);
        $stateData = Cache::get($stateKey, ['state' => null, 'data' => []]);
        $stateData['data'][$key] = $value;
        $stateData['updated_at'] = now()->toDateTimeString();
        
        \Log::info("WA Workflow SetData: {$phone} -> {$key} = {$value}");
        Cache::put($stateKey, $stateData, self::STATE_TTL);
    }

    /**
     * Clear the current workflow.
     */
    public function clearWorkflow(string $phone): void
    {
        Cache::forget($this->getStateKey($phone));
    }

    /**
     * Check if user is in a workflow.
     */
    public function hasActiveWorkflow(string $phone): bool
    {
        return Cache::has($this->getStateKey($phone));
    }

    /**
     * Get workflow type from state.
     */
    public function getWorkflowType(string $phone): ?string
    {
        $state = $this->getState($phone);
        if (!$state) {
            return null;
        }

        // Extract workflow type from state (e.g., SALES_ADD_ITEM -> SALES)
        $parts = explode('_', $state);
        return $parts[0] ?? null;
    }

    /**
     * Transition to next state with validation.
     */
    public function transition(string $phone, string $nextState, ?array $data = null): void
    {
        $currentState = $this->getState($phone);
        $currentData = $this->getData($phone);

        // Merge data if provided
        if ($data !== null) {
            $currentData = array_merge($currentData, $data);
        }

        $this->setState($phone, $nextState, $currentData);
    }

    private function getStateKey(string $phone): string
    {
        return 'wa_workflow_' . $phone;
    }
}
