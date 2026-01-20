# Tutorial 13: Integrating Claude PHP Agent

Learn how to integrate the powerful `claude-php-agent` library within Agent State Language workflows for advanced AI agent capabilities.

## What You'll Learn

- Understanding the bridge pattern between claude-php-agent and ASL
- Creating adapter classes that wrap claude-php-agent for ASL workflows
- Mapping ASL parameters to agent task execution
- Token tracking and cost accumulation across workflow states
- Building your first integrated workflow

## Prerequisites

- Completed [Tutorial 12: Building Skills](12-building-skills.md)
- Familiarity with claude-php-agent library
- PHP 8.1 or higher

## Why Integrate claude-php-agent?

While ASL provides powerful workflow orchestration, `claude-php-agent` offers advanced agent capabilities:

| Feature | ASL Native | claude-php-agent |
|---------|------------|------------------|
| Workflow orchestration | ✅ Excellent | ❌ Not available |
| Tool execution loops | ❌ Basic | ✅ ReAct, Reflection, Plan-Execute |
| Memory management | ✅ Workflow-level | ✅ Conversation-level |
| Streaming responses | ❌ Limited | ✅ Full support |
| Extended thinking | ❌ Not available | ✅ Supported |

By combining both, you get the best of workflow orchestration and agent capabilities.

## Step 1: Install Dependencies

```bash
composer require agent-state-language/asl claude-php/agent
```

## Step 2: Understanding the Architecture

The integration uses an adapter pattern:

```
ASL Workflow → AgentRegistry → ClaudeAgentAdapter → claude-php-agent Agent → Claude API
```

The adapter translates between:
- ASL's `execute(array $parameters): array` interface
- claude-php-agent's `run(string $task): AgentResult` method

## Step 3: Create the Adapter

Create `src/Adapters/ClaudeAgentAdapter.php`:

```php
<?php

namespace MyOrg\Adapters;

use AgentStateLanguage\Agents\AgentInterface;
use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudePhp\ClaudePhp;

/**
 * Adapter that wraps claude-php-agent's Agent for use in ASL workflows.
 */
class ClaudeAgentAdapter implements AgentInterface
{
    private Agent $agent;
    private string $name;
    private ?AgentResult $lastResult = null;

    public function __construct(
        string $name,
        Agent $agent
    ) {
        $this->name = $name;
        $this->agent = $agent;
    }

    /**
     * Create an adapter with a new agent instance.
     */
    public static function create(
        string $name,
        ClaudePhp $client,
        string $systemPrompt = '',
        string $model = 'claude-sonnet-4-20250514'
    ): self {
        $agent = Agent::create($client)
            ->withName($name)
            ->withSystemPrompt($systemPrompt)
            ->withModel($model);

        return new self($name, $agent);
    }

    /**
     * Execute the agent with ASL parameters.
     */
    public function execute(array $parameters): array
    {
        // Extract the task/prompt from parameters
        $task = $this->extractTask($parameters);

        // Run the agent
        $this->lastResult = $this->agent->run($task);

        // Convert result to ASL format
        return $this->formatResult($this->lastResult, $parameters);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the underlying agent for advanced configuration.
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Get the last execution result.
     */
    public function getLastResult(): ?AgentResult
    {
        return $this->lastResult;
    }

    /**
     * Extract task string from ASL parameters.
     */
    private function extractTask(array $parameters): string
    {
        // Check common parameter names for the task
        if (isset($parameters['prompt'])) {
            return (string) $parameters['prompt'];
        }

        if (isset($parameters['task'])) {
            return (string) $parameters['task'];
        }

        if (isset($parameters['message'])) {
            return (string) $parameters['message'];
        }

        if (isset($parameters['input'])) {
            return (string) $parameters['input'];
        }

        // If no specific task field, format all parameters as context
        return $this->formatParametersAsTask($parameters);
    }

    /**
     * Format parameters as a structured task.
     */
    private function formatParametersAsTask(array $parameters): string
    {
        $parts = [];

        foreach ($parameters as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue; // Skip internal parameters
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }

            $parts[] = "{$key}: {$value}";
        }

        return implode("\n", $parts);
    }

    /**
     * Format agent result for ASL workflow.
     */
    private function formatResult(AgentResult $result, array $originalParams): array
    {
        $tokenUsage = $result->getTokenUsage();
        $totalTokens = ($tokenUsage['input'] ?? 0) + ($tokenUsage['output'] ?? 0);

        // Calculate cost (simplified - adjust rates as needed)
        $cost = $this->calculateCost($tokenUsage);

        $output = [
            'response' => $result->getAnswer(),
            'success' => $result->isSuccess(),
            'iterations' => $result->getIterations(),
            '_tokens' => $totalTokens,
            '_cost' => $cost,
            '_usage' => $tokenUsage,
        ];

        // Include error information if failed
        if (!$result->isSuccess()) {
            $output['error'] = $result->getError();
        }

        // Try to parse JSON from response
        $parsed = $this->tryParseJson($result->getAnswer());
        if ($parsed !== null) {
            $output['parsed'] = $parsed;
        }

        return $output;
    }

    /**
     * Calculate cost based on token usage.
     */
    private function calculateCost(array $tokenUsage): float
    {
        // Default Claude Sonnet rates per 1M tokens
        $inputRate = 3.00;
        $outputRate = 15.00;

        $inputCost = (($tokenUsage['input'] ?? 0) / 1_000_000) * $inputRate;
        $outputCost = (($tokenUsage['output'] ?? 0) / 1_000_000) * $outputRate;

        return $inputCost + $outputCost;
    }

    /**
     * Try to parse response as JSON.
     */
    private function tryParseJson(string $content): ?array
    {
        // Look for JSON in code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
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
```

## Step 4: Create a Configurable Factory

Create `src/Adapters/ClaudeAgentAdapterFactory.php`:

```php
<?php

namespace MyOrg\Adapters;

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Loops\ReactLoop;
use ClaudeAgents\Loops\ReflectionLoop;
use ClaudeAgents\Loops\PlanExecuteLoop;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating configured ClaudeAgentAdapters.
 */
class ClaudeAgentAdapterFactory
{
    private ClaudePhp $client;
    private LoggerInterface $logger;

    public function __construct(ClaudePhp $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create an adapter from configuration array.
     *
     * @param array{
     *     name: string,
     *     system_prompt?: string,
     *     model?: string,
     *     max_iterations?: int,
     *     max_tokens?: int,
     *     temperature?: float,
     *     loop_strategy?: string,
     *     thinking?: array
     * } $config
     */
    public function create(array $config): ClaudeAgentAdapter
    {
        $name = $config['name'] ?? 'agent';
        $systemPrompt = $config['system_prompt'] ?? '';
        $model = $config['model'] ?? 'claude-sonnet-4-20250514';

        $agent = Agent::create($this->client)
            ->withName($name)
            ->withSystemPrompt($systemPrompt)
            ->withModel($model)
            ->withLogger($this->logger);

        // Apply optional settings
        if (isset($config['max_iterations'])) {
            $agent->maxIterations($config['max_iterations']);
        }

        if (isset($config['max_tokens'])) {
            $agent->maxTokens($config['max_tokens']);
        }

        if (isset($config['temperature'])) {
            $agent->temperature($config['temperature']);
        }

        // Configure loop strategy
        if (isset($config['loop_strategy'])) {
            $strategy = $this->createLoopStrategy($config['loop_strategy']);
            $agent->withLoopStrategy($strategy);
        }

        // Enable extended thinking if configured
        if (isset($config['thinking']['enabled']) && $config['thinking']['enabled']) {
            $budget = $config['thinking']['budget_tokens'] ?? 10000;
            $agent->withThinking($budget);
        }

        return new ClaudeAgentAdapter($name, $agent);
    }

    /**
     * Create multiple adapters from configuration.
     *
     * @param array<string, array> $configs
     * @return array<string, ClaudeAgentAdapter>
     */
    public function createMany(array $configs): array
    {
        $adapters = [];

        foreach ($configs as $name => $config) {
            $config['name'] = $config['name'] ?? $name;
            $adapters[$name] = $this->create($config);
        }

        return $adapters;
    }

    /**
     * Create a loop strategy by name.
     */
    private function createLoopStrategy(string $name): mixed
    {
        return match (strtolower($name)) {
            'react' => new ReactLoop($this->logger),
            'reflection' => new ReflectionLoop($this->logger),
            'plan_execute', 'plan-execute' => new PlanExecuteLoop($this->logger),
            default => new ReactLoop($this->logger),
        };
    }

    /**
     * Shorthand: Create a simple agent adapter.
     */
    public function simple(string $name, string $systemPrompt = ''): ClaudeAgentAdapter
    {
        return $this->create([
            'name' => $name,
            'system_prompt' => $systemPrompt,
        ]);
    }

    /**
     * Shorthand: Create an agent with extended thinking.
     */
    public function withThinking(
        string $name,
        string $systemPrompt = '',
        int $budgetTokens = 10000
    ): ClaudeAgentAdapter {
        return $this->create([
            'name' => $name,
            'system_prompt' => $systemPrompt,
            'thinking' => [
                'enabled' => true,
                'budget_tokens' => $budgetTokens,
            ],
        ]);
    }
}
```

## Step 5: Define the Workflow

Create `workflows/analyze-document.asl.json`:

```json
{
  "Comment": "Document analysis workflow using claude-php-agent",
  "Version": "1.0",
  "StartAt": "AnalyzeDocument",
  "States": {
    "AnalyzeDocument": {
      "Type": "Task",
      "Agent": "DocumentAnalyzer",
      "Parameters": {
        "prompt.$": "States.Format('Analyze the following document and extract key insights:\n\nTitle: {}\nContent: {}\n\nProvide your analysis as JSON with keys: summary, key_points, sentiment, topics', $.title, $.content)"
      },
      "ResultPath": "$.analysis",
      "Next": "CheckSuccess"
    },
    "CheckSuccess": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.analysis.success",
          "BooleanEquals": true,
          "Next": "FormatOutput"
        }
      ],
      "Default": "HandleError"
    },
    "HandleError": {
      "Type": "Fail",
      "Error": "AnalysisFailed",
      "Cause.$": "$.analysis.error"
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "title.$": "$.title",
        "analysis.$": "$.analysis.parsed",
        "rawResponse.$": "$.analysis.response",
        "tokensUsed.$": "$.analysis._tokens",
        "cost.$": "$.analysis._cost"
      },
      "End": true
    }
  }
}
```

## Step 6: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use ClaudePhp\ClaudePhp;
use MyOrg\Adapters\ClaudeAgentAdapterFactory;

// Initialize Claude client
$client = ClaudePhp::make(getenv('ANTHROPIC_API_KEY'));

// Create adapter factory
$factory = new ClaudeAgentAdapterFactory($client);

// Create the document analyzer agent
$analyzer = $factory->create([
    'name' => 'DocumentAnalyzer',
    'system_prompt' => 'You are a document analysis expert. Always respond with valid JSON.',
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 2000,
    'temperature' => 0.3,
]);

// Register with ASL
$registry = new AgentRegistry();
$registry->register('DocumentAnalyzer', $analyzer);

// Load and run workflow
$engine = WorkflowEngine::fromFile('workflows/analyze-document.asl.json', $registry);

$result = $engine->run([
    'title' => 'Quarterly Sales Report Q4 2025',
    'content' => 'Sales increased by 23% compared to Q3, driven primarily by the 
    new product line launch in October. Customer satisfaction scores remained 
    high at 4.7/5. Key challenges included supply chain delays affecting 
    delivery times in November. The team recommends expanding warehouse 
    capacity for Q1 2026 to meet growing demand.'
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    
    echo "=== Document Analysis Complete ===\n\n";
    echo "Title: {$output['title']}\n\n";
    
    if (isset($output['analysis'])) {
        echo "Summary: {$output['analysis']['summary']}\n\n";
        
        echo "Key Points:\n";
        foreach ($output['analysis']['key_points'] ?? [] as $point) {
            echo "  • {$point}\n";
        }
        
        echo "\nSentiment: {$output['analysis']['sentiment']}\n";
        echo "Topics: " . implode(', ', $output['analysis']['topics'] ?? []) . "\n";
    }
    
    echo "\n--- Metrics ---\n";
    echo "Tokens used: {$output['tokensUsed']}\n";
    echo "Cost: $" . number_format($output['cost'], 4) . "\n";
    echo "Duration: " . number_format($result->getDuration(), 2) . "s\n";
} else {
    echo "Error: " . $result->getError() . "\n";
    echo "Cause: " . $result->getErrorCause() . "\n";
}
```

## Expected Output

```
=== Document Analysis Complete ===

Title: Quarterly Sales Report Q4 2025

Summary: Q4 2025 showed strong growth with 23% sales increase, driven by new product launches despite supply chain challenges.

Key Points:
  • 23% sales increase compared to Q3
  • New product line launched in October drove growth
  • Customer satisfaction maintained at 4.7/5
  • Supply chain delays impacted November deliveries
  • Warehouse expansion recommended for Q1 2026

Sentiment: positive
Topics: sales, growth, product launch, customer satisfaction, supply chain

--- Metrics ---
Tokens used: 847
Cost: $0.0089
Duration: 2.34s
```

## Understanding the Integration

### Parameter Mapping

The adapter handles several common parameter patterns:

| ASL Parameter | claude-php-agent Usage |
|---------------|----------------------|
| `prompt` | Direct task string |
| `task` | Direct task string |
| `message` | Direct task string |
| `input` | Direct task string |
| Other keys | Formatted as "key: value" pairs |

### Result Mapping

Agent results are transformed for ASL consumption:

| AgentResult Method | ASL Output Key |
|-------------------|----------------|
| `getAnswer()` | `response` |
| `isSuccess()` | `success` |
| `getIterations()` | `iterations` |
| `getTokenUsage()` | `_tokens`, `_usage` |
| `getError()` | `error` |

### Token Tracking

The adapter automatically tracks tokens and costs:

```php
// Access in workflow output
$tokens = $result->getOutput()['_tokens'];  // Total tokens
$cost = $result->getOutput()['_cost'];      // USD cost

// Or via the workflow result
$totalTokens = $result->getTokensUsed();
$totalCost = $result->getCost();
```

## Experiment

Try these modifications:

### Enable Extended Thinking

```php
$analyzer = $factory->create([
    'name' => 'DeepAnalyzer',
    'system_prompt' => 'You are an expert analyst.',
    'thinking' => [
        'enabled' => true,
        'budget_tokens' => 15000,
    ],
]);
```

### Add Iteration Callbacks

```php
$agent = $analyzer->getAgent();
$agent->onIteration(function ($iteration, $response, $context) {
    echo "Iteration {$iteration}: Processing...\n";
});
```

### Chain Multiple Agents

```json
{
  "StartAt": "Extract",
  "States": {
    "Extract": {
      "Type": "Task",
      "Agent": "Extractor",
      "ResultPath": "$.extracted",
      "Next": "Analyze"
    },
    "Analyze": {
      "Type": "Task",
      "Agent": "Analyzer",
      "Parameters": {
        "prompt.$": "States.Format('Analyze this data: {}', $.extracted.response)"
      },
      "End": true
    }
  }
}
```

## Common Mistakes

### Missing API Key

```
Error: Agent.APIError
Cause: API error (401): Invalid API key
```

**Fix**: Ensure `ANTHROPIC_API_KEY` environment variable is set.

### Parameter Key Mismatch

```php
// Workflow sends 'query' but adapter expects 'prompt'
"Parameters": {
    "query.$": "$.userQuestion"  // Won't be recognized as task
}
```

**Fix**: Use standard parameter names: `prompt`, `task`, `message`, or `input`.

### Unregistered Agent

```
Error: States.AgentNotFound
Cause: Agent 'DocumentAnalyzer' is not registered
```

**Fix**: Ensure agent is registered before running workflow:

```php
$registry->register('DocumentAnalyzer', $analyzer);
```

### JSON Parse Failures

If the agent doesn't return valid JSON, the `parsed` field will be null:

```php
if ($output['analysis']['parsed'] === null) {
    // Fall back to raw response
    $data = $output['analysis']['response'];
}
```

**Fix**: Use explicit JSON instructions in system prompt.

## Summary

You've learned:

- ✅ The adapter pattern for integrating claude-php-agent with ASL
- ✅ Creating `ClaudeAgentAdapter` that implements `AgentInterface`
- ✅ Building a factory for configurable agent creation
- ✅ Parameter and result mapping between systems
- ✅ Token tracking and cost accumulation
- ✅ Running your first integrated workflow

## Next Steps

- [Tutorial 14: Tool-Enabled Agent Workflows](14-tool-enabled-agent-workflows.md) - Add tools to your agents
- [Tutorial 15: Multi-Agent Orchestration](15-multi-agent-orchestration.md) - Coordinate multiple agents
