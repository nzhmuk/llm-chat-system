<?php

return [
    'url' => env('LLM_API_URL'),

    // Max seconds to wait for the LLM to respond. Must be >= the reverse-proxy
    // read timeout, or PHP gives up first (502) before the proxy does (504).
    'timeout' => (int) env('LLM_TIMEOUT', 300),

    // Optional model override sent to the LLM service. When null, the service
    // uses its own default model.
    'model' => env('LLM_MODEL'),

    // API key for the LLM service (sent as a Bearer token). Must match the
    // LLM_API_KEY configured on the LLM service.
    'key' => env('LLM_API_KEY'),

    // Passphrase that unlocks Special Order 937 clearance for the session.
    'clearance_phrase' => env('LLM_CLEARANCE_PHRASE', 'OVERRIDE 937'),
];