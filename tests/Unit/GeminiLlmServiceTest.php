<?php

namespace Tests\Unit;

use App\Services\Rag\GeminiLlmService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLlmServiceTest extends TestCase
{
    public function test_stream_chat_uses_fallback_model_when_primary_is_unavailable(): void
    {
        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.llm_model' => 'gemini-2.5-flash',
            'services.gemini.fallback_models' => ['gemini-2.5-flash-lite'],
            'services.gemini.retry_attempts' => 1,
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $urls = [];

        Http::fake(function (Request $request) use (&$urls) {
            $url = (string) $request->url();
            $urls[] = $url;

            if (str_contains($url, 'gemini-2.5-flash:generateContent')) {
                return Http::response([
                    'error' => [
                        'code' => 503,
                        'message' => 'This model is currently experiencing high demand.',
                        'status' => 'UNAVAILABLE',
                    ],
                ], 503);
            }

            if (str_contains($url, 'gemini-2.5-flash-lite:generateContent')) {
                return Http::response([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => 'Fallback model answer.',
                            ]],
                        ],
                    ]],
                ], 200);
            }

            return Http::response([
                'error' => [
                    'message' => "Unexpected Gemini URL: {$url}",
                ],
            ], 500);
        });

        $service = new GeminiLlmService();
        $chunks = [];

        $service->streamChat([
            ['role' => 'system', 'content' => 'Be helpful.'],
            ['role' => 'user', 'content' => 'Hello'],
        ], function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });

        $this->assertSame(['Fallback model answer.'], $chunks);
        $this->assertTrue(collect($urls)->contains(fn (string $url): bool => str_contains($url, 'gemini-2.5-flash:generateContent')));
        $this->assertTrue(collect($urls)->contains(fn (string $url): bool => str_contains($url, 'gemini-2.5-flash-lite:generateContent')));
        $this->assertFalse(collect($urls)->contains(fn (string $url): bool => str_contains($url, 'streamGenerateContent')));
    }

    public function test_gemma_models_receive_system_prompt_inline(): void
    {
        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.llm_model' => 'gemma-3-12b-it',
            'services.gemini.fallback_models' => [],
            'services.gemini.retry_attempts' => 1,
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $payloads = [];

        Http::fake(function (Request $request) use (&$payloads) {
            $payloads[] = $request->data();

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'Gemma answer.',
                        ]],
                    ],
                ]],
            ], 200);
        });

        $service = new GeminiLlmService();

        $answer = $service->chat([
            ['role' => 'system', 'content' => 'Stay safe.'],
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertSame('Gemma answer.', $answer);
        $this->assertArrayNotHasKey('system_instruction', $payloads[0]);
        $this->assertStringContainsString('System instructions:', $payloads[0]['contents'][0]['parts'][0]['text']);
        $this->assertStringContainsString('Stay safe.', $payloads[0]['contents'][0]['parts'][0]['text']);
        $this->assertStringContainsString('Hello', $payloads[0]['contents'][0]['parts'][0]['text']);
    }
}
