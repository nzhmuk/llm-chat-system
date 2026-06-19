<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    public function chat(string $message): string
    {
        $response = Http::timeout(60)->post(
            config('llm.url') . '/chat',
            ['message' => $message]
        );

        return $response->json('response') ?? 'No response';
    }
}