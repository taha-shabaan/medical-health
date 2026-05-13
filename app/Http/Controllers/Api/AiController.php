<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiController extends Controller
{
    public function __construct(
        private readonly AiResponder $responder
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $query = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->withCount('messages')
            ->latest();

        if ($context = $request->query('role_context')) {
            $query->where('role_context', (string) $context);
        }

        $rows = $query->limit(30)->get()->map(fn (AiConversation $c) => [
            'id' => $c->id,
            'title' => $c->title ?: 'New chat',
            'role_context' => $c->role_context,
            'messages_count' => $c->messages_count ?? 0,
            'updated_at' => $c->updated_at,
            'created_at' => $c->created_at,
        ]);

        return response()->json(['conversations' => $rows]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'role_context' => ['nullable', 'string', 'max:32'],
        ]);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? 'New chat',
            'role_context' => $validated['role_context'] ?? 'general',
        ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'role_context' => $conversation->role_context,
                'updated_at' => $conversation->updated_at,
            ],
        ], 201);
    }

    public function messages(Request $request, AiConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (AiMessage $m) => [
                'id' => $m->id,
                'sender' => $m->sender,
                'content' => $m->content,
                'attachment_name' => $m->attachment_name,
                'created_at' => $m->created_at,
            ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'role_context' => $conversation->role_context,
            ],
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'message' => ['required', 'string', 'max:5000'],
            'role_context' => ['nullable', 'string', 'max:32'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = AiConversation::query()
                ->where('id', $validated['conversation_id'])
                ->where('user_id', $request->user()->id)
                ->first();
        }

        if (! $conversation) {
            $conversation = AiConversation::create([
                'user_id' => $request->user()->id,
                'role_context' => $validated['role_context'] ?? 'general',
                'title' => mb_substr(trim($validated['message']), 0, 80),
            ]);
        }

        $userMessage = $conversation->messages()->create([
            'sender' => 'user',
            'content' => trim($validated['message']),
            'attachment_name' => $validated['attachment_name'] ?? null,
        ]);

        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['sender', 'content'])
            ->map(fn (AiMessage $m) => ['sender' => $m->sender, 'content' => $m->content])
            ->all();

        try {
            $assistantText = $this->responder->respond($history, $conversation->role_context);
        } catch (Throwable $e) {
            report($e);
            $assistantText = 'Temporarily unavailable. Please try again.';
        }

        $assistantMessage = $conversation->messages()->create([
            'sender' => 'assistant',
            'content' => $assistantText,
        ]);

        $conversation->touch();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'role_context' => $conversation->role_context,
                'updated_at' => $conversation->updated_at,
            ],
            'user_message' => [
                'id' => $userMessage->id,
                'sender' => $userMessage->sender,
                'content' => $userMessage->content,
                'attachment_name' => $userMessage->attachment_name,
                'created_at' => $userMessage->created_at,
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'sender' => $assistantMessage->sender,
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at,
            ],
        ]);
    }
}
