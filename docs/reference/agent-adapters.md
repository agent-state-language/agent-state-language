# Agent Adapters Reference

Agent State Language provides built-in adapters for popular LLM providers.

## Supported Providers

| Provider | Class | Default Model |
|----------|-------|---------------|
| Claude (Anthropic) | `ClaudeAgent` | `claude-sonnet-4-20250514` |
| OpenAI | `OpenAIAgent` | `gpt-5.2` |

## Claude Agent

### Basic Usage

```php
<?php

use AgentStateLanguage\Agents\LLM\ClaudeAgent;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;

// Create a Claude agent
$agent = new ClaudeAgent(
    name: 'Analyzer',
    apiKey: getenv('ANTHROPIC_API_KEY'),
    model: 'claude-sonnet-4-20250514',
    systemPrompt: 'You are a code analysis expert. Respond with JSON.'
);

// Configure options
$agent->setTemperature(0.3)
      ->setMaxTokens(2000);

// Register with the engine
$registry = new AgentRegistry();
$registry->register('Analyzer', $agent);

// Use in workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$result = $engine->run(['code' => 'function test() { return true; }']);
```

### Supported Models

| Model | Cost (per 1M tokens) | Notes |
|-------|---------------------|-------|
| `claude-opus-4-5-20250514` | $5.00 in / $25.00 out | Most intelligent, effort parameter |
| `claude-sonnet-4-5-20250514` | $3.00 in / $15.00 out | Best balance for agents |
| `claude-haiku-4-5-20250514` | $1.00 in / $5.00 out | Fastest, near-frontier |
| `claude-sonnet-4-20250514` | $3.00 in / $15.00 out | Default, balanced |
| `claude-3-5-sonnet-latest` | $3.00 in / $15.00 out | Legacy 3.5 Sonnet |
| `claude-3-5-haiku-latest` | $0.80 in / $4.00 out | Legacy, fast |

### Response Structure

```php
$result = $agent->execute(['prompt' => 'Analyze this code...']);

// Response includes:
[
    'response' => 'The raw text response from Claude',
    'parsed' => ['key' => 'value'],  // If JSON was detected
    'model' => 'claude-sonnet-4-20250514',
    'stop_reason' => 'end_turn',
    '_tokens' => 1500,           // Total tokens used
    '_cost' => 0.0045,           // Cost in USD
    '_usage' => [
        'input' => 500,
        'output' => 1000
    ]
]
```

## OpenAI Agent

### Basic Usage

```php
<?php

use AgentStateLanguage\Agents\LLM\OpenAIAgent;

$agent = new OpenAIAgent(
    name: 'Summarizer',
    apiKey: getenv('OPENAI_API_KEY'),
    model: 'gpt-5.2',
    systemPrompt: 'You are a document summarizer.'
);

// Enable JSON mode for structured output
$agent->setJsonMode(true);
```

### Supported Models

| Model | Cost (per 1M tokens) | Notes |
|-------|---------------------|-------|
| `gpt-5.2` | $1.75 in / $14.00 out | Default, best for coding/agents |
| `gpt-5.2-pro` | $21.00 in / $168.00 out | Smartest, most precise |
| `gpt-5-mini` | $0.25 in / $2.00 out | Fast & efficient |
| `gpt-5-nano` | $0.05 in / $0.40 out | Lowest cost |
| `o3` | $2.00 in / $8.00 out | Frontier reasoning |
| `o3-pro` | $20.00 in / $80.00 out | Extended thinking |
| `o4-mini` | $1.10 in / $4.40 out | Efficient reasoning |
| `gpt-4.1` | $2.00 in / $8.00 out | Legacy, non-reasoning |
| `gpt-4o` | $2.50 in / $10.00 out | Legacy multimodal |

### JSON Mode

OpenAI supports structured JSON output:

```php
$agent = new OpenAIAgent('Parser', $apiKey, 'gpt-5.2');
$agent->setJsonMode(true);
$agent->setSystemPrompt('Extract entities as JSON: {"entities": [...]}');

$result = $agent->execute(['prompt' => 'Apple Inc. is based in Cupertino.']);
// $result['parsed'] will contain the structured JSON
```

## Factory Pattern

Use `LLMAgentFactory` for convenient agent creation:

### From Configuration

```php
<?php

use AgentStateLanguage\Agents\LLM\LLMAgentFactory;

// Create from config array
$agent = LLMAgentFactory::create([
    'name' => 'MyAgent',
    'provider' => 'claude',
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'model' => 'claude-3-haiku-20240307',
    'system_prompt' => 'You are helpful.',
    'temperature' => 0.5,
    'max_tokens' => 1000
]);
```

### Shorthand Methods

```php
// Create Claude agent
$claude = LLMAgentFactory::claude(
    'Analyzer',
    getenv('ANTHROPIC_API_KEY'),
    'claude-sonnet-4-20250514',
    'You analyze code.'
);

// Create OpenAI agent
$openai = LLMAgentFactory::openai(
    'Summarizer',
    getenv('OPENAI_API_KEY'),
    'gpt-4o-mini',
    'You summarize documents.'
);
```

### Create Multiple Agents

```php
$agents = LLMAgentFactory::createMany([
    'analyzer' => [
        'provider' => 'claude',
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'system_prompt' => 'Analyze code for issues.'
    ],
    'reviewer' => [
        'provider' => 'claude',
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'model' => 'claude-3-haiku-20240307',
        'system_prompt' => 'Review code changes.'
    ],
    'summarizer' => [
        'provider' => 'openai',
        'api_key' => getenv('OPENAI_API_KEY'),
        'system_prompt' => 'Summarize findings.'
    ]
]);

// Register all agents
foreach ($agents as $name => $agent) {
    $registry->register($name, $agent);
}
```

## Complete Workflow Example

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\LLM\LLMAgentFactory;
use AgentStateLanguage\Engine\WorkflowEngine;

// Create agents
$registry = new AgentRegistry();

$registry->register('CodeAnalyzer', LLMAgentFactory::claude(
    'CodeAnalyzer',
    getenv('ANTHROPIC_API_KEY'),
    'claude-sonnet-4-20250514',
    'You are a code analyzer. Analyze the provided code and return JSON with:
    - issues: array of found issues
    - score: quality score 0-100
    - suggestions: array of improvement suggestions'
));

$registry->register('SecurityScanner', LLMAgentFactory::claude(
    'SecurityScanner',
    getenv('ANTHROPIC_API_KEY'),
    'claude-3-haiku-20240307',
    'You are a security expert. Scan for vulnerabilities and return JSON with:
    - vulnerabilities: array of security issues
    - severity: overall severity (low/medium/high/critical)
    - recommendations: array of fixes'
));

// Load and run workflow
$engine = WorkflowEngine::fromFile('code-review.asl.json', $registry);

$result = $engine->run([
    'code' => file_get_contents('src/MyClass.php'),
    'language' => 'php'
]);

if ($result->isSuccess()) {
    echo "Analysis complete!\n";
    echo "Tokens used: " . $result->getTokensUsed() . "\n";
    echo "Cost: $" . number_format($result->getCost(), 4) . "\n";
    print_r($result->getOutput());
} else {
    echo "Error: " . $result->getError() . "\n";
}
```

## Token and Cost Tracking

Agents automatically track token usage and costs:

```php
$agent = new ClaudeAgent('Test', $apiKey);
$result = $agent->execute(['prompt' => 'Hello!']);

// Get usage from result
$tokens = $result['_tokens'];  // Total tokens
$cost = $result['_cost'];      // Cost in USD

// Or from agent directly
$usage = $agent->getLastTokenUsage();
// ['input' => 100, 'output' => 50]

$cost = $agent->getLastCost();
// 0.00045
```

The workflow engine automatically accumulates these across all agent calls:

```php
$result = $engine->run($input);

echo "Total tokens: " . $result->getTokensUsed();
echo "Total cost: $" . $result->getCost();
```

## Custom LLM Agents

Create custom agents by extending `AbstractLLMAgent`:

```php
<?php

use AgentStateLanguage\Agents\LLM\AbstractLLMAgent;

class LocalLLMAgent extends AbstractLLMAgent
{
    private string $endpoint;

    public function __construct(string $name, string $endpoint)
    {
        parent::__construct($name, '', 'local-model');
        $this->endpoint = $endpoint;
    }

    public function execute(array $parameters): array
    {
        $message = $this->buildUserMessage($parameters);
        
        $response = $this->httpPost($this->endpoint, [
            'prompt' => $message,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature
        ], [
            'Content-Type' => 'application/json'
        ]);

        return [
            'response' => $response['text'] ?? '',
            '_tokens' => $response['tokens_used'] ?? 0,
            '_cost' => 0.0 // Local models are free
        ];
    }
}
```

## Environment Variables

Recommended setup for API keys:

```bash
# .env file
ANTHROPIC_API_KEY=sk-ant-xxx
OPENAI_API_KEY=sk-xxx
```

```php
// Load with vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$agent = new ClaudeAgent('Agent', $_ENV['ANTHROPIC_API_KEY']);
```
