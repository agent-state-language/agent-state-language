# Tutorial 10: Cost Management

Learn how to control token usage and costs in your workflows.

## What You'll Learn

- Workflow and state budgets
- Fallback strategies
- Cost alerts
- Token tracking

## Workflow Budget

```json
{
  "Comment": "Cost-controlled workflow",
  "Budget": {
    "MaxCost": "$5.00",
    "MaxTokens": 100000,
    "OnExceed": "PauseAndNotify"
  },
  "StartAt": "Process",
  "States": { ... }
}
```

## State-Level Budget

```json
{
  "ExpensiveAnalysis": {
    "Type": "Task",
    "Agent": "DeepAnalyzer",
    "Budget": {
      "MaxTokens": 10000,
      "MaxCost": "$1.00",
      "Priority": "high"
    }
  }
}
```

## Fallback Strategies

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "Fallback": {
      "Cascade": [
        { "When": "BudgetAt60Percent", "UseModel": "claude-3-sonnet" },
        { "When": "BudgetAt85Percent", "UseModel": "claude-3-haiku" },
        { "When": "BudgetAt95Percent", "Action": "PauseAndNotify" }
      ]
    }
  }
}
```

## Cost Alerts

```json
{
  "Budget": {
    "MaxCost": "$20.00",
    "Alerts": [
      { "At": "50%", "Notify": ["team@example.com"] },
      { "At": "80%", "Notify": ["#alerts"], "Channel": "slack" }
    ]
  }
}
```

## Caching

```json
{
  "CachedOperation": {
    "Type": "Task",
    "Agent": "ExpensiveAgent",
    "Cache": {
      "Enabled": true,
      "Key.$": "States.Hash($.input, 'SHA-256')",
      "TTL": "24h"
    }
  }
}
```

## Summary

You've learned:

- ✅ Setting budget limits
- ✅ Fallback model strategies
- ✅ Cost alerts and notifications
- ✅ Caching for cost reduction
