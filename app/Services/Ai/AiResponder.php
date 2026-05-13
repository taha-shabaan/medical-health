<?php

namespace App\Services\Ai;

use App\Models\AiKnowledgeEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiResponder
{
    /**
     * @param  array<int, array{sender:string, content:string}>  $history
     */
    public function respond(array $history, string $roleContext = 'general'): string
    {
        $driver = (string) config('services.ai.driver', 'database');

        if ($driver === 'database') {
            return $this->databaseResponse($history, $roleContext);
        }

        if ($driver === 'fake') {
            return $this->fakeResponse($history, $roleContext);
        }

        // backward compatible with old AI_DRIVER=openai and extensible via LLM_PROVIDER
        $provider = strtolower((string) config('services.ai.llm_provider', 'openai'));
        if ($provider === 'gemini') {
            return $this->geminiResponse($history, $roleContext);
        }

        if ($provider === 'openrouter') {
            return $this->openAiCompatibleResponse(
                $history,
                $roleContext,
                (string) config('services.ai.openrouter_api_key'),
                (string) config('services.ai.openrouter_endpoint'),
                (string) config('services.ai.openrouter_model'),
                $this->csvList((string) config('services.ai.openrouter_fallback_models', ''))
            );
        }

        // default: OpenAI-compatible endpoint from AI_* variables
        return $this->openAiCompatibleResponse(
            $history,
            $roleContext,
            (string) config('services.ai.api_key'),
            (string) config('services.ai.endpoint'),
            (string) config('services.ai.model', 'gpt-4o-mini')
        );
    }

    /**
     * @param  array<int, array{sender:string, content:string}>  $history
     */
    private function openAiCompatibleResponse(
        array $history,
        string $roleContext,
        string $apiKey,
        string $endpoint,
        string $model,
        array $fallbackModels = []
    ): string
    {
        if ($apiKey === '') {
            throw new RuntimeException('AI API key is missing for OpenAI-compatible provider.');
        }

        $messages = [
            [
                'role' => 'system',
                'content' => (string) config('services.ai.system_prompt'),
            ],
            [
                'role' => 'system',
                'content' => 'User role context: '.$roleContext,
            ],
        ];

        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['sender'] === 'assistant' ? 'assistant' : 'user',
                'content' => $item['content'],
            ];
        }

        $models = array_values(array_unique(array_filter([$model, ...$fallbackModels])));
        $lastError = null;
        $attempts = (int) config('services.ai.retry_attempts', 1);

        foreach ($models as $candidateModel) {
            for ($i = 0; $i < $attempts; $i++) {
                try {
                    $response = Http::timeout((int) config('services.ai.timeout', 20))
                        ->withToken($apiKey)
                        ->acceptJson()
                        ->post($endpoint, [
                            'model' => $candidateModel,
                            'messages' => $messages,
                            'temperature' => 0.4,
                        ]);

                    if (! $response->successful()) {
                        $lastError = new RuntimeException('AI provider request failed for model '.$candidateModel);
                        continue;
                    }

                    $text = (string) Arr::get($response->json(), 'choices.0.message.content', '');
                    if (trim($text) !== '') {
                        return trim($text);
                    }

                    $lastError = new RuntimeException('AI provider returned empty response.');
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }
        }

        throw new RuntimeException('AI provider request failed.', 0, $lastError);
    }

    /**
     * @param  array<int, array{sender:string, content:string}>  $history
     */
    private function geminiResponse(array $history, string $roleContext): string
    {
        $apiKey = (string) config('services.ai.gemini_api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is missing.');
        }

        $primary = trim((string) config('services.ai.gemini_model', 'gemini-1.5-flash'));
        $models = array_values(array_unique(array_filter([$primary, ...$this->csvList((string) config('services.ai.gemini_fallback_models', ''))])));
        $base = rtrim((string) config('services.ai.gemini_endpoint', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');

        $contents = [];
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => (string) config('services.ai.system_prompt')],
                ['text' => 'User role context: '.$roleContext],
            ],
        ];

        foreach ($history as $item) {
            $contents[] = [
                'role' => $item['sender'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) $item['content']]],
            ];
        }

        $attempts = (int) config('services.ai.retry_attempts', 1);
        $lastError = null;

        foreach ($models as $model) {
            $url = $base.'/'.$model.':generateContent?key='.urlencode($apiKey);
            for ($i = 0; $i < $attempts; $i++) {
                try {
                    $response = Http::timeout((int) config('services.ai.timeout', 20))
                        ->acceptJson()
                        ->post($url, [
                            'contents' => $contents,
                            'generationConfig' => [
                                'temperature' => 0.4,
                            ],
                        ]);

                    if (! $response->successful()) {
                        $lastError = new RuntimeException('Gemini provider request failed for model '.$model);
                        continue;
                    }

                    $text = (string) Arr::get($response->json(), 'candidates.0.content.parts.0.text', '');
                    if (trim($text) !== '') {
                        return trim($text);
                    }

                    $lastError = new RuntimeException('Gemini provider returned empty response.');
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }
        }

        throw new RuntimeException('Gemini provider request failed.', 0, $lastError);
    }

    /** @return array<int,string> */
    private function csvList(string $value): array
    {
        return array_values(array_filter(array_map(static fn ($x) => trim($x), explode(',', $value))));
    }

    /**
     * @param  array<int, array{sender:string, content:string}>  $history
     */
    private function fakeResponse(array $history, string $roleContext): string
    {
        $last = trim((string) Arr::get(end($history), 'content', ''));
        $prefix = match ($roleContext) {
            'doctor' => 'Medical assistant (doctor mode): ',
            'patient' => 'Medical assistant (patient mode): ',
            default => 'Medical assistant: ',
        };

        if ($last === '') {
            return $prefix.'How can I help you today?';
        }

        return $prefix.'I understood your message: "'.$last.'". This is a development response. Configure AI_DRIVER=openai for live model output.';
    }

    /**
     * Match last user message against `ai_knowledge_entries` (keyword / substring triggers). No external API.
     *
     * @param  array<int, array{sender:string, content:string}>  $history
     */
    private function databaseResponse(array $history, string $roleContext): string
    {
        $last = trim((string) Arr::get(end($history), 'content', ''));
        if ($last === '') {
            return (string) config('services.ai.database_empty_user', 'How can I help you?');
        }

        $context = $roleContext !== '' ? $roleContext : 'general';

        $entries = AiKnowledgeEntry::query()
            ->active()
            ->forRoleContext($context)
            ->orderByDesc('priority')
            ->get();

        $bestScore = 0;
        $bestResponse = null;

        foreach ($entries as $entry) {
            $score = 0;
            $parts = preg_split('/[,\n،]+/u', (string) $entry->triggers) ?: [];
            foreach ($parts as $part) {
                $trigger = trim($part);
                if ($trigger === '') {
                    continue;
                }
                if (mb_stripos($last, $trigger) !== false) {
                    $score += mb_strlen($trigger);
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestResponse = (string) $entry->response;
            }
        }

        if ($bestResponse !== null && $bestScore > 0) {
            return $bestResponse;
        }

        return (string) config('services.ai.database_fallback');
    }
}
