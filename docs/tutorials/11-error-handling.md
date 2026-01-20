# Tutorial 11: Error Handling

Learn how to handle errors gracefully in your AI agent workflows.

## What You'll Learn

- Retry configurations with exponential backoff
- Catch handlers for different error types
- Fallback patterns for resilience
- Timeout management
- Building a fault-tolerant API integration

## Prerequisites

- Completed [Tutorial 10: Cost Management](10-cost-management.md)
- Understanding of Task states

## The Scenario

We'll build a data enrichment workflow that:

1. Calls an external API that may fail intermittently
2. Retries with exponential backoff on transient errors
3. Falls back to a secondary service if primary fails
4. Catches and handles specific error types differently
5. Times out gracefully on long-running operations

## Step 1: Understanding Error Types

ASL defines standard error types for consistent handling:

| Error Type | Description |
|------------|-------------|
| `States.ALL` | Matches all errors (catch-all) |
| `States.Timeout` | Operation exceeded time limit |
| `States.TaskFailed` | Agent execution failed |
| `States.RateLimitExceeded` | API rate limit hit |
| `States.BudgetExceeded` | Cost limit reached |
| `States.ValidationError` | Input validation failed |
| `States.AgentNotFound` | Specified agent doesn't exist |

### Custom Error Types

Agents can throw custom errors:

```php
throw new \AgentStateLanguage\Exceptions\WorkflowException(
    'CustomError',
    'Specific error message'
);
```

## Step 2: Create the Agents

### UnreliableAPIAgent

An agent that simulates a flaky external API:

```php
<?php

namespace MyOrg\ErrorHandling;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;

class UnreliableAPIAgent implements AgentInterface
{
    private int $attemptCount = 0;
    private int $failuresBeforeSuccess;
    
    public function __construct(int $failuresBeforeSuccess = 2)
    {
        $this->failuresBeforeSuccess = $failuresBeforeSuccess;
    }

    public function execute(array $parameters): array
    {
        $this->attemptCount++;
        $endpoint = $parameters['endpoint'] ?? '';
        $payload = $parameters['payload'] ?? [];
        
        // Simulate intermittent failures
        if ($this->attemptCount <= $this->failuresBeforeSuccess) {
            $errorType = $this->attemptCount === 1 
                ? 'States.Timeout' 
                : 'States.RateLimitExceeded';
            
            throw new WorkflowException(
                $errorType,
                "API call failed on attempt {$this->attemptCount}"
            );
        }
        
        // Success on third attempt
        return [
            'success' => true,
            'data' => [
                'enrichedField' => 'value from API',
                'additionalInfo' => 'supplementary data'
            ],
            'attempts' => $this->attemptCount,
            'endpoint' => $endpoint
        ];
    }
    
    public function resetAttemptCount(): void
    {
        $this->attemptCount = 0;
    }

    public function getName(): string
    {
        return 'UnreliableAPIAgent';
    }
}
```

### ValidationAgent

An agent that validates input data:

```php
<?php

namespace MyOrg\ErrorHandling;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;

class ValidationAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $schema = $parameters['schema'] ?? 'default';
        
        $errors = $this->validate($data, $schema);
        
        if (!empty($errors)) {
            throw new WorkflowException(
                'ValidationError',
                'Input validation failed: ' . implode(', ', $errors),
                ['errors' => $errors]
            );
        }
        
        return [
            'valid' => true,
            'data' => $data,
            'schema' => $schema,
            'validatedAt' => date('c')
        ];
    }
    
    private function validate(array $data, string $schema): array
    {
        $errors = [];
        
        // Basic validation rules
        if (empty($data['id'])) {
            $errors[] = 'id is required';
        }
        
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email format is invalid';
        }
        
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] < 0)) {
            $errors[] = 'amount must be a positive number';
        }
        
        return $errors;
    }

    public function getName(): string
    {
        return 'ValidationAgent';
    }
}
```

### FallbackAgent

A backup service when primary fails:

```php
<?php

namespace MyOrg\ErrorHandling;

use AgentStateLanguage\Agents\AgentInterface;

class FallbackAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $originalRequest = $parameters['originalRequest'] ?? [];
        $error = $parameters['error'] ?? [];
        
        // Provide degraded but functional response
        return [
            'success' => true,
            'degraded' => true,
            'data' => [
                'enrichedField' => 'fallback value',
                'source' => 'backup_service'
            ],
            'originalError' => $error['message'] ?? 'Unknown error',
            'fallbackUsed' => true
        ];
    }

    public function getName(): string
    {
        return 'FallbackAgent';
    }
}
```

### SlowProcessorAgent

An agent that may timeout:

```php
<?php

namespace MyOrg\ErrorHandling;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\HeartbeatAgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;

class SlowProcessorAgent implements AgentInterface, HeartbeatAgentInterface
{
    private ?\Closure $heartbeatCallback = null;
    private int $processingTime;
    private int $timeout;
    
    public function __construct(int $processingTimeSeconds = 10)
    {
        $this->processingTime = $processingTimeSeconds;
        $this->timeout = 30; // Default timeout
    }
    
    public function setHeartbeatCallback(\Closure $callback): void
    {
        $this->heartbeatCallback = $callback;
    }

    public function execute(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $this->timeout = $parameters['_timeout'] ?? 30;
        
        $startTime = time();
        $processedItems = 0;
        $totalItems = count($data);
        
        foreach ($data as $index => $item) {
            // Simulate processing time
            usleep(100000); // 0.1 seconds per item
            $processedItems++;
            
            // Send heartbeat to prevent timeout
            if ($this->heartbeatCallback) {
                ($this->heartbeatCallback)([
                    'progress' => round(($processedItems / max($totalItems, 1)) * 100),
                    'processed' => $processedItems,
                    'total' => $totalItems
                ]);
            }
            
            // Check if we're approaching timeout
            if (time() - $startTime > $this->timeout - 5) {
                throw new WorkflowException(
                    'States.Timeout',
                    'Processing would exceed timeout limit'
                );
            }
        }
        
        return [
            'processed' => $processedItems,
            'total' => $totalItems,
            'duration' => time() - $startTime,
            'completedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'SlowProcessorAgent';
    }
}
```

## Step 3: Define the Workflow

Create `fault-tolerant.asl.json`:

```json
{
  "Comment": "Fault-tolerant data enrichment workflow",
  "StartAt": "ValidateInput",
  "States": {
    "ValidateInput": {
      "Type": "Task",
      "Agent": "ValidationAgent",
      "Parameters": {
        "data.$": "$.input",
        "schema": "enrichment"
      },
      "ResultPath": "$.validation",
      "Catch": [
        {
          "ErrorEquals": ["ValidationError"],
          "ResultPath": "$.error",
          "Next": "HandleValidationError"
        }
      ],
      "Next": "EnrichData"
    },
    "HandleValidationError": {
      "Type": "Pass",
      "Parameters": {
        "success": false,
        "errorType": "validation",
        "message.$": "$.error.message",
        "errors.$": "$.error.details.errors",
        "suggestion": "Please fix the validation errors and retry"
      },
      "End": true
    },
    "EnrichData": {
      "Type": "Task",
      "Agent": "UnreliableAPIAgent",
      "Parameters": {
        "endpoint": "https://api.example.com/enrich",
        "payload.$": "$.validation.data"
      },
      "Retry": [
        {
          "ErrorEquals": ["States.Timeout"],
          "MaxAttempts": 2,
          "IntervalSeconds": 2,
          "BackoffRate": 1.5
        },
        {
          "ErrorEquals": ["States.RateLimitExceeded"],
          "MaxAttempts": 3,
          "IntervalSeconds": 10,
          "BackoffRate": 2.0,
          "MaxIntervalSeconds": 60
        },
        {
          "ErrorEquals": ["States.TaskFailed"],
          "MaxAttempts": 2,
          "IntervalSeconds": 5,
          "BackoffRate": 2.0
        }
      ],
      "Catch": [
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.enrichError",
          "Next": "TryFallbackService"
        }
      ],
      "ResultPath": "$.enrichment",
      "Next": "ProcessEnrichedData"
    },
    "TryFallbackService": {
      "Type": "Task",
      "Agent": "FallbackAgent",
      "Parameters": {
        "originalRequest.$": "$.validation.data",
        "error.$": "$.enrichError"
      },
      "Retry": [
        {
          "ErrorEquals": ["States.ALL"],
          "MaxAttempts": 2,
          "IntervalSeconds": 5
        }
      ],
      "Catch": [
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.fallbackError",
          "Next": "AllServicesFailed"
        }
      ],
      "ResultPath": "$.enrichment",
      "Next": "ProcessEnrichedData"
    },
    "ProcessEnrichedData": {
      "Type": "Task",
      "Agent": "SlowProcessorAgent",
      "Parameters": {
        "data.$": "$.enrichment.data"
      },
      "TimeoutSeconds": 60,
      "HeartbeatSeconds": 15,
      "Retry": [
        {
          "ErrorEquals": ["States.Timeout"],
          "MaxAttempts": 1,
          "IntervalSeconds": 0
        }
      ],
      "Catch": [
        {
          "ErrorEquals": ["States.Timeout"],
          "ResultPath": "$.processingError",
          "Next": "HandleTimeout"
        }
      ],
      "ResultPath": "$.processing",
      "Next": "BuildResult"
    },
    "HandleTimeout": {
      "Type": "Pass",
      "Parameters": {
        "success": true,
        "partial": true,
        "message": "Processing timed out, partial results available",
        "enrichment.$": "$.enrichment",
        "warning": "Some data may not be fully processed"
      },
      "End": true
    },
    "AllServicesFailed": {
      "Type": "Fail",
      "Error": "ServiceUnavailable",
      "Cause": "Both primary and fallback services failed"
    },
    "BuildResult": {
      "Type": "Pass",
      "Parameters": {
        "success": true,
        "partial": false,
        "data.$": "$.enrichment.data",
        "processing.$": "$.processing",
        "usedFallback.$": "$.enrichment.fallbackUsed",
        "retryAttempts.$": "$.enrichment.attempts"
      },
      "End": true
    }
  }
}
```

## Step 4: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\ErrorHandling\UnreliableAPIAgent;
use MyOrg\ErrorHandling\ValidationAgent;
use MyOrg\ErrorHandling\FallbackAgent;
use MyOrg\ErrorHandling\SlowProcessorAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('ValidationAgent', new ValidationAgent());
$registry->register('UnreliableAPIAgent', new UnreliableAPIAgent(2)); // Fail twice then succeed
$registry->register('FallbackAgent', new FallbackAgent());
$registry->register('SlowProcessorAgent', new SlowProcessorAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('fault-tolerant.asl.json', $registry);

// Test 1: Successful after retries
echo "=== Test 1: Success After Retries ===\n";
$result1 = $engine->run([
    'input' => [
        'id' => '123',
        'email' => 'user@example.com',
        'amount' => 99.99
    ]
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Success: " . ($output['success'] ? 'Yes' : 'No') . "\n";
    echo "Used Fallback: " . ($output['usedFallback'] ? 'Yes' : 'No') . "\n";
    echo "Retry Attempts: " . ($output['retryAttempts'] ?? 1) . "\n";
}

// Test 2: Validation error
echo "\n=== Test 2: Validation Error ===\n";
$result2 = $engine->run([
    'input' => [
        'email' => 'invalid-email',
        'amount' => -50
    ]
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Error Type: " . ($output['errorType'] ?? 'none') . "\n";
    echo "Errors: " . implode(', ', $output['errors'] ?? []) . "\n";
}

// Test 3: Fallback required (too many failures)
echo "\n=== Test 3: Fallback Service Used ===\n";
$registry->register('UnreliableAPIAgent', new UnreliableAPIAgent(10)); // Always fail
$engine = WorkflowEngine::fromFile('fault-tolerant.asl.json', $registry);

$result3 = $engine->run([
    'input' => [
        'id' => '456',
        'email' => 'test@example.com'
    ]
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Success: " . ($output['success'] ? 'Yes' : 'No') . "\n";
    echo "Used Fallback: " . ($output['usedFallback'] ? 'Yes' : 'No') . "\n";
}
```

## Expected Output

```
=== Test 1: Success After Retries ===
Success: Yes
Used Fallback: No
Retry Attempts: 3

=== Test 2: Validation Error ===
Error Type: validation
Errors: id is required, email format is invalid, amount must be a positive number

=== Test 3: Fallback Service Used ===
Success: Yes
Used Fallback: Yes
```

## Retry Configuration Reference

### Basic Retry

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.Timeout"],
      "MaxAttempts": 3,
      "IntervalSeconds": 5,
      "BackoffRate": 2.0
    }
  ]
}
```

| Parameter | Description | Default |
|-----------|-------------|---------|
| `ErrorEquals` | Errors to match | Required |
| `MaxAttempts` | Maximum retry count | 3 |
| `IntervalSeconds` | Initial wait between retries | 1 |
| `BackoffRate` | Multiplier for each retry | 2.0 |
| `MaxIntervalSeconds` | Cap on wait time | No limit |
| `JitterStrategy` | Add randomness: `none`, `full` | `none` |

### Backoff Calculation

With `IntervalSeconds: 5` and `BackoffRate: 2.0`:

| Attempt | Wait Time |
|---------|-----------|
| 1 | 5 seconds |
| 2 | 10 seconds |
| 3 | 20 seconds |
| 4 | 40 seconds |

### Jitter Strategy

Add randomness to prevent thundering herd:

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.RateLimitExceeded"],
      "MaxAttempts": 5,
      "IntervalSeconds": 10,
      "BackoffRate": 2.0,
      "JitterStrategy": "full"
    }
  ]
}
```

## Catch Configuration

### Specific Error Handling

```json
{
  "Catch": [
    {
      "ErrorEquals": ["ValidationError"],
      "ResultPath": "$.validationError",
      "Next": "HandleValidation"
    },
    {
      "ErrorEquals": ["AuthenticationError", "AuthorizationError"],
      "ResultPath": "$.authError",
      "Next": "HandleAuth"
    },
    {
      "ErrorEquals": ["States.ALL"],
      "ResultPath": "$.error",
      "Next": "CatchAll"
    }
  ]
}
```

**Important**: `States.ALL` must be last - it matches everything!

### Error Data in ResultPath

When an error is caught, the following is stored at `ResultPath`:

```json
{
  "error": "ValidationError",
  "message": "Input validation failed",
  "cause": "email format is invalid",
  "details": { "field": "email" }
}
```

## Timeout Configuration

```json
{
  "TimeoutSeconds": 300,
  "HeartbeatSeconds": 60
}
```

| Parameter | Description |
|-----------|-------------|
| `TimeoutSeconds` | Max execution time |
| `HeartbeatSeconds` | Required heartbeat interval |

If no heartbeat is received within `HeartbeatSeconds`, the task times out.

## Experiment

Try these modifications:

### Add Circuit Breaker Pattern

```json
{
  "CircuitBreaker": {
    "Enabled": true,
    "FailureThreshold": 5,
    "SuccessThreshold": 2,
    "Timeout": "30s"
  }
}
```

### Conditional Retry

```json
{
  "Retry": [
    {
      "ErrorEquals": ["RetryableError"],
      "Condition.$": "$.retryCount < 5 && States.CurrentCost() < 10.0",
      "MaxAttempts": 5
    }
  ]
}
```

## Common Mistakes

### Wrong Catch Order

```json
{
  "Catch": [
    { "ErrorEquals": ["States.ALL"], "Next": "CatchAll" },
    { "ErrorEquals": ["ValidationError"], "Next": "HandleValidation" }
  ]
}
```

**Problem**: `States.ALL` catches everything; specific handler never runs.

**Fix**: Put specific handlers first, `States.ALL` last.

### No Maximum Interval

```json
{
  "Retry": [
    {
      "IntervalSeconds": 60,
      "BackoffRate": 3.0,
      "MaxAttempts": 10
    }
  ]
}
```

**Problem**: Wait time grows to 60 × 3^9 = 1.1 million seconds!

**Fix**: Add `MaxIntervalSeconds`.

### Retrying Non-Transient Errors

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.ALL"],
      "MaxAttempts": 5
    }
  ]
}
```

**Problem**: Retries validation errors, auth failures, etc.

**Fix**: Only retry transient errors (`States.Timeout`, `States.RateLimitExceeded`).

## Summary

You've learned:

- ✅ Retry configurations with exponential backoff
- ✅ Handling different error types with Catch
- ✅ Fallback patterns for resilience
- ✅ Timeout and heartbeat management
- ✅ Building fault-tolerant workflows
- ✅ Best practices for error handling

## Next Steps

- [Tutorial 12: Building Skills](12-building-skills.md) - Reusable templates
