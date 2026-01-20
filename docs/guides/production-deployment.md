# Production Deployment

This guide covers deploying ASL workflows in production environments.

## Pre-Deployment Checklist

- [ ] All workflows validated
- [ ] Agents tested with production configurations
- [ ] Error handling configured
- [ ] Budgets and rate limits set
- [ ] Monitoring and alerting configured
- [ ] Logging enabled

## Validation

Always validate before deployment:

```php
<?php

$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

try {
    $engine->validate();
    echo "Workflow is valid\n";
} catch (ValidationException $e) {
    echo "Validation failed:\n";
    foreach ($e->getErrors() as $error) {
        echo "- $error\n";
    }
    exit(1);
}
```

## Configuration Management

### Environment-Specific Workflows

```php
$environment = getenv('APP_ENV') ?: 'development';
$workflowFile = "workflows/{$environment}/main.asl.json";

$engine = WorkflowEngine::fromFile($workflowFile, $registry);
```

### External Configuration

```json
{
  "Budget": {
    "MaxCost.$": "env.MAX_WORKFLOW_COST",
    "MaxTokens.$": "env.MAX_WORKFLOW_TOKENS"
  }
}
```

## Monitoring

### Execution Metrics

```php
$result = $engine->run($input);

// Log metrics
$metrics->record('workflow.duration', $result->getDuration());
$metrics->record('workflow.tokens', $result->getTokensUsed());
$metrics->record('workflow.cost', $result->getCost());
$metrics->record('workflow.success', $result->isSuccess() ? 1 : 0);
```

### Execution Traces

```php
if (!$result->isSuccess()) {
    $logger->error('Workflow failed', [
        'error' => $result->getError(),
        'cause' => $result->getErrorCause(),
        'trace' => $result->getTrace()
    ]);
}
```

## Error Handling

### Global Error Handler

```json
{
  "States": {
    "MainProcess": {
      "Type": "Task",
      "Agent": "MainAgent",
      "Catch": [
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.error",
          "Next": "ErrorNotification"
        }
      ]
    },
    "ErrorNotification": {
      "Type": "Task",
      "Agent": "ErrorNotifier",
      "End": true
    }
  }
}
```

### Graceful Degradation

```json
{
  "Budget": {
    "Fallback": {
      "Cascade": [
        { "When": "BudgetAt80Percent", "UseModel": "cheaper-model" }
      ]
    }
  }
}
```

## Scaling

### Horizontal Scaling

Run multiple workflow engine instances behind a load balancer.

### Rate Limiting

```json
{
  "Tools": {
    "RateLimits": {
      "web_search": { "MaxPerMinute": 30 },
      "api_call": { "MaxPerMinute": 100 }
    }
  }
}
```

### Concurrency Control

```json
{
  "Type": "Map",
  "MaxConcurrency": 5
}
```

## Security Checklist

- [ ] Tool allowlists configured
- [ ] File system restrictions in place
- [ ] Network access limited
- [ ] Sensitive data redacted from logs
- [ ] API keys stored securely
- [ ] Sandboxing enabled for code execution

## Backup and Recovery

### Checkpoints

```json
{
  "Type": "Checkpoint",
  "Name": "after-expensive-operation",
  "TTL": "7d"
}
```

### State Persistence

Configure memory backend for production:

```json
{
  "Memory": {
    "Backend": "redis",
    "Connection": "redis://prod-redis:6379"
  }
}
```

## Health Checks

```php
// Workflow health check endpoint
function healthCheck(): array
{
    $registry = new AgentRegistry();
    // Register minimal test agents

    try {
        $engine = WorkflowEngine::fromFile('health-check.asl.json', $registry);
        $engine->validate();
        
        $result = $engine->run(['test' => true]);
        
        return [
            'status' => 'healthy',
            'latency' => $result->getDuration()
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
}
```
