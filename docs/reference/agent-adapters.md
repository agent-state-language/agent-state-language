# Agent Adapters Reference

Agent State Language uses adapters to connect workflow Task states with actual agent implementations. This document covers how to create and use agent adapters.

## Overview

An agent adapter is a bridge between the ASL engine and your agent implementation. It:

1. Receives parameters from the workflow
2. Executes the agent logic
3. Returns results back to the workflow

## The AgentInterface

All agents must implement the `AgentInterface`:

```php
<?php

namespace AgentStateLanguage\Agents;

interface AgentInterface
{
    /**
     * Execute the agent with the given parameters.
     *
     * @param array $parameters Parameters from the workflow
     * @return array Result to store in the workflow state
     */
    public function execute(array $parameters): array;

    /**
     * Get the agent's name for registration.
     */
    public function getName(): string;
}
```

## Creating a Simple Agent

### Basic Implementation

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class EchoAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        return [
            'echo' => $parameters['message'] ?? 'No message provided',
            'timestamp' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'EchoAgent';
    }
}
```

### Registering and Using

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

$registry = new AgentRegistry();
$registry->register('EchoAgent', new EchoAgent());

$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$result = $engine->run(['message' => 'Hello, World!']);
```

## Claude Agent Adapter

The `ClaudeAgentAdapter` wraps agents from the `claude-php-agent` package:

```php
<?php

use AgentStateLanguage\Agents\Adapters\ClaudeAgentAdapter;
use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;

// Create a Claude agent
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful assistant.')
    ->maxIterations(5);

// Wrap it in the adapter
$adapter = new ClaudeAgentAdapter($agent);

// Register with the engine
$registry->register('Assistant', $adapter);
```

### ClaudeAgentAdapter Options

```php
<?php

$adapter = new ClaudeAgentAdapter($agent, [
    'promptKey' => 'prompt',      // Parameter key containing the prompt
    'responseKey' => 'response',  // Key for the response in results
    'includeMetadata' => true,    // Include token counts, timing, etc.
]);
```

## Stateful Agents

For agents that maintain state between calls:

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\StatefulAgentInterface;

class ConversationAgent implements AgentInterface, StatefulAgentInterface
{
    private array $history = [];

    public function execute(array $parameters): array
    {
        $message = $parameters['message'];
        $this->history[] = ['role' => 'user', 'content' => $message];

        // Generate response (simplified)
        $response = $this->generateResponse($message);
        $this->history[] = ['role' => 'assistant', 'content' => $response];

        return [
            'response' => $response,
            'turnCount' => count($this->history) / 2
        ];
    }

    public function getState(): array
    {
        return ['history' => $this->history];
    }

    public function setState(array $state): void
    {
        $this->history = $state['history'] ?? [];
    }

    public function resetState(): void
    {
        $this->history = [];
    }

    public function getName(): string
    {
        return 'ConversationAgent';
    }

    private function generateResponse(string $message): string
    {
        // Your LLM call here
        return "Response to: $message";
    }
}
```

## Tool-Enabled Agents

For agents that can use tools:

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;

class ResearchAgent implements AgentInterface, ToolAwareAgentInterface
{
    private array $allowedTools = [];
    private $toolExecutor;

    public function setAllowedTools(array $tools): void
    {
        $this->allowedTools = $tools;
    }

    public function setToolExecutor(callable $executor): void
    {
        $this->toolExecutor = $executor;
    }

    public function execute(array $parameters): array
    {
        $query = $parameters['query'];

        // Check if we can use web_search
        if (in_array('web_search', $this->allowedTools)) {
            $searchResults = ($this->toolExecutor)('web_search', [
                'query' => $query
            ]);
            return ['results' => $searchResults];
        }

        return ['error' => 'web_search tool not available'];
    }

    public function getName(): string
    {
        return 'ResearchAgent';
    }
}
```

### Using in Workflow

```json
{
  "Research": {
    "Type": "Task",
    "Agent": "ResearchAgent",
    "Tools": {
      "Allowed": ["web_search", "fetch_webpage"],
      "Denied": ["execute_shell"]
    },
    "Parameters": {
      "query.$": "$.searchQuery"
    },
    "Next": "ProcessResults"
  }
}
```

## Context-Aware Agents

For agents that need context management:

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ContextAwareAgentInterface;

class AnalysisAgent implements AgentInterface, ContextAwareAgentInterface
{
    private array $context = [];
    private int $maxTokens = 8000;

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function setMaxContextTokens(int $tokens): void
    {
        $this->maxTokens = $tokens;
    }

    public function execute(array $parameters): array
    {
        // Build prompt with context
        $prompt = $this->buildPrompt($parameters['task'], $this->context);

        // Execute with LLM
        $response = $this->callLLM($prompt);

        return ['analysis' => $response];
    }

    private function buildPrompt(string $task, array $context): string
    {
        $contextStr = '';
        foreach ($context as $key => $value) {
            $contextStr .= "$key: $value\n";
        }

        return "Context:\n$contextStr\n\nTask: $task";
    }

    public function getName(): string
    {
        return 'AnalysisAgent';
    }
}
```

### Using in Workflow

```json
{
  "Analyze": {
    "Type": "Task",
    "Agent": "AnalysisAgent",
    "Context": {
      "Strategy": "sliding_window",
      "MaxTokens": 6000,
      "Priority": ["$.currentFile", "$.recentChanges"]
    },
    "Parameters": {
      "task.$": "$.analysisTask"
    },
    "Next": "Report"
  }
}
```

## Agent Factory

For dynamic agent creation:

```php
<?php

use AgentStateLanguage\Agents\AgentFactory;
use AgentStateLanguage\Agents\AgentRegistry;

class MyAgentFactory implements AgentFactory
{
    private ClaudePhp $client;

    public function __construct(ClaudePhp $client)
    {
        $this->client = $client;
    }

    public function create(string $name, array $config = []): AgentInterface
    {
        return match ($name) {
            'Analyzer' => $this->createAnalyzer($config),
            'Researcher' => $this->createResearcher($config),
            'Writer' => $this->createWriter($config),
            default => throw new \InvalidArgumentException("Unknown agent: $name")
        };
    }

    private function createAnalyzer(array $config): AgentInterface
    {
        $agent = Agent::create($this->client)
            ->withSystemPrompt($config['systemPrompt'] ?? 'You are an analyzer.')
            ->maxIterations($config['maxIterations'] ?? 5);

        return new ClaudeAgentAdapter($agent);
    }

    // ... other factory methods
}

// Usage
$factory = new MyAgentFactory($client);
$registry = AgentRegistry::fromFactory($factory, [
    'Analyzer' => ['systemPrompt' => 'Analyze code for issues.'],
    'Researcher' => ['maxIterations' => 10],
    'Writer' => []
]);
```

## Error Handling

Agents should throw specific exceptions for proper retry/catch handling:

```php
<?php

use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Exceptions\RateLimitException;
use AgentStateLanguage\Exceptions\TimeoutException;

class RobustAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        try {
            return $this->doExecute($parameters);
        } catch (RateLimitException $e) {
            // Will trigger retry logic if configured
            throw $e;
        } catch (TimeoutException $e) {
            // Will trigger retry or catch
            throw $e;
        } catch (\Exception $e) {
            // Wrap in AgentException for proper handling
            throw new AgentException(
                "Agent execution failed: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    // ...
}
```

### Using with Retry/Catch

```json
{
  "CallAgent": {
    "Type": "Task",
    "Agent": "RobustAgent",
    "Retry": [
      {
        "ErrorEquals": ["RateLimitException"],
        "IntervalSeconds": 30,
        "MaxAttempts": 3,
        "BackoffRate": 2.0
      },
      {
        "ErrorEquals": ["TimeoutException"],
        "IntervalSeconds": 5,
        "MaxAttempts": 2
      }
    ],
    "Catch": [
      {
        "ErrorEquals": ["AgentException"],
        "ResultPath": "$.error",
        "Next": "HandleError"
      }
    ],
    "Next": "Success"
  }
}
```

## Testing Agents

### Mock Agent for Testing

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class MockAgent implements AgentInterface
{
    private array $responses;
    private int $callCount = 0;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function execute(array $parameters): array
    {
        $response = $this->responses[$this->callCount] ?? end($this->responses);
        $this->callCount++;
        return $response;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function getName(): string
    {
        return 'MockAgent';
    }
}

// Usage in tests
$mock = new MockAgent([
    ['result' => 'first call'],
    ['result' => 'second call']
]);

$registry = new AgentRegistry();
$registry->register('TestAgent', $mock);

$engine = WorkflowEngine::fromFile('test.asl.json', $registry);
$engine->run([]);

assert($mock->getCallCount() === 2);
```
