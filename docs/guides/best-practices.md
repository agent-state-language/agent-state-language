# Best Practices

This guide covers best practices for designing, building, and maintaining ASL workflows.

## Workflow Design

### Keep States Focused

Each state should do one thing well:

```json
// ✅ Good - focused states
{
  "ParseInput": { "Type": "Task", "Agent": "Parser", "Next": "Validate" },
  "Validate": { "Type": "Task", "Agent": "Validator", "Next": "Process" },
  "Process": { "Type": "Task", "Agent": "Processor", "End": true }
}

// ❌ Bad - monolithic state
{
  "DoEverything": { "Type": "Task", "Agent": "MonolithAgent", "End": true }
}
```

### Use Descriptive State Names

```json
// ✅ Good
"ValidateUserInput", "SendConfirmationEmail", "ProcessPayment"

// ❌ Bad  
"Step1", "DoStuff", "Handler"
```

### Add Comments

```json
{
  "Comment": "Customer onboarding workflow v2.1",
  "States": {
    "ValidateEmail": {
      "Comment": "Check email format and domain validity",
      "Type": "Task"
    }
  }
}
```

## Error Handling

### Always Use Retry for External Services

```json
{
  "CallExternalAPI": {
    "Type": "Task",
    "Agent": "APIClient",
    "Retry": [
      {
        "ErrorEquals": ["States.Timeout", "States.RateLimitExceeded"],
        "MaxAttempts": 3,
        "BackoffRate": 2.0
      }
    ]
  }
}
```

### Catch All at Workflow Level

```json
{
  "RiskyOperation": {
    "Type": "Task",
    "Catch": [
      { "ErrorEquals": ["SpecificError"], "Next": "HandleSpecific" },
      { "ErrorEquals": ["States.ALL"], "Next": "GlobalErrorHandler" }
    ]
  }
}
```

### Set Appropriate Timeouts

```json
{
  "QuickTask": { "TimeoutSeconds": 30 },
  "MediumTask": { "TimeoutSeconds": 120 },
  "LongTask": { "TimeoutSeconds": 600, "HeartbeatSeconds": 60 }
}
```

## Security

### Use Allowlists for Tools

```json
// ✅ Explicit allowlist
{
  "Tools": {
    "Allowed": ["read_file", "web_search"]
  }
}

// ❌ Avoid open access
{
  "Tools": {
    "Denied": ["dangerous_tool"]
  }
}
```

### Sandbox Untrusted Operations

```json
{
  "Tools": {
    "Allowed": ["execute_code"],
    "Sandboxed": true
  }
}
```

### Restrict File Access

```json
{
  "Tools": {
    "FileSystem": {
      "AllowedPaths": ["./data/**"],
      "DeniedPaths": ["./.env", "./secrets/**"]
    }
  }
}
```

## Cost Management

### Set Workflow Budgets

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "MaxTokens": 100000
  }
}
```

### Use Fallback Models

```json
{
  "Budget": {
    "Fallback": {
      "When": "BudgetAt80Percent",
      "UseModel": "cheaper-model"
    }
  }
}
```

### Cache Expensive Operations

```json
{
  "Cache": {
    "Enabled": true,
    "Key.$": "States.Hash($.input, 'SHA-256')",
    "TTL": "24h"
  }
}
```

## Testing

### Test with Mock Agents

Create mock agents that return predictable outputs for testing.

### Test Error Paths

Ensure your error handling works by simulating failures.

### Validate Before Deployment

```php
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$engine->validate(); // Throws on validation errors
```

## Performance

### Use Parallel When Possible

```json
{
  "Type": "Parallel",
  "Branches": [
    { "StartAt": "Task1", "States": {...} },
    { "StartAt": "Task2", "States": {...} }
  ]
}
```

### Limit Map Concurrency

```json
{
  "Type": "Map",
  "MaxConcurrency": 5
}
```

### Minimize Data Passing

Only pass necessary data between states:

```json
{
  "Parameters": {
    "needed.$": "$.specificField"
  }
}
```

## Maintenance

### Version Your Workflows

```json
{
  "Comment": "My Workflow v1.2.3",
  "Version": "1.2.3"
}
```

### Use Templates for Reusability

```json
{
  "Imports": {
    "common": "./templates/common.asl.json"
  }
}
```

### Document Custom Agents

Create README files explaining each agent's purpose and parameters.
