<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    /**
     * Build the request body, including the model override and prior
     * conversation turns when provided.
     *
     * @param array<int, array{role: string, content: string}> $history
     */
    private function payload(string $message, array $history = []): array
    {
        $payload = ['message' => $message];

        if ($model = config('llm.model')) {
            $payload['model'] = $model;
        }

        if ($history) {
            $payload['history'] = array_values($history);
        }

        return $payload;
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function chat(string $message, array $history = []): string
    {
        $response = Http::timeout(config('llm.timeout', 300))
            ->connectTimeout(10)
            ->withToken(config('llm.key'))
            ->post(config('llm.url') . '/chat', $this->payload($message, $history))
            ->throw();

        return $response->json('response') ?? 'No response';
    }

    /**
     * Stream the LLM response, yielding chunks of text as they arrive.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @return \Generator<string>
     */
    public function chatStream(string $message, array $history = []): \Generator
    {
        $response = Http::timeout(config('llm.timeout', 300))
            ->connectTimeout(10)
            ->withToken(config('llm.key'))
            ->withOptions(['stream' => true])
            ->post(config('llm.url') . '/chat/stream', $this->payload($message, $history))
            ->throw();

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $chunk = $body->read(1024);

            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }
}