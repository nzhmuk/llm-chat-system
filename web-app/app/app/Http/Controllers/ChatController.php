<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\ChatSession;
use App\Services\LlmClient;

class ChatController extends Controller
{
    // How many prior messages to send to the LLM as context. Bounds prompt
    // size (and latency) regardless of how long the conversation gets.
    private const HISTORY_LIMIT = 10;

    public function index(Request $request)
    {
        $session = $this->currentSession($request);

        $messages = $session
            ->messages()
            ->orderBy('id')
            ->get(['role', 'content']);

        return view('chat', ['messages' => $messages]);
    }

    public function newSession(Request $request)
    {
        // Start a fresh conversation. Prior sessions stay in the database.
        // Called via AJAX so the page (and audio) isn't reloaded.
        ChatSession::create(['user_id' => $request->user()->id]);

        return response()->noContent();
    }

    public function send(Request $request, LlmClient $llm)
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $session = $this->currentSession($request);
        $history = $this->history($session);
        $session->messages()->create(['role' => 'user', 'content' => $validated['message']]);

        try {
            $response = $llm->chat($validated['message'], $history);
        } catch (\Throwable $e) {
            Log::error('LLM request failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'The mainframe is unreachable. Please try again.',
            ], 502);
        }

        $session->messages()->create(['role' => 'assistant', 'content' => $response]);

        return response()->json(['response' => $response]);
    }

    public function stream(Request $request, LlmClient $llm): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $session = $this->currentSession($request);
        $history = $this->history($session);
        $session->messages()->create(['role' => 'user', 'content' => $validated['message']]);

        return response()->stream(function () use ($llm, $validated, $history, $session) {
            // Disable PHP-side compression/buffering so each chunk is flushed
            // immediately (gzip buffering would defeat streaming).
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $full = '';

            try {
                foreach ($llm->chatStream($validated['message'], $history) as $chunk) {
                    echo $chunk;
                    $full .= $chunk;

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                Log::error('LLM stream failed', ['error' => $e->getMessage()]);
                echo "\n[MAINFRAME UNREACHABLE]";
            }

            // Persist the assistant reply once the stream completes.
            if (trim($full) !== '') {
                $session->messages()->create(['role' => 'assistant', 'content' => $full]);
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            // Tell nginx (Plesk proxy) not to buffer the streamed response.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * The user's active conversation (most recent), created on first use.
     */
    private function currentSession(Request $request): ChatSession
    {
        $userId = $request->user()->id;

        return ChatSession::where('user_id', $userId)->latest('id')->first()
            ?? ChatSession::create(['user_id' => $userId]);
    }

    /**
     * Recent turns as [{role, content}, ...] in chronological order, capped at
     * HISTORY_LIMIT. Excludes the current (not-yet-saved) message.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(ChatSession $session): array
    {
        return $session
            ->messages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();
    }
}
