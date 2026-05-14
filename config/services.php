<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payments' => [
        // fake: succeeds locally for dev/testing
        // rest: calls external provider endpoint with bearer token
        'driver' => env('PAYMENT_GATEWAY_DRIVER', 'fake'),
        'endpoint' => env('PAYMENT_GATEWAY_ENDPOINT', ''),
        'api_key' => env('PAYMENT_GATEWAY_API_KEY', ''),
        'timeout' => env('PAYMENT_GATEWAY_TIMEOUT', 15),
        // HMAC SHA256 secret used by /api/payments/webhook
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', ''),
    ],

    /*
    | OTP / transactional SMS
    | - driver=auto: إذا كان الرابط يحتوي africastalking.com يُستخدم تنسيق Africa's Talking
    |   وإلا يُرسل JSON عام (api_key, to, sender, message).
    */
    'sms' => [
        'url' => env('SMS_PROVIDER_URL', ''),
        'api_key' => env('SMS_PROVIDER_API_KEY') ?: env('AT_API_KEY', ''),
        'username' => env('AT_USERNAME', 'sandbox'),
        /** Alphanumeric / short-code — لا يمرّ إلا لو معتمد في لوحة Africa’s Talking؛ وإلا سيُرفض الطلب غالبًا */
        'from' => env('SMS_PROVIDER_SENDER', ''),
        'driver' => env('SMS_PROVIDER_DRIVER', 'auto'),
        /** افتراضيًا نحاول الأول بدون from (يزيد نجاح OTP)، ثم نعيد المحاولة مع from إذا وُجد */
        'at_try_without_sender_first' => filter_var(
            env('SMS_AT_TRY_WITHOUT_SENDER_FIRST', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'ai' => [
        // fake: simple echo (dev)
        // database: replies from ai_knowledge_entries (no API key)
        // openai: calls OpenAI Chat Completions API
        'driver' => env('AI_DRIVER', 'database'),
        // openai | openrouter | gemini
        'llm_provider' => env('LLM_PROVIDER', 'openai'),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'endpoint' => env('AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        // OpenRouter / Qwen compatibility
        'openrouter_api_key' => env('QWEN_API_KEY', ''),
        'openrouter_model' => env('QWEN_MODEL', 'qwen/qwen-2.5-72b-instruct'),
        'openrouter_fallback_models' => env('QWEN_FALLBACK_MODELS', 'qwen/qwen-2.5-7b-instruct'),
        'openrouter_endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
        // Gemini native API compatibility
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
        'gemini_model' => env('GEMINI_LLM_MODEL', 'gemini-1.5-flash'),
        'gemini_fallback_models' => env('GEMINI_FALLBACK_MODELS', ''),
        'gemini_endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
        'timeout' => (int) env('AI_TIMEOUT', 20),
        'retry_attempts' => max(1, (int) env('AI_RETRY_ATTEMPTS', 1)),
        'system_prompt' => env('AI_SYSTEM_PROMPT', 'You are a safe medical assistant. Give general guidance only and suggest seeing a doctor for emergencies.'),
        'database_fallback' => env(
            'AI_DATABASE_FALLBACK',
            'لم أجد إجابة مطابقة في قاعدة المعرفة. جرّب كلمات أخرى أو راجع المشرف لإضافة ردود جديدة.'
        ),
        'database_empty_user' => env('AI_DATABASE_EMPTY', 'كيف أقدر أساعدك؟'),
    ],

];
