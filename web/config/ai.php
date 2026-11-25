<?php

/*
 * laravel/ai: Laravel's official AI SDK. One API for many AI providers; each agent picks its
 * provider + model (the reminders package reads them from config/reminders.php).
 *
 * This house uses two:
 *  - anthropic: Claude in the cloud, for tasks that need vision or must be exactly right
 *    (reading school letters). Key in web/.env, never in git.
 *  - ollama: models running locally on the Mac mini (qwen, nomic-embed-text), free and private.
 *    From inside Docker, the Mac is reachable as host.docker.internal.
 */
return [

    'default' => env('AI_DEFAULT_PROVIDER', 'anthropic'),
    'default_for_embeddings' => 'ollama',

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
            'individually' => true,
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
        ],
    ],

];
