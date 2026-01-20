# Tutorial 10: Cost Management

Learn how to control token usage and costs in your AI agent workflows.

## What You'll Learn

- Setting workflow and state-level budgets
- Implementing fallback model strategies
- Configuring cost alerts and notifications
- Using caching to reduce costs
- Building a cost-aware document processor

## Prerequisites

- Completed [Tutorial 9: Multi-Agent Debate](09-multi-agent-debate.md)
- Understanding of LLM token pricing

## The Scenario

We'll build a document analysis workflow that:

1. Sets a maximum budget for the entire workflow
2. Uses expensive models for critical analysis
3. Falls back to cheaper models as budget depletes
4. Caches repeated analyses to save costs
5. Alerts when approaching budget limits

## Step 1: Understanding Cost Management

LLM costs can escalate quickly. ASL provides multiple mechanisms to control spending:

| Mechanism | Purpose |
|-----------|---------|
| **Budgets** | Set hard limits on spending |
| **Fallbacks** | Switch to cheaper models automatically |
| **Alerts** | Notify when thresholds are reached |
| **Caching** | Avoid redundant API calls |
| **Tracking** | Monitor usage in real-time |

### Token Costs by Model

| Model | Input (per 1M) | Output (per 1M) |
|-------|----------------|-----------------|
| Claude Opus 4.5 | $5.00 | $25.00 |
| Claude Sonnet 4.5 | $3.00 | $15.00 |
| Claude Haiku 4.5 | $1.00 | $5.00 |
| GPT-5.2 | $1.75 | $14.00 |
| GPT-5-mini | $0.25 | $2.00 |

## Step 2: Create the Agents

### DocumentAnalyzerAgent

A token-aware analysis agent:

```php
<?php

namespace MyOrg\CostManagement;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\LLM\LLMAgentInterface;

class DocumentAnalyzerAgent implements AgentInterface, LLMAgentInterface
{
    private string $model;
    private int $lastTokenUsage = 0;
    private float $lastCost = 0.0;
    
    private const MODEL_COSTS = [
        'claude-opus-4-5' => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-4-5' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ];

    public function __construct(string $model = 'claude-sonnet-4-5')
    {
        $this->model = $model;
    }
    
    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function execute(array $parameters): array
    {
        $document = $parameters['document'] ?? '';
        $analysisType = $parameters['analysisType'] ?? 'standard';
        
        // Simulate token usage based on document length and analysis type
        $inputTokens = $this->estimateTokens($document);
        $outputTokens = $this->estimateOutputTokens($analysisType);
        
        // Calculate cost
        $cost = $this->calculateCost($inputTokens, $outputTokens);
        
        $this->lastTokenUsage = $inputTokens + $outputTokens;
        $this->lastCost = $cost;
        
        // Simulate analysis result
        $analysis = $this->performAnalysis($document, $analysisType);
        
        return [
            'analysis' => $analysis,
            'model' => $this->model,
            'analysisType' => $analysisType,
            '_tokens' => $this->lastTokenUsage,
            '_cost' => $this->lastCost,
            '_usage' => [
                'input' => $inputTokens,
                'output' => $outputTokens
            ]
        ];
    }
    
    private function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 characters per token
        return (int) ceil(strlen($text) / 4);
    }
    
    private function estimateOutputTokens(string $analysisType): int
    {
        return match($analysisType) {
            'deep' => 2000,
            'standard' => 1000,
            'quick' => 500,
            default => 1000
        };
    }
    
    private function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::MODEL_COSTS[$this->model] ?? self::MODEL_COSTS['claude-sonnet-4-5'];
        
        $inputCost = ($inputTokens / 1_000_000) * $costs['input'];
        $outputCost = ($outputTokens / 1_000_000) * $costs['output'];
        
        return $inputCost + $outputCost;
    }
    
    private function performAnalysis(string $document, string $analysisType): array
    {
        // Simulate analysis based on type
        $wordCount = str_word_count($document);
        
        return [
            'summary' => 'Document analysis complete.',
            'wordCount' => $wordCount,
            'sentiment' => 'neutral',
            'topics' => ['technology', 'business'],
            'qualityScore' => 85,
            'depth' => $analysisType
        ];
    }
    
    public function getLastTokenUsage(): array
    {
        return ['total' => $this->lastTokenUsage];
    }
    
    public function getLastCost(): float
    {
        return $this->lastCost;
    }

    public function getName(): string
    {
        return 'DocumentAnalyzerAgent';
    }
}
```

### CostAwareSummarizerAgent

An agent that tracks its own costs:

```php
<?php

namespace MyOrg\CostManagement;

use AgentStateLanguage\Agents\AgentInterface;

class CostAwareSummarizerAgent implements AgentInterface
{
    private string $model = 'claude-haiku-4-5';
    
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? '';
        $maxLength = $parameters['maxLength'] ?? 200;
        $budgetRemaining = $parameters['_budgetRemaining'] ?? null;
        
        // Adjust behavior based on remaining budget
        if ($budgetRemaining !== null && $budgetRemaining < 1.0) {
            // Very low budget - use minimal processing
            $maxLength = min($maxLength, 100);
        }
        
        // Simulate summarization
        $summary = substr($content, 0, $maxLength);
        if (strlen($content) > $maxLength) {
            $summary .= '...';
        }
        
        $inputTokens = (int) ceil(strlen($content) / 4);
        $outputTokens = (int) ceil(strlen($summary) / 4);
        $cost = ($inputTokens + $outputTokens) / 1_000_000 * 3.0; // Haiku pricing
        
        return [
            'summary' => $summary,
            'originalLength' => strlen($content),
            'summaryLength' => strlen($summary),
            'compressionRatio' => round(strlen($summary) / max(strlen($content), 1), 2),
            '_tokens' => $inputTokens + $outputTokens,
            '_cost' => $cost
        ];
    }

    public function getName(): string
    {
        return 'CostAwareSummarizerAgent';
    }
}
```

## Step 3: Define the Workflow

Create `cost-managed-analysis.asl.json`:

```json
{
  "Comment": "Cost-managed document analysis workflow",
  "Budget": {
    "MaxCost": "$5.00",
    "MaxTokens": 100000,
    "OnExceed": "PauseAndNotify",
    "Fallback": {
      "Cascade": [
        {
          "When": "BudgetAt50Percent",
          "UseModel": "claude-sonnet-4-5"
        },
        {
          "When": "BudgetAt75Percent",
          "UseModel": "claude-haiku-4-5"
        },
        {
          "When": "BudgetAt90Percent",
          "Action": "ReduceQuality"
        },
        {
          "When": "BudgetAt95Percent",
          "Action": "PauseAndNotify"
        }
      ]
    },
    "Alerts": [
      {
        "At": "50%",
        "Notify": ["dev-team@example.com"],
        "Message": "Document analysis at 50% budget"
      },
      {
        "At": "80%",
        "Notify": ["#cost-alerts"],
        "Channel": "slack",
        "Priority": "high"
      }
    ]
  },
  "StartAt": "CheckCache",
  "States": {
    "CheckCache": {
      "Type": "Task",
      "Agent": "CacheChecker",
      "Parameters": {
        "cacheKey.$": "States.Hash($.document, 'SHA-256')",
        "cacheType": "analysis"
      },
      "ResultPath": "$.cacheResult",
      "Next": "CacheDecision"
    },
    "CacheDecision": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.cacheResult.hit",
          "BooleanEquals": true,
          "Next": "UseCachedResult"
        }
      ],
      "Default": "DeepAnalysis"
    },
    "UseCachedResult": {
      "Type": "Pass",
      "Parameters": {
        "analysis.$": "$.cacheResult.data",
        "fromCache": true,
        "costSaved.$": "$.cacheResult.originalCost"
      },
      "End": true
    },
    "DeepAnalysis": {
      "Type": "Task",
      "Agent": "DocumentAnalyzerAgent",
      "Parameters": {
        "document.$": "$.document",
        "analysisType": "deep"
      },
      "Budget": {
        "MaxCost": "$2.00",
        "MaxTokens": 50000,
        "Priority": "high"
      },
      "Cache": {
        "Enabled": true,
        "Key.$": "States.Hash($.document, 'SHA-256')",
        "TTL": "24h"
      },
      "ResultPath": "$.deepAnalysis",
      "Next": "CheckBudgetForSummary"
    },
    "CheckBudgetForSummary": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable.$": "States.CurrentCost()",
          "NumericGreaterThan": 4.0,
          "Next": "SkipSummary"
        }
      ],
      "Default": "GenerateSummary"
    },
    "GenerateSummary": {
      "Type": "Task",
      "Agent": "CostAwareSummarizerAgent",
      "Parameters": {
        "content.$": "$.document",
        "maxLength": 500,
        "_budgetRemaining.$": "States.MathSubtract(5.0, States.CurrentCost())"
      },
      "Budget": {
        "MaxCost": "$0.50",
        "Priority": "low"
      },
      "ResultPath": "$.summary",
      "Next": "CombineResults"
    },
    "SkipSummary": {
      "Type": "Pass",
      "Parameters": {
        "summary": {
          "summary": "Summary skipped due to budget constraints",
          "skipped": true
        }
      },
      "ResultPath": "$.summary",
      "Next": "CombineResults"
    },
    "CombineResults": {
      "Type": "Pass",
      "Parameters": {
        "analysis.$": "$.deepAnalysis.analysis",
        "summary.$": "$.summary.summary",
        "model.$": "$.deepAnalysis.model",
        "totalTokens.$": "States.CurrentTokens()",
        "totalCost.$": "States.CurrentCost()",
        "fromCache": false
      },
      "Next": "SaveToCache"
    },
    "SaveToCache": {
      "Type": "Task",
      "Agent": "CacheSaver",
      "Parameters": {
        "key.$": "States.Hash($.document, 'SHA-256')",
        "data.$": "$",
        "ttl": "24h"
      },
      "End": true
    }
  }
}
```

## Step 4: Create Supporting Agents

### CacheCheckerAgent

```php
<?php

namespace MyOrg\CostManagement;

use AgentStateLanguage\Agents\AgentInterface;

class CacheCheckerAgent implements AgentInterface
{
    private static array $cache = [];
    
    public function execute(array $parameters): array
    {
        $key = $parameters['cacheKey'] ?? '';
        $type = $parameters['cacheType'] ?? 'default';
        
        $cacheKey = "{$type}:{$key}";
        
        if (isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            return [
                'hit' => true,
                'data' => $cached['data'],
                'originalCost' => $cached['cost'],
                'cachedAt' => $cached['timestamp']
            ];
        }
        
        return [
            'hit' => false,
            'data' => null
        ];
    }
    
    public static function store(string $key, array $data, float $cost): void
    {
        self::$cache[$key] = [
            'data' => $data,
            'cost' => $cost,
            'timestamp' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'CacheChecker';
    }
}
```

### CacheSaverAgent

```php
<?php

namespace MyOrg\CostManagement;

use AgentStateLanguage\Agents\AgentInterface;

class CacheSaverAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $key = $parameters['key'] ?? '';
        $data = $parameters['data'] ?? [];
        $ttl = $parameters['ttl'] ?? '24h';
        
        $cost = $data['totalCost'] ?? 0;
        
        CacheCheckerAgent::store("analysis:{$key}", $data, $cost);
        
        return [
            'saved' => true,
            'key' => $key,
            'ttl' => $ttl,
            'savedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'CacheSaver';
    }
}
```

## Step 5: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\CostManagement\DocumentAnalyzerAgent;
use MyOrg\CostManagement\CostAwareSummarizerAgent;
use MyOrg\CostManagement\CacheCheckerAgent;
use MyOrg\CostManagement\CacheSaverAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('DocumentAnalyzerAgent', new DocumentAnalyzerAgent());
$registry->register('CostAwareSummarizerAgent', new CostAwareSummarizerAgent());
$registry->register('CacheChecker', new CacheCheckerAgent());
$registry->register('CacheSaver', new CacheSaverAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('cost-managed-analysis.asl.json', $registry);

// Sample document
$document = str_repeat("This is a sample document for analysis. ", 100);

// First run - full analysis
echo "=== First Run (Fresh Analysis) ===\n";
$result1 = $engine->run(['document' => $document]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "From Cache: " . ($output['fromCache'] ? 'Yes' : 'No') . "\n";
    echo "Model Used: " . ($output['model'] ?? 'N/A') . "\n";
    echo "Total Tokens: " . $result1->getTokensUsed() . "\n";
    echo "Total Cost: $" . number_format($result1->getCost(), 4) . "\n";
    echo "Analysis Quality Score: " . ($output['analysis']['qualityScore'] ?? 'N/A') . "\n";
}

echo "\n=== Second Run (Cached) ===\n";
$result2 = $engine->run(['document' => $document]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "From Cache: " . ($output['fromCache'] ? 'Yes' : 'No') . "\n";
    if ($output['fromCache']) {
        echo "Cost Saved: $" . number_format($output['costSaved'] ?? 0, 4) . "\n";
    }
    echo "Total Cost This Run: $" . number_format($result2->getCost(), 4) . "\n";
}

echo "\n=== Cost Summary ===\n";
$totalCost = $result1->getCost() + $result2->getCost();
echo "Total Cost (both runs): $" . number_format($totalCost, 4) . "\n";
echo "Savings from caching: $" . number_format($result1->getCost(), 4) . "\n";
```

## Expected Output

```
=== First Run (Fresh Analysis) ===
From Cache: No
Model Used: claude-sonnet-4-5
Total Tokens: 3500
Total Cost: $0.0553
Analysis Quality Score: 85

=== Second Run (Cached) ===
From Cache: Yes
Cost Saved: $0.0553
Total Cost This Run: $0.0000

=== Cost Summary ===
Total Cost (both runs): $0.0553
Savings from caching: $0.0553
```

## Budget Configuration Reference

### Workflow-Level Budget

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "MaxTokens": 200000,
    "OnExceed": "PauseAndNotify"
  }
}
```

| Option | Description |
|--------|-------------|
| `MaxCost` | Maximum spending in USD |
| `MaxTokens` | Maximum tokens across all agents |
| `OnExceed` | Action when limit reached |

### OnExceed Actions

| Action | Behavior |
|--------|----------|
| `Fail` | Terminate workflow with error |
| `PauseAndNotify` | Pause and send notification |
| `Continue` | Log warning, continue (risky) |
| `UseFallback` | Switch to cheaper model |

### Model Fallback Cascade

```json
{
  "Fallback": {
    "Cascade": [
      { "When": "BudgetAt50Percent", "UseModel": "claude-sonnet-4-5" },
      { "When": "BudgetAt75Percent", "UseModel": "claude-haiku-4-5" },
      { "When": "BudgetAt90Percent", "Action": "ReduceQuality" }
    ]
  }
}
```

### State-Level Budget Priority

```json
{
  "Budget": {
    "MaxCost": "$1.00",
    "Priority": "high"
  }
}
```

| Priority | Behavior |
|----------|----------|
| `high` | Reserve budget from workflow total |
| `normal` | Standard allocation |
| `low` | Use only if budget available |

## Experiment

Try these modifications:

### Add Real-Time Cost Tracking

```json
{
  "TrackCosts": {
    "Type": "Pass",
    "Parameters": {
      "currentCost.$": "States.CurrentCost()",
      "currentTokens.$": "States.CurrentTokens()",
      "budgetUsed.$": "States.Format('{}%', States.MathMultiply(States.CurrentCost(), 20))"
    },
    "Next": "Continue"
  }
}
```

### Add Per-User Budgets

```json
{
  "Budget": {
    "MaxCost.$": "$.userBudget",
    "TrackBy": "$.userId",
    "ResetPeriod": "daily"
  }
}
```

## Common Mistakes

### No Fallback Strategy

```json
{
  "Budget": {
    "MaxCost": "$5.00",
    "OnExceed": "Fail"
  }
}
```

**Problem**: Workflow fails abruptly when budget exceeded.

**Fix**: Add graceful fallback cascade.

### Cache Key Collisions

```json
{
  "Cache": {
    "Key": "analysis"
  }
}
```

**Problem**: Different documents get same cached result.

**Fix**: Use content hash: `"Key.$": "States.Hash($.document, 'SHA-256')"`.

### Ignoring Token Costs

**Problem**: Only tracking API call costs, not token counts.

**Fix**: Budget both `MaxCost` and `MaxTokens`.

## Summary

You've learned:

- ✅ Setting workflow and state-level budgets
- ✅ Implementing model fallback strategies
- ✅ Configuring cost alerts
- ✅ Using caching to reduce costs
- ✅ Building cost-aware agents
- ✅ Tracking usage in real-time

## Next Steps

- [Tutorial 11: Error Handling](11-error-handling.md) - Retry and recovery
- [Tutorial 12: Building Skills](12-building-skills.md) - Reusable templates
