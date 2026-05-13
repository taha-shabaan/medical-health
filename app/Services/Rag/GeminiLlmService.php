<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * GeminiLlmService
 *
 * Handles chat completions with Gemini Flash.
 *
 * Important API differences from OpenAI:
 *  - Roles: 'user' and 'model' (not 'assistant')
 *  - System prompt goes in a separate 'system_instruction' field
 *  - Safety settings are passed per request
 */
class GeminiLlmService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private array $fallbackModels;
    private int $attemptsPerModel;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.key');
        $this->model   = config('services.gemini.llm_model');
        $this->baseUrl = config('services.gemini.base_url');
        $this->fallbackModels = $this->parseModelList(config('services.gemini.fallback_models', []));
        $this->attemptsPerModel = max(1, (int) config('services.gemini.retry_attempts', 1));
    }

    /**
     * Emit a Gemini response through the callback used by ChatController SSE.
     * The provider call is non-streaming because Gemini SSE can hang in PHP HTTP.
     */
    public function streamChat(array $messages, callable $onChunk): void
    {
        $response = $this->chat($messages);

        if ($response === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $onChunk($response);

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    // -------------------------------------------------------------------------
    // Non-streaming — used by background jobs and tests
    // -------------------------------------------------------------------------

    /**
     * Blocking chat — waits for the full response and returns it as a string.
     */
    public function chat(array $messages): string
    {
        $this->ensureConfigured();

        return $this->runWithModelFallback(
            fn (string $model): string => $this->chatWithModel($messages, $model),
            'chat'
        );
    }

    private function chatWithModel(array $messages, string $model): string
    {
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post(
                $this->modelEndpoint($model, 'generateContent'),
                $this->requestPayload($messages, $model)
            );

        if ($response->failed()) {
            Log::error('GeminiLlmService::chat failed', [
                'model' => $model,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw $this->providerException('chat', $model, $response->status(), $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert OpenAI-style message array to Gemini's 'contents' format.
     *
     * OpenAI  →  Gemini
     * system  →  handled via system_instruction field (extracted separately)
     * user    →  { role: 'user',  parts: [{ text: '...' }] }
     * assistant → { role: 'model', parts: [{ text: '...' }] }
     */
    private function toGeminiFormat(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                continue; // system prompt extracted separately
            }

            $contents[] = [
                'role'  => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        // Gemini requires the conversation to start with a 'user' turn.
        // If history is empty or starts with model, prepend a minimal user turn.
        if ($contents !== [] && $contents[0]['role'] !== 'user') {
            array_unshift($contents, [
                'role'  => 'user',
                'parts' => [[
                    'text' => 'Continue the conversation and answer the latest user question using the provided evidence.',
                ]],
            ]);
        }

        return $contents;
    }

    private function requestPayload(array $messages, string $model): array
    {
        $systemPrompt = $this->extractSystemPrompt($messages);
        $contents = $this->toGeminiFormat($messages);

        $payload = [
            'contents' => $this->supportsSystemInstruction($model)
                ? $contents
                : $this->mergeSystemPromptIntoContents($contents, $systemPrompt),
            'generationConfig' => $this->generationConfig(),
            'safetySettings' => $this->safetySettings(),
        ];

        if ($systemPrompt !== '' && $this->supportsSystemInstruction($model)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        return $payload;
    }

    private function supportsSystemInstruction(string $model): bool
    {
        return !str_contains($model, 'gemma');
    }

    private function mergeSystemPromptIntoContents(array $contents, string $systemPrompt): array
    {
        if ($systemPrompt === '') {
            return $contents;
        }

        $instruction = "System instructions:\n{$systemPrompt}\n\nUser message:\n";

        if ($contents === []) {
            return [[
                'role' => 'user',
                'parts' => [['text' => $instruction]],
            ]];
        }

        if (($contents[0]['role'] ?? null) !== 'user') {
            array_unshift($contents, [
                'role' => 'user',
                'parts' => [['text' => $systemPrompt]],
            ]);

            return $contents;
        }

        $contents[0]['parts'][0]['text'] = $instruction . ($contents[0]['parts'][0]['text'] ?? '');

        return $contents;
    }

    /**
     * Pull out the system prompt from the messages array.
     * Returns an empty string if no system message is found.
     */
    private function extractSystemPrompt(array $messages): string
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                return $message['content'];
            }
        }
        return '';
    }

    /**
     * Generation config — low temperature is critical for medical accuracy.
        * Gemini Flash max output depends on the configured model.
     */
    private function generationConfig(): array
    {
        return [
            'temperature'     => 0.1,   // factual, conservative
            'topP'            => 0.9,
            'topK'            => 40,
            'maxOutputTokens' => 1024,
            'candidateCount'  => 1,
        ];
    }

    /**
     * Gemini built-in safety filters — block dangerous medical content generation.
     * BLOCK_MEDIUM_AND_ABOVE is the production-safe threshold.
     */
    private function safetySettings(): array
    {
        return [
            [
                'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category'  => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category'  => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ];
    }

    private function ensureConfigured(): void
    {
        if (!$this->apiKey || !$this->model || !$this->baseUrl) {
            throw new RuntimeException('Gemini LLM service is not configured.');
        }
    }

    private function modelEndpoint(string $model, string $operation): string
    {
        $modelPath = str_starts_with($model, 'models/') ? $model : "models/{$model}";

        return "{$this->baseUrl}/{$modelPath}:{$operation}?key={$this->apiKey}";
    }

    private function runWithModelFallback(callable $callback, string $operation): mixed
    {
        $models = $this->modelsToTry();
        $lastException = null;

        foreach ($models as $modelIndex => $model) {
            for ($attempt = 1; $attempt <= $this->attemptsPerModel; $attempt++) {
                try {
                    return $callback($model);
                } catch (Throwable $e) {
                    $lastException = $e;
                    $retrySameModel = $attempt < $this->attemptsPerModel && $this->shouldRetrySameModel($e);
                    $tryNextModel = !$retrySameModel
                        && $modelIndex < count($models) - 1
                        && $this->shouldTryAnotherModel($e);

                    if (!$retrySameModel && !$tryNextModel) {
                        throw $e;
                    }

                    Log::warning("GeminiLlmService::{$operation} retrying after provider failure", [
                        'model' => $model,
                        'attempt' => $attempt,
                        'next_model' => $retrySameModel ? $model : $models[$modelIndex + 1],
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    if ($retrySameModel) {
                        usleep(250000 * $attempt);
                        continue;
                    }

                    break;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Gemini request failed before any model was attempted.');
    }

    private function modelsToTry(): array
    {
        return array_values(array_unique(array_filter(array_merge([$this->model], $this->fallbackModels))));
    }

    private function parseModelList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $model): string => trim((string) $model),
            $value
        )));
    }

    private function shouldTryAnotherModel(Throwable $e): bool
    {
        $status = $this->httpStatusFromException($e);

        return in_array($status, [404, 429, 500, 502, 503, 504], true);
    }

    private function shouldRetrySameModel(Throwable $e): bool
    {
        $status = $this->httpStatusFromException($e);

        return in_array($status, [500, 502, 503, 504], true);
    }

    private function httpStatusFromException(Throwable $e): ?int
    {
        if (preg_match('/HTTP (\d{3})/', $e->getMessage(), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function providerException(string $operation, string $model, int $status, string $body): RuntimeException
    {
        return new RuntimeException("Gemini {$operation} HTTP {$status} for model {$model}: {$body}");
    }
}
