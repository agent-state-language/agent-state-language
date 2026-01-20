<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents\LLM;

use AgentStateLanguage\Exceptions\ASLException;

/**
 * Factory for creating LLM agents.
 */
class LLMAgentFactory
{
    private const PROVIDERS = [
        'claude' => ClaudeAgent::class,
        'anthropic' => ClaudeAgent::class,
        'openai' => OpenAIAgent::class,
        'gpt' => OpenAIAgent::class,
    ];

    /**
     * Create an LLM agent from configuration.
     *
     * @param array{
     *     name: string,
     *     provider: string,
     *     api_key: string,
     *     model?: string,
     *     system_prompt?: string,
     *     temperature?: float,
     *     max_tokens?: int
     * } $config
     * @return LLMAgentInterface
     * @throws ASLException
     */
    public static function create(array $config): LLMAgentInterface
    {
        $name = $config['name'] ?? throw new ASLException('Agent name is required', 'Config.Error');
        $provider = strtolower($config['provider'] ?? '');
        $apiKey = $config['api_key'] ?? throw new ASLException('API key is required', 'Config.Error');

        if (!isset(self::PROVIDERS[$provider])) {
            throw new ASLException(
                "Unknown LLM provider: {$provider}. Supported: " . implode(', ', array_keys(self::PROVIDERS)),
                'Config.Error'
            );
        }

        $agentClass = self::PROVIDERS[$provider];

        // Determine default model based on provider
        $model = $config['model'] ?? match ($provider) {
            'claude', 'anthropic' => 'claude-sonnet-4-20250514',
            'openai', 'gpt' => 'gpt-5.2',
            default => throw new ASLException("No default model for provider: {$provider}", 'Config.Error')
        };

        $systemPrompt = $config['system_prompt'] ?? '';

        /** @var LLMAgentInterface $agent */
        $agent = new $agentClass($name, $apiKey, $model, $systemPrompt);

        // Apply optional settings
        if (isset($config['temperature'])) {
            $agent->setTemperature((float) $config['temperature']);
        }

        if (isset($config['max_tokens'])) {
            $agent->setMaxTokens((int) $config['max_tokens']);
        }

        return $agent;
    }

    /**
     * Create a Claude agent.
     */
    public static function claude(
        string $name,
        string $apiKey,
        string $model = 'claude-sonnet-4-20250514',
        string $systemPrompt = ''
    ): ClaudeAgent {
        return new ClaudeAgent($name, $apiKey, $model, $systemPrompt);
    }

    /**
     * Create an OpenAI agent.
     */
    public static function openai(
        string $name,
        string $apiKey,
        string $model = 'gpt-5.2',
        string $systemPrompt = ''
    ): OpenAIAgent {
        return new OpenAIAgent($name, $apiKey, $model, $systemPrompt);
    }

    /**
     * Create agents from a configuration array.
     *
     * @param array<string, array> $configs
     * @return array<string, LLMAgentInterface>
     */
    public static function createMany(array $configs): array
    {
        $agents = [];

        foreach ($configs as $name => $config) {
            $config['name'] ??= $name;
            $agents[$name] = self::create($config);
        }

        return $agents;
    }
}
