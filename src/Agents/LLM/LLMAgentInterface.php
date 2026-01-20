<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents\LLM;

use AgentStateLanguage\Agents\AgentInterface;

/**
 * Interface for LLM-based agents.
 */
interface LLMAgentInterface extends AgentInterface
{
    /**
     * Set the system prompt for the agent.
     */
    public function setSystemPrompt(string $prompt): self;

    /**
     * Get the system prompt.
     */
    public function getSystemPrompt(): string;

    /**
     * Set the model to use.
     */
    public function setModel(string $model): self;

    /**
     * Get the model name.
     */
    public function getModel(): string;

    /**
     * Set temperature for response generation.
     */
    public function setTemperature(float $temperature): self;

    /**
     * Set maximum tokens for response.
     */
    public function setMaxTokens(int $maxTokens): self;

    /**
     * Get token usage from last request.
     */
    public function getLastTokenUsage(): array;

    /**
     * Get cost from last request.
     */
    public function getLastCost(): float;
}
