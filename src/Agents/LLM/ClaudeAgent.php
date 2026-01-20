<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents\LLM;

use AgentStateLanguage\Exceptions\AgentException;

/**
 * Claude (Anthropic) LLM agent.
 */
class ClaudeAgent extends AbstractLLMAgent
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * Cost per 1M tokens (input/output) by model.
     */
    private const MODEL_COSTS = [
        // Claude 4.5 models
        'claude-opus-4-5-20250514' => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-4-5-20250514' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5-20250514' => ['input' => 1.00, 'output' => 5.00],
        // Claude 4 models
        'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
        // Claude 3.5 models (legacy)
        'claude-3-5-sonnet-latest' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku-latest' => ['input' => 0.80, 'output' => 4.00],
        'claude-3-5-haiku-20241022' => ['input' => 0.80, 'output' => 4.00],
    ];

    public function __construct(
        string $name,
        string $apiKey,
        string $model = 'claude-sonnet-4-20250514',
        string $systemPrompt = ''
    ) {
        parent::__construct($name, $apiKey, $model, $systemPrompt);
    }

    public function execute(array $parameters): array
    {
        $userMessage = $this->buildUserMessage($parameters);

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        // Add system prompt if set
        if (!empty($this->systemPrompt)) {
            $payload['system'] = $this->systemPrompt;
        }

        // Add temperature if not default
        if ($this->temperature !== 1.0) {
            $payload['temperature'] = $this->temperature;
        }

        try {
            $response = $this->httpPost(
                self::API_URL,
                $payload,
                [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                ]
            );
        } catch (AgentException $e) {
            throw $e;
        }

        // Extract response content
        $content = $this->extractContent($response);

        // Track token usage
        $this->lastTokenUsage = [
            'input' => $response['usage']['input_tokens'] ?? 0,
            'output' => $response['usage']['output_tokens'] ?? 0,
        ];

        // Calculate cost
        $this->lastCost = $this->calculateCost(
            $this->lastTokenUsage['input'],
            $this->lastTokenUsage['output']
        );

        // Try to parse as JSON if it looks like JSON
        $parsed = $this->tryParseJson($content);

        return [
            'response' => $content,
            'parsed' => $parsed,
            'model' => $response['model'] ?? $this->model,
            'stop_reason' => $response['stop_reason'] ?? null,
            '_tokens' => $this->lastTokenUsage['input'] + $this->lastTokenUsage['output'],
            '_cost' => $this->lastCost,
            '_usage' => $this->lastTokenUsage,
        ];
    }

    /**
     * Extract text content from response.
     */
    private function extractContent(array $response): string
    {
        $content = $response['content'] ?? [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                return $block['text'] ?? '';
            }
        }

        return '';
    }

    /**
     * Calculate cost based on token usage.
     */
    private function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::MODEL_COSTS[$this->model] ?? ['input' => 3.00, 'output' => 15.00];

        $inputCost = ($inputTokens / 1_000_000) * $costs['input'];
        $outputCost = ($outputTokens / 1_000_000) * $costs['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Try to parse content as JSON.
     */
    private function tryParseJson(string $content): ?array
    {
        // Look for JSON in code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $jsonStr = trim($matches[1]);
            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try parsing the whole content
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }
}
