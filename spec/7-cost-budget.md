# 7. Cost and Budget

This section covers how ASL manages token usage and cost constraints for agent workflows.

## Overview

LLM operations have real costs. ASL provides:

- **Budget limits** - Maximum spend per workflow or state
- **Token tracking** - Monitor token consumption
- **Cost awareness** - Real-time cost visibility
- **Fallback strategies** - Switch to cheaper options when needed

## Workflow-Level Budget

Set a budget for the entire workflow:

```json
{
  "Comment": "Workflow with budget controls",
  "Budget": {
    "MaxCost": "$5.00",
    "MaxTokens": 100000
  },
  "StartAt": "Begin",
  "States": { ... }
}
```

### Budget Fields

| Field | Type | Description |
|-------|------|-------------|
| `MaxCost` | string | Maximum cost (e.g., "$5.00") |
| `MaxTokens` | integer | Maximum total tokens |
| `OnExceed` | string | Action when exceeded |
| `Fallback` | object | Fallback configuration |
| `Alerts` | array | Alert thresholds |
| `Currency` | string | Cost currency (default: USD) |

### OnExceed Actions

| Action | Description |
|--------|-------------|
| `Fail` | Terminate with error |
| `PauseAndNotify` | Pause and wait for approval |
| `UseFallback` | Switch to fallback agent |
| `Continue` | Log warning but continue |
| `Throttle` | Slow down execution |

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "OnExceed": "PauseAndNotify",
    "Notify": ["team@example.com"]
  }
}
```

## State-Level Budget

Control costs for individual states:

```json
{
  "ExpensiveAnalysis": {
    "Type": "Task",
    "Agent": "DeepAnalyzer",
    "Budget": {
      "MaxTokens": 10000,
      "MaxCost": "$1.00",
      "Priority": "high"
    },
    "Next": "Continue"
  }
}
```

### Priority Levels

Priority affects budget allocation:

| Priority | Description |
|----------|-------------|
| `critical` | Never throttle, prefer expensive models |
| `high` | Prefer quality over cost |
| `normal` | Balance cost and quality |
| `low` | Prefer cost savings |
| `background` | Use cheapest options |

```json
{
  "Budget": {
    "MaxTokens": 5000,
    "Priority": "low"
  }
}
```

## Token Tracking

### Input/Output Limits

```json
{
  "GenerateResponse": {
    "Type": "Task",
    "Agent": "Generator",
    "Budget": {
      "MaxInputTokens": 8000,
      "MaxOutputTokens": 2000
    },
    "Next": "Process"
  }
}
```

### Token Estimation

Check tokens before execution:

```json
{
  "CheckTokens": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.TokenCount($.content)",
        "NumericGreaterThan": 10000,
        "Next": "SummarizeFirst"
      }
    ],
    "Default": "ProcessDirectly"
  }
}
```

## Model Fallbacks

Switch to cheaper models when budget is constrained:

```json
{
  "Budget": {
    "MaxCost": "$5.00",
    "Fallback": {
      "When": "BudgetAt80Percent",
      "UseModel": "claude-3-haiku",
      "Notify": true
    }
  }
}
```

### Fallback Triggers

| Trigger | Description |
|---------|-------------|
| `BudgetAt50Percent` | 50% of budget used |
| `BudgetAt80Percent` | 80% of budget used |
| `BudgetAt90Percent` | 90% of budget used |
| `TokensAt50Percent` | 50% of tokens used |
| `Always` | Always use fallback |

### Multiple Fallbacks

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "Fallback": {
      "Cascade": [
        {
          "When": "BudgetAt60Percent",
          "UseModel": "claude-3-sonnet"
        },
        {
          "When": "BudgetAt85Percent",
          "UseModel": "claude-3-haiku"
        },
        {
          "When": "BudgetAt95Percent",
          "Action": "PauseAndNotify"
        }
      ]
    }
  }
}
```

## Alerts

Configure alerts at various thresholds:

```json
{
  "Budget": {
    "MaxCost": "$20.00",
    "Alerts": [
      {
        "At": "50%",
        "Notify": ["team@example.com"],
        "Channel": "email"
      },
      {
        "At": "80%",
        "Notify": ["#alerts"],
        "Channel": "slack"
      },
      {
        "At": "95%",
        "Notify": ["+1234567890"],
        "Channel": "sms"
      }
    ]
  }
}
```

## Cost Attribution

Track costs by category:

```json
{
  "ExpensiveTask": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Budget": {
      "Category": "analysis",
      "Tags": ["code-review", "security"],
      "Attribution": {
        "Team": "engineering",
        "Project.$": "$.projectId"
      }
    },
    "Next": "Continue"
  }
}
```

### Cost Report

After execution, cost breakdown is available:

```json
{
  "costs": {
    "total": 4.52,
    "byState": {
      "AnalyzeCode": 2.10,
      "GenerateReport": 1.50,
      "Summarize": 0.92
    },
    "byCategory": {
      "analysis": 2.10,
      "generation": 2.42
    },
    "byModel": {
      "claude-3-opus": 3.60,
      "claude-3-sonnet": 0.92
    }
  }
}
```

## Rate-Based Budgeting

Budget over time periods:

```json
{
  "Budget": {
    "MaxCostPerHour": "$10.00",
    "MaxCostPerDay": "$100.00",
    "OnExceed": "Throttle",
    "ThrottleDelay": "30s"
  }
}
```

## Shared Budgets

Multiple workflows sharing a budget pool:

```json
{
  "Budget": {
    "Pool": "team-monthly-budget",
    "MaxFromPool": "$50.00",
    "ReleaseOnComplete": true
  }
}
```

## Conditional Processing

Route based on remaining budget:

```json
{
  "CheckBudget": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.CurrentCost()",
        "NumericGreaterThan": 8.0,
        "Next": "QuickSummary"
      },
      {
        "Variable.$": "States.RemainingBudget()",
        "NumericLessThan": 2.0,
        "Next": "EconomyMode"
      }
    ],
    "Default": "FullAnalysis"
  }
}
```

## Cost Estimation

Estimate costs before execution:

```json
{
  "EstimateCost": {
    "Type": "Pass",
    "Parameters": {
      "estimatedTokens.$": "States.TokenCount($.input)",
      "estimatedCost.$": "States.EstimateCost($.input, 'claude-3-opus')"
    },
    "Next": "CheckEstimate"
  },
  "CheckEstimate": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable": "$.estimatedCost",
        "NumericGreaterThan": 5.0,
        "Next": "RequireApproval"
      }
    ],
    "Default": "Proceed"
  }
}
```

## Caching for Cost Reduction

Cache expensive operations:

```json
{
  "ExpensiveComputation": {
    "Type": "Task",
    "Agent": "HeavyAnalyzer",
    "Cache": {
      "Enabled": true,
      "Key.$": "States.Hash($.input, 'SHA-256')",
      "TTL": "24h"
    },
    "Next": "UseResult"
  }
}
```

### Cache Configuration

| Field | Type | Description |
|-------|------|-------------|
| `Enabled` | boolean | Enable caching |
| `Key` | string | Cache key |
| `Key.$` | string | Dynamic cache key |
| `TTL` | string | Cache duration |
| `Scope` | string | Cache scope |

## Complete Example

```json
{
  "Comment": "Cost-optimized research workflow",
  "Budget": {
    "MaxCost": "$15.00",
    "MaxTokens": 200000,
    "OnExceed": "UseFallback",
    "Fallback": {
      "Cascade": [
        { "When": "BudgetAt70Percent", "UseModel": "claude-3-sonnet" },
        { "When": "BudgetAt90Percent", "UseModel": "claude-3-haiku" }
      ]
    },
    "Alerts": [
      { "At": "50%", "Notify": ["team@example.com"] },
      { "At": "90%", "Notify": ["manager@example.com"] }
    ]
  },
  "StartAt": "CheckInput",
  "States": {
    "CheckInput": {
      "Type": "Pass",
      "Parameters": {
        "inputTokens.$": "States.TokenCount($.query)",
        "estimatedCost.$": "States.EstimateCost($.query, 'claude-3-opus')"
      },
      "Next": "RouteByComplexity"
    },
    "RouteByComplexity": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.estimatedCost",
          "NumericGreaterThan": 5.0,
          "Next": "ExpensiveResearch"
        }
      ],
      "Default": "StandardResearch"
    },
    "ExpensiveResearch": {
      "Type": "Task",
      "Agent": "DeepResearcher",
      "Budget": {
        "MaxCost": "$8.00",
        "Priority": "high"
      },
      "Cache": {
        "Enabled": true,
        "Key.$": "States.Hash($.query, 'SHA-256')",
        "TTL": "7d"
      },
      "Next": "Synthesize"
    },
    "StandardResearch": {
      "Type": "Task",
      "Agent": "QuickResearcher",
      "Budget": {
        "MaxCost": "$2.00",
        "Priority": "normal"
      },
      "Next": "Synthesize"
    },
    "Synthesize": {
      "Type": "Task",
      "Agent": "Synthesizer",
      "Budget": {
        "MaxCost": "$3.00"
      },
      "End": true
    }
  }
}
```

## Best Practices

### 1. Set Workflow-Level Budgets

```json
{
  "Budget": {
    "MaxCost": "$10.00"
  }
}
```

### 2. Use Fallback Cascades

```json
{
  "Fallback": {
    "Cascade": [
      { "When": "BudgetAt60Percent", "UseModel": "sonnet" },
      { "When": "BudgetAt85Percent", "UseModel": "haiku" }
    ]
  }
}
```

### 3. Cache Expensive Operations

```json
{
  "Cache": {
    "Enabled": true,
    "TTL": "24h"
  }
}
```

### 4. Set Up Alerts

```json
{
  "Alerts": [
    { "At": "50%", "Notify": ["team@example.com"] }
  ]
}
```

### 5. Estimate Before Expensive Operations

```json
{
  "Parameters": {
    "estimate.$": "States.EstimateCost($.input, 'model')"
  }
}
```

### 6. Prioritize Critical Operations

```json
{
  "Budget": {
    "Priority": "critical"
  }
}
```
