<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents\LLM;

use AgentStateLanguage\Exceptions\AgentException;

/**
 * Abstract base class for LLM agents.
 */
abstract class AbstractLLMAgent implements LLMAgentInterface
{
    protected string $name;
    protected string $apiKey;
    protected string $model;
    protected string $systemPrompt = '';
    protected float $temperature = 0.7;
    protected int $maxTokens = 4096;
    
    /** @var array{input: int, output: int} */
    protected array $lastTokenUsage = ['input' => 0, 'output' => 0];
    protected float $lastCost = 0.0;

    public function __construct(
        string $name,
        string $apiKey,
        string $model,
        string $systemPrompt = ''
    ) {
        $this->name = $name;
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->systemPrompt = $systemPrompt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = max(0.0, min(2.0, $temperature));
        return $this;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = max(1, $maxTokens);
        return $this;
    }

    public function getLastTokenUsage(): array
    {
        return $this->lastTokenUsage;
    }

    public function getLastCost(): float
    {
        return $this->lastCost;
    }

    /**
     * Build the user message from parameters.
     */
    protected function buildUserMessage(array $parameters): string
    {
        // If there's a 'prompt' or 'message' key, use that
        if (isset($parameters['prompt'])) {
            return (string) $parameters['prompt'];
        }
        if (isset($parameters['message'])) {
            return (string) $parameters['message'];
        }
        if (isset($parameters['input'])) {
            return (string) $parameters['input'];
        }
        
        // Otherwise, convert the entire parameters to a formatted message
        return json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Make an HTTP request.
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     * @throws AgentException
     */
    protected function httpPost(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        
        if ($ch === false) {
            throw new AgentException('Failed to initialize cURL', $this->name, 'Agent.NetworkError');
        }

        $headerList = [];
        foreach ($headers as $key => $value) {
            $headerList[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headerList,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new AgentException(
                "HTTP request failed: {$error}",
                $this->name,
                'Agent.NetworkError'
            );
        }

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AgentException(
                'Failed to parse API response: ' . json_last_error_msg(),
                $this->name,
                'Agent.ParseError'
            );
        }

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? $decoded['message'] ?? 'Unknown error';
            throw new AgentException(
                "API error ({$httpCode}): {$errorMessage}",
                $this->name,
                'Agent.APIError'
            );
        }

        return $decoded;
    }
}
