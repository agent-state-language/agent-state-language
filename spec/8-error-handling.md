# 8. Error Handling

This section covers how ASL handles errors, retries, and failure recovery.

## Overview

Robust workflows need comprehensive error handling:

- **Retry** - Automatically retry transient failures
- **Catch** - Handle specific errors gracefully
- **Fallback** - Alternative paths when operations fail
- **Timeouts** - Prevent infinite waiting

## Error Types

### Predefined Errors

| Error | Description |
|-------|-------------|
| `States.ALL` | Matches any error |
| `States.Timeout` | Execution timed out |
| `States.TaskFailed` | Task execution failed |
| `States.Permissions` | Permission denied |
| `States.BudgetExceeded` | Cost/token budget exceeded |
| `States.RateLimitExceeded` | Rate limit hit |
| `States.ResultPathMatchFailure` | ResultPath couldn't be applied |
| `States.ParameterPathFailure` | Parameter path resolution failed |
| `States.IntrinsicFailure` | Intrinsic function failed |

### Agent-Specific Errors

| Error | Description |
|-------|-------------|
| `Agent.InvalidInput` | Input validation failed |
| `Agent.InvalidOutput` | Output validation failed |
| `Agent.ContextOverflow` | Context too large |
| `Agent.ModelError` | LLM returned error |
| `Agent.ToolError` | Tool execution failed |
| `Agent.GuardrailViolation` | Content blocked by guardrails |

### Custom Errors

Agents can throw custom errors:

```php
throw new AgentException('CustomError', 'Description of what went wrong');
```

## Retry

The Retry field configures automatic retry behavior.

### Basic Retry

```json
{
  "CallAPI": {
    "Type": "Task",
    "Agent": "APIAgent",
    "Retry": [
      {
        "ErrorEquals": ["States.Timeout", "States.TaskFailed"],
        "MaxAttempts": 3
      }
    ],
    "Next": "Process"
  }
}
```

### Retry Fields

| Field | Type | Description |
|-------|------|-------------|
| `ErrorEquals` | array | Errors to match |
| `IntervalSeconds` | integer | Initial wait between retries |
| `MaxAttempts` | integer | Maximum retry attempts |
| `BackoffRate` | number | Multiplier for interval |
| `MaxDelaySeconds` | integer | Maximum delay between retries |
| `JitterStrategy` | string | Randomization strategy |

### Exponential Backoff

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.RateLimitExceeded"],
      "IntervalSeconds": 5,
      "MaxAttempts": 5,
      "BackoffRate": 2.0,
      "MaxDelaySeconds": 60
    }
  ]
}
```

This produces delays: 5s → 10s → 20s → 40s → 60s (capped)

### Jitter Strategies

| Strategy | Description |
|----------|-------------|
| `NONE` | No jitter |
| `FULL` | Random between 0 and computed delay |
| `DECORRELATED` | Decorrelated jitter |

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.RateLimitExceeded"],
      "IntervalSeconds": 1,
      "MaxAttempts": 10,
      "BackoffRate": 2.0,
      "JitterStrategy": "FULL"
    }
  ]
}
```

### Multiple Retry Policies

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.RateLimitExceeded"],
      "IntervalSeconds": 30,
      "MaxAttempts": 5,
      "BackoffRate": 1.5
    },
    {
      "ErrorEquals": ["States.Timeout"],
      "IntervalSeconds": 5,
      "MaxAttempts": 3
    },
    {
      "ErrorEquals": ["Agent.ModelError"],
      "IntervalSeconds": 2,
      "MaxAttempts": 2
    }
  ]
}
```

### Retry All

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.ALL"],
      "MaxAttempts": 3
    }
  ]
}
```

## Catch

The Catch field defines error handlers.

### Basic Catch

```json
{
  "RiskyOperation": {
    "Type": "Task",
    "Agent": "RiskyAgent",
    "Catch": [
      {
        "ErrorEquals": ["States.TaskFailed"],
        "ResultPath": "$.error",
        "Next": "HandleError"
      }
    ],
    "Next": "Success"
  }
}
```

### Catch Fields

| Field | Type | Description |
|-------|------|-------------|
| `ErrorEquals` | array | Errors to match |
| `ResultPath` | string | Where to store error info |
| `Next` | string | State to transition to |

### Error Object

When caught, the error information is stored:

```json
{
  "error": {
    "Error": "States.Timeout",
    "Cause": "Task execution exceeded 60 seconds"
  }
}
```

### Multiple Catch Handlers

```json
{
  "Catch": [
    {
      "ErrorEquals": ["ValidationError"],
      "ResultPath": "$.validationError",
      "Next": "HandleValidation"
    },
    {
      "ErrorEquals": ["Agent.GuardrailViolation"],
      "ResultPath": "$.guardrailError",
      "Next": "HandleGuardrail"
    },
    {
      "ErrorEquals": ["States.ALL"],
      "ResultPath": "$.error",
      "Next": "CatchAll"
    }
  ]
}
```

### Catch in Parallel States

```json
{
  "ParallelProcessing": {
    "Type": "Parallel",
    "Branches": [...],
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.parallelError",
        "Next": "HandleParallelFailure"
      }
    ],
    "Next": "Continue"
  }
}
```

## Combine Retry and Catch

Retry first, then catch if all retries fail:

```json
{
  "CallExternalService": {
    "Type": "Task",
    "Agent": "ExternalAPIAgent",
    "Retry": [
      {
        "ErrorEquals": ["States.Timeout", "States.RateLimitExceeded"],
        "IntervalSeconds": 10,
        "MaxAttempts": 3,
        "BackoffRate": 2.0
      }
    ],
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.serviceError",
        "Next": "UseFallback"
      }
    ],
    "Next": "ProcessResponse"
  }
}
```

## Timeouts

### State Timeout

```json
{
  "LongRunningTask": {
    "Type": "Task",
    "Agent": "HeavyProcessor",
    "TimeoutSeconds": 300,
    "Next": "Continue"
  }
}
```

### Heartbeat

For long-running tasks, require periodic heartbeats:

```json
{
  "VeryLongTask": {
    "Type": "Task",
    "Agent": "BatchProcessor",
    "TimeoutSeconds": 3600,
    "HeartbeatSeconds": 60,
    "Next": "Continue"
  }
}
```

If no heartbeat within 60 seconds, the task is considered failed.

## Fallback Patterns

### Simple Fallback

```json
{
  "PrimaryOperation": {
    "Type": "Task",
    "Agent": "PrimaryAgent",
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "Next": "FallbackOperation"
      }
    ],
    "Next": "Continue"
  },
  "FallbackOperation": {
    "Type": "Task",
    "Agent": "FallbackAgent",
    "Next": "Continue"
  }
}
```

### Fallback with Recovery

```json
{
  "TryPrimary": {
    "Type": "Task",
    "Agent": "PrimaryAgent",
    "ResultPath": "$.result",
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.primaryError",
        "Next": "TrySecondary"
      }
    ],
    "Next": "UseResult"
  },
  "TrySecondary": {
    "Type": "Task",
    "Agent": "SecondaryAgent",
    "ResultPath": "$.result",
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.secondaryError",
        "Next": "UseDefault"
      }
    ],
    "Next": "UseResult"
  },
  "UseDefault": {
    "Type": "Pass",
    "Result": { "default": true },
    "ResultPath": "$.result",
    "Next": "UseResult"
  }
}
```

## Graceful Degradation

```json
{
  "EnhancedAnalysis": {
    "Type": "Task",
    "Agent": "AdvancedAnalyzer",
    "Catch": [
      {
        "ErrorEquals": ["States.BudgetExceeded", "States.Timeout"],
        "Next": "BasicAnalysis"
      }
    ],
    "Next": "Complete"
  },
  "BasicAnalysis": {
    "Type": "Task",
    "Agent": "SimpleAnalyzer",
    "Next": "Complete"
  },
  "Complete": {
    "Type": "Succeed"
  }
}
```

## Circuit Breaker Pattern

```json
{
  "CheckCircuit": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable": "$.circuitBreaker.failures",
        "NumericGreaterThanEquals": 5,
        "Next": "CircuitOpen"
      }
    ],
    "Default": "TryOperation"
  },
  "TryOperation": {
    "Type": "Task",
    "Agent": "UnreliableService",
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "Next": "IncrementFailures"
      }
    ],
    "Next": "ResetFailures"
  },
  "IncrementFailures": {
    "Type": "Pass",
    "Parameters": {
      "circuitBreaker": {
        "failures.$": "States.MathAdd($.circuitBreaker.failures, 1)",
        "lastFailure.$": "$$.State.EnteredTime"
      }
    },
    "Next": "UseFallback"
  },
  "ResetFailures": {
    "Type": "Pass",
    "Parameters": {
      "circuitBreaker": {
        "failures": 0
      }
    },
    "Next": "Continue"
  },
  "CircuitOpen": {
    "Type": "Choice",
    "Comment": "Check if enough time has passed to retry",
    "Choices": [
      {
        "Variable": "$.circuitBreaker.lastFailure",
        "TimestampLessThanEquals": "-5m",
        "Next": "TryOperation"
      }
    ],
    "Default": "UseFallback"
  }
}
```

## Complete Example

```json
{
  "Comment": "Robust workflow with comprehensive error handling",
  "StartAt": "ValidateInput",
  "States": {
    "ValidateInput": {
      "Type": "Task",
      "Agent": "Validator",
      "Catch": [
        {
          "ErrorEquals": ["ValidationError"],
          "ResultPath": "$.validationError",
          "Next": "InvalidInput"
        }
      ],
      "Next": "ProcessData"
    },
    "ProcessData": {
      "Type": "Task",
      "Agent": "Processor",
      "TimeoutSeconds": 120,
      "HeartbeatSeconds": 30,
      "Retry": [
        {
          "ErrorEquals": ["States.RateLimitExceeded"],
          "IntervalSeconds": 30,
          "MaxAttempts": 5,
          "BackoffRate": 2.0
        },
        {
          "ErrorEquals": ["States.Timeout"],
          "IntervalSeconds": 5,
          "MaxAttempts": 2
        }
      ],
      "Catch": [
        {
          "ErrorEquals": ["Agent.ModelError"],
          "ResultPath": "$.modelError",
          "Next": "TryAlternativeModel"
        },
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.processingError",
          "Next": "HandleProcessingError"
        }
      ],
      "Next": "SaveResults"
    },
    "TryAlternativeModel": {
      "Type": "Task",
      "Agent": "AlternativeProcessor",
      "Retry": [
        {
          "ErrorEquals": ["States.ALL"],
          "MaxAttempts": 2
        }
      ],
      "Catch": [
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.alternativeError",
          "Next": "HandleProcessingError"
        }
      ],
      "Next": "SaveResults"
    },
    "HandleProcessingError": {
      "Type": "Task",
      "Agent": "ErrorHandler",
      "Parameters": {
        "error.$": "$.processingError",
        "alternativeError.$": "$.alternativeError"
      },
      "Next": "NotifyError"
    },
    "NotifyError": {
      "Type": "Task",
      "Agent": "Notifier",
      "Parameters": {
        "type": "error",
        "details.$": "$"
      },
      "Next": "Failed"
    },
    "SaveResults": {
      "Type": "Task",
      "Agent": "Saver",
      "Retry": [
        {
          "ErrorEquals": ["States.ALL"],
          "MaxAttempts": 3
        }
      ],
      "Next": "Success"
    },
    "InvalidInput": {
      "Type": "Fail",
      "Error": "InvalidInput",
      "Cause.$": "$.validationError.Cause"
    },
    "Success": {
      "Type": "Succeed"
    },
    "Failed": {
      "Type": "Fail",
      "Error": "ProcessingFailed",
      "Cause": "Workflow failed after exhausting all recovery options"
    }
  }
}
```

## Best Practices

### 1. Be Specific with Error Matching

```json
{
  "Catch": [
    { "ErrorEquals": ["ValidationError"], "Next": "HandleValidation" },
    { "ErrorEquals": ["AuthError"], "Next": "HandleAuth" },
    { "ErrorEquals": ["States.ALL"], "Next": "CatchAll" }
  ]
}
```

### 2. Use Exponential Backoff

```json
{
  "Retry": [{
    "IntervalSeconds": 1,
    "BackoffRate": 2.0,
    "MaxDelaySeconds": 120
  }]
}
```

### 3. Set Reasonable Timeouts

```json
{
  "TimeoutSeconds": 60,
  "HeartbeatSeconds": 10
}
```

### 4. Combine Retry and Catch

```json
{
  "Retry": [{ "ErrorEquals": ["Transient"], "MaxAttempts": 3 }],
  "Catch": [{ "ErrorEquals": ["States.ALL"], "Next": "Fallback" }]
}
```

### 5. Store Error Context

```json
{
  "Catch": [{
    "ErrorEquals": ["States.ALL"],
    "ResultPath": "$.errorContext",
    "Next": "HandleError"
  }]
}
```
