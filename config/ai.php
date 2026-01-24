<?php

return [
    // Driver: 'mock' (default) or 'openai'
    'driver' => env('AI_DRIVER', env('AI_DRIVER', 'mock')),

    // API key is read from env; DO NOT commit your key to source control.
    'api_key' => env('AI_API_KEY', null),

    // Token used to secure stateless dev API endpoints (X-AI-API-KEY)
    'api_token' => env('AI_API_TOKEN', null),

    // Model to use for the OpenAI-compatible endpoint
    'model' => env('AI_MODEL', 'gpt-5'),

    // Optional base URI for OpenAI compatible APIs (leave null to use OpenAI official)
    'base_uri' => env('AI_BASE_URI', 'https://api.openai.com'),

    // Token timeout & other HTTP options
    'timeout_seconds' => env('AI_TIMEOUT', 10),
];
