<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class Service
{
    /**
     * Get config value safely
     */
    protected function getConfig(string $service, string $key = null)
    {
        $config = config("services.$service");

        if ($key) {
            return $config[$key] ?? null;
        }

        return $config;
    }

    /**
     * Generic HTTP client for AI APIs
     */
    protected function http(string $service)
    {
        $config = $this->getConfig($service);

        $client = Http::timeout(20)
            ->retry(2, 200)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ]);

        // 🔑 Attach API key if exists
        if (!empty($config['api_key'])) {
            $client = $client->withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
            ]);
        }

        return $client;
    }

    /**
     * Log AI errors safely
     */
    protected function logError(string $service, $response)
    {
        Log::error("{$service} API error", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
    }
}