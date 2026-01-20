<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents\LLM;

use AgentStateLanguage\Exceptions\AgentException;

/**
 * OpenAI LLM agent.
 */
class OpenAIAgent extends AbstractLLMAgent
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Cost per 1M tokens (input/output) by model.
     */
    private const MODEL_COSTS = [
        // GPT-5 flagship models
        'gpt-5.2' => ['input' => 1.75, 'output' => 14.00],
        'gpt-5.2-pro' => ['input' => 21.00, 'output' => 168.00],
        'gpt-5-mini' => ['input' => 0.25, 'output' => 2.00],
        'gpt-5-nano' => ['input' => 0.05, 'output' => 0.40],
        // Reasoning models (o-series)
        'o3' => ['input' => 2.00, 'output' => 8.00],
        'o3-pro' => ['input' => 20.00, 'output' => 80.00],
        'o4-mini' => ['input' => 1.10, 'output' => 4.40],
        // GPT-4 models (legacy)
        'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ];

    private bool $jsonMode = false;

    public function __construct(
        string $name,
        string $apiKey,
        string $model = 'gpt-5.2',
        string $systemPrompt = ''
    ) {
        parent::__construct($name, $apiKey, $model, $systemPrompt);
    }

    /**
     * Enable JSON mode for structured responses.
     */
    public function setJsonMode(bool $enabled): self
    {
        $this->jsonMode = $enabled;
        return $this;
    }

    public function execute(array $parameters): array
    {
        $userMessage = $this->buildUserMessage($parameters);

        $messages = [];

        // Add system message if set
        if (!empty($this->systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        // Add JSON mode if enabled
        if ($this->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->httpPost(
                self::API_URL,
                $payload,
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}",
                ]
            );
        } catch (AgentException $e) {
            throw $e;
        }

        // Extract response content
        $content = $this->extractContent($response);

        // Track token usage
        $usage = $response['usage'] ?? [];
        $this->lastTokenUsage = [
            'input' => $usage['prompt_tokens'] ?? 0,
            'output' => $usage['completion_tokens'] ?? 0,
        ];

        // Calculate cost
        $this->lastCost = $this->calculateCost(
            $this->lastTokenUsage['input'],
            $this->lastTokenUsage['output']
        );

        // Try to parse as JSON
        $parsed = $this->tryParseJson($content);

        return [
            'response' => $content,
            'parsed' => $parsed,
            'model' => $response['model'] ?? $this->model,
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
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
        $choices = $response['choices'] ?? [];

        if (empty($choices)) {
            return '';
        }

        return $choices[0]['message']['content'] ?? '';
    }

    /**
     * Calculate cost based on token usage.
     */
    private function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::MODEL_COSTS[$this->model] ?? ['input' => 1.75, 'output' => 14.00];

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
