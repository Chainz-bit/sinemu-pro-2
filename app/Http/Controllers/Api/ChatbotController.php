<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly GeminiService $gemini,
    ) {}

    /**
     * Send a message to the chatbot and get an AI response.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'session_id' => ['required', 'string', 'max:64'],
        ]);

        $sessionId = $request->input('session_id');
        $userMessage = trim($request->input('message'));

        // Find or create conversation
        $conversation = ChatbotConversation::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $request->user()?->id],
        );

        // Update user_id if user logs in during an existing session
        if ($request->user() && ! $conversation->user_id) {
            $conversation->update(['user_id' => $request->user()->id]);
        }

        // Save user message
        ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Get conversation history for context
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatbotMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->toArray();

        // Remove the last message (the one we just added) since it's passed separately
        array_pop($history);

        // Get AI response
        $result = $this->gemini->chat($userMessage, $history);

        // Save assistant message
        $assistantMessage = ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $result['message'],
        ]);

        // Touch conversation updated_at
        $conversation->touch();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'message_id' => $assistantMessage->id,
        ]);
    }

    /**
     * Get conversation history for a session.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
        ]);

        $conversation = ChatbotConversation::where('session_id', $request->input('session_id'))
            ->first();

        if (! $conversation) {
            return response()->json([
                'messages' => [],
            ]);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatbotMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return response()->json([
            'messages' => $messages,
        ]);
    }

    /**
     * Start a new conversation (clears current session).
     */
    public function newConversation(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
        ]);

        // Delete the old conversation and its messages (cascade)
        ChatbotConversation::where('session_id', $request->input('session_id'))
            ->delete();

        return response()->json([
            'success' => true,
            'new_session_id' => Str::uuid()->toString(),
        ]);
    }
}
