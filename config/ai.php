<?php

return [
    // Driver: 'openai' for AI-powered analysis, 'mock' for rule-based fallback
    // AI analyzes database-derived metrics (does NOT generate data)
    'driver' => env('AI_DRIVER', 'openai'),

    // OpenAI/OpenRouter API credentials
    'api_key' => env('AI_API_KEY', null),
    'api_token' => env('AI_API_TOKEN', null),

    // Model configuration
    // OpenRouter: openai/gpt-4, openai/gpt-3.5-turbo
    // OpenAI: gpt-4, gpt-4-turbo, gpt-3.5-turbo
    'model' => env('AI_MODEL', 'openai/gpt-4'),

    // API endpoint
    // OpenRouter: https://openrouter.ai/api
    // OpenAI: https://api.openai.com
    'base_uri' => env('AI_BASE_URI', 'https://openrouter.ai/api'),

    // Request timeout
    'timeout_seconds' => env('AI_TIMEOUT', 120),
];
