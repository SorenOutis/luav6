<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class ChatController extends Controller
{
    protected string $sessionKey = 'koa_chat_history';

    public function __invoke(Request $request)
    {
        if (! Setting::get('ai_chat_enabled', true)) {
            return response()->json([
                'response' => Setting::get('ai_chat_maintenance_message', 'KOA is currently under maintenance.'),
            ], 503);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            // Get history from session
            $historyData = session()->get($this->sessionKey, []);

            // Map session data to message objects
            $history = collect($historyData)->map(function ($msg) {
                if ($msg['role'] === 'user') {
                    return new UserMessage($msg['content']);
                }

                return new AssistantMessage($msg['content']);
            })->toArray();

            $agent = new AssistantAgent;
            $agent->setHistory($history);

            $response = $agent->prompt($request->message);

            // Update history in session
            $historyData[] = ['role' => 'user', 'content' => $request->message];
            $historyData[] = ['role' => 'assistant', 'content' => (string) $response];
            session()->put($this->sessionKey, $historyData);
            session()->save(); // Explicitly save session

            return response()->json([
                'response' => (string) $response,
                'history' => $historyData,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Controller Error: '.$e->getMessage());

            return response()->json([
                'response' => 'KOA is currently having technical difficulties. Please try again later.',
            ], 500);
        }
    }

    public function getHistory()
    {
        $history = session()->get($this->sessionKey);

        if (! $history) {
            $history = [['role' => 'assistant', 'content' => 'Hello! How can I help you today?']];
            session()->put($this->sessionKey, $history);
            session()->save();
        }

        return response()->json([
            'history' => $history,
        ]);
    }
}
