<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LlmClient;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function send(Request $request, LlmClient $llm)
    {
        $response = $llm->chat($request->message);

        return response()->json([
            'response' => $response
        ]);
    }
}
