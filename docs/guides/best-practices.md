# Best Practices

This guide covers best practices for designing, building, and maintaining ASL workflows in production environments.

## Workflow Design

### Keep States Focused

Each state should do one thing well. This improves testability, debugging, and reusability:

```json
{
  "Comment": "Good - focused, single-responsibility states",
  "States": {
    "ParseInput": {
      "Type": "Task",
      "Agent": "InputParser",
      "Parameters": {
        "raw.$": "$.input"
      },
      "ResultPath": "$.parsed",
      "Next": "Validate"
    },
    "Validate": {
      "Type": "Task",
      "Agent": "Validator",
      "Parameters": {
        "data.$": "$.parsed"
      },
      "ResultPath": "$.validation",
      "Next": "Process"
    },
    "Process": {
      "Type": "Task",
      "Agent": "Processor",
      "Parameters": {
        "validData.$": "$.validation.data"
      },
      "End": true
    }
  }
}
```

**Avoid** monolithic states that do everything:

```json
{
  "Comment": "Bad - monolithic state doing too much",
  "States": {
    "DoEverything": {
      "Type": "Task",
      "Agent": "MonolithAgent",
      "End": true
    }
  }
}
```

### Use Descriptive State Names

State names should clearly describe what the state does:

```json
{
  "ValidateUserInput": { },
  "SendConfirmationEmail": { },
  "ProcessPaymentTransaction": { },
  "CheckInventoryAvailability": { },
  "GenerateInvoicePDF": { }
}
```

**Avoid** generic or unclear names:

```json
{
  "Step1": { },
  "DoStuff": { },
  "Handler": { },
  "Process": { }
}
```

### Document Your Workflows

Add comments at workflow and state levels:

```json
{
  "Comment": "Customer onboarding workflow v2.1 - Handles new customer registration, verification, and welcome sequence",
  "Version": "2.1.0",
  "StartAt": "ValidateEmail",
  "States": {
    "ValidateEmail": {
      "Comment": "Verify email format and check domain is not disposable",
      "Type": "Task",
      "Agent": "EmailValidator",
      "Next": "CheckDuplicates"
    },
    "CheckDuplicates": {
      "Comment": "Ensure customer doesn't already exist in database",
      "Type": "Task",
      "Agent": "DuplicateChecker",
      "End": true
    }
  }
}
```

## Error Handling

### Always Retry External Services

External services can fail transiently. Always configure retries:

```json
{
  "CallPaymentGateway": {
    "Type": "Task",
    "Agent": "PaymentClient",
    "Retry": [
      {
        "ErrorEquals": ["States.Timeout"],
        "MaxAttempts": 2,
        "IntervalSeconds": 2,
        "BackoffRate": 1.5
      },
      {
        "ErrorEquals": ["States.RateLimitExceeded"],
        "MaxAttempts": 5,
        "IntervalSeconds": 30,
        "BackoffRate": 2.0,
        "MaxIntervalSeconds": 120
      },
      {
        "ErrorEquals": ["States.TaskFailed"],
        "MaxAttempts": 3,
        "IntervalSeconds": 5,
        "BackoffRate": 2.0
      }
    ],
    "Next": "ProcessPaymentResult"
  }
}
```

### Order Catch Handlers Correctly

Specific handlers first, then catch-all:

```json
{
  "RiskyOperation": {
    "Type": "Task",
    "Agent": "RiskyAgent",
    "Catch": [
      {
        "ErrorEquals": ["ValidationError"],
        "ResultPath": "$.validationError",
        "Next": "HandleValidationError"
      },
      {
        "ErrorEquals": ["AuthenticationError", "AuthorizationError"],
        "ResultPath": "$.authError",
        "Next": "HandleAuthError"
      },
      {
        "ErrorEquals": ["States.Timeout"],
        "ResultPath": "$.timeoutError",
        "Next": "HandleTimeout"
      },
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.error",
        "Next": "GlobalErrorHandler"
      }
    ]
  }
}
```

### Set Appropriate Timeouts

Match timeouts to expected operation duration:

| Operation Type | Recommended Timeout | Heartbeat |
|----------------|---------------------|-----------|
| Quick API call | 30s | - |
| Standard task | 120s | - |
| LLM generation | 300s | 60s |
| Long processing | 600s | 60s |
| Batch operations | 1800s | 120s |

```json
{
  "QuickTask": {
    "Type": "Task",
    "Agent": "FastAgent",
    "TimeoutSeconds": 30,
    "Next": "Continue"
  },
  "LongRunningTask": {
    "Type": "Task",
    "Agent": "SlowAgent",
    "TimeoutSeconds": 600,
    "HeartbeatSeconds": 60,
    "Next": "Continue"
  }
}
```

## Security

### Use Allowlists for Tools

Always use explicit allowlists rather than denylists:

```json
{
  "ResearchTask": {
    "Type": "Task",
    "Agent": "Researcher",
    "Tools": {
      "Allowed": ["web_search", "fetch_url", "read_file"]
    }
  }
}
```

**Avoid** relying only on denylists:

```json
{
  "Tools": {
    "Denied": ["execute_shell"]
  }
}
```

### Sandbox Code Execution

Always sandbox any code execution:

```json
{
  "ExecuteUserCode": {
    "Type": "Task",
    "Agent": "CodeRunner",
    "Tools": {
      "Allowed": ["execute_code"],
      "Sandboxed": true,
      "Sandbox": {
        "Environment": "docker",
        "Image": "python:3.11-slim",
        "Timeout": "30s",
        "Memory": "256M",
        "Network": false,
        "ReadOnlyFS": true
      }
    }
  }
}
```

### Restrict File System Access

```json
{
  "FileProcessor": {
    "Type": "Task",
    "Agent": "FileAgent",
    "Tools": {
      "Allowed": ["read_file", "write_file"],
      "FileSystem": {
        "AllowedPaths": ["./data/**", "./output/**"],
        "DeniedPaths": [
          "./.env",
          "./.env.*",
          "./secrets/**",
          "./.git/**",
          "./config/credentials.*"
        ],
        "MaxFileSize": "10M"
      }
    }
  }
}
```

### Validate All External Input

```json
{
  "ValidateInput": {
    "Type": "Task",
    "Agent": "InputValidator",
    "Parameters": {
      "input.$": "$.userInput",
      "schema": "user-request"
    },
    "Catch": [
      {
        "ErrorEquals": ["ValidationError"],
        "Next": "RejectInvalidInput"
      }
    ],
    "Next": "ProcessValidInput"
  }
}
```

## Cost Management

### Set Workflow Budgets

Always set cost limits:

```json
{
  "Budget": {
    "MaxCost": "$10.00",
    "MaxTokens": 100000,
    "OnExceed": "PauseAndNotify"
  }
}
```

### Implement Model Fallback

Use cheaper models as budget depletes:

```json
{
  "Budget": {
    "MaxCost": "$20.00",
    "Fallback": {
      "Cascade": [
        { "When": "BudgetAt50Percent", "UseModel": "claude-sonnet-4-5" },
        { "When": "BudgetAt75Percent", "UseModel": "claude-haiku-4-5" },
        { "When": "BudgetAt90Percent", "Action": "ReduceQuality" },
        { "When": "BudgetAt95Percent", "Action": "PauseAndNotify" }
      ]
    }
  }
}
```

### Configure Cost Alerts

```json
{
  "Budget": {
    "MaxCost": "$50.00",
    "Alerts": [
      { "At": "50%", "Notify": ["team@example.com"] },
      { "At": "75%", "Notify": ["manager@example.com"] },
      { "At": "90%", "Notify": ["#alerts-critical"], "Channel": "slack", "Priority": "high" }
    ]
  }
}
```

### Cache Expensive Operations

```json
{
  "ExpensiveAnalysis": {
    "Type": "Task",
    "Agent": "DeepAnalyzer",
    "Cache": {
      "Enabled": true,
      "Key.$": "States.Hash($.document, 'SHA-256')",
      "TTL": "24h"
    },
    "Next": "Continue"
  }
}
```

## Testing

### Create Comprehensive Mock Agents

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class MockAgent implements AgentInterface
{
    private array $responses;
    private int $callCount = 0;
    private array $capturedParams = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function execute(array $parameters): array
    {
        $this->capturedParams[] = $parameters;
        $response = $this->responses[$this->callCount] ?? end($this->responses);
        $this->callCount++;
        return $response;
    }

    public function getName(): string { return 'MockAgent'; }
    public function getCallCount(): int { return $this->callCount; }
    public function getCapturedParams(): array { return $this->capturedParams; }
}
```

### Test All Code Paths

```php
public function testWorkflowHandlesAllChoicePaths(): void
{
    // Test high severity path
    $result = $engine->run(['severity' => 'critical']);
    $this->assertEquals('escalated', $result->getOutput()['status']);
    
    // Test medium severity path
    $result = $engine->run(['severity' => 'medium']);
    $this->assertEquals('queued', $result->getOutput()['status']);
    
    // Test low severity path (default)
    $result = $engine->run(['severity' => 'low']);
    $this->assertEquals('auto_resolved', $result->getOutput()['status']);
}
```

### Validate Before Deployment

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Validation\WorkflowValidator;

// Method 1: Using engine
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$errors = $engine->validate();

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Validation Error: {$error['message']}\n";
    }
    exit(1);
}

// Method 2: Standalone validation
$validator = new WorkflowValidator();
$result = $validator->validateFile('workflow.asl.json');

if (!$result->isValid()) {
    throw new \Exception('Workflow validation failed');
}
```

## Performance

### Use Parallel Execution

When tasks are independent, run them in parallel:

```json
{
  "ParallelAnalysis": {
    "Type": "Parallel",
    "Branches": [
      {
        "StartAt": "SecurityScan",
        "States": {
          "SecurityScan": { "Type": "Task", "Agent": "SecurityScanner", "End": true }
        }
      },
      {
        "StartAt": "PerformanceCheck",
        "States": {
          "PerformanceCheck": { "Type": "Task", "Agent": "PerformanceChecker", "End": true }
        }
      },
      {
        "StartAt": "ComplianceAudit",
        "States": {
          "ComplianceAudit": { "Type": "Task", "Agent": "ComplianceAuditor", "End": true }
        }
      }
    ],
    "ResultPath": "$.analysisResults",
    "Next": "AggregateResults"
  }
}
```

### Control Map Concurrency

Prevent overwhelming external services:

```json
{
  "ProcessItems": {
    "Type": "Map",
    "ItemsPath": "$.items",
    "MaxConcurrency": 5,
    "Iterator": {
      "StartAt": "ProcessItem",
      "States": {
        "ProcessItem": { "Type": "Task", "Agent": "ItemProcessor", "End": true }
      }
    }
  }
}
```

### Minimize Data Transfer

Only pass what's needed:

```json
{
  "Type": "Task",
  "Agent": "Processor",
  "Parameters": {
    "id.$": "$.document.id",
    "content.$": "$.document.content"
  },
  "OutputPath": "$.result"
}
```

**Avoid** passing entire large objects:

```json
{
  "Parameters": {
    "everything.$": "$"
  }
}
```

## Maintenance

### Version Your Workflows

```json
{
  "Comment": "Order Processing Workflow",
  "Version": "2.3.1",
  "Metadata": {
    "author": "team@example.com",
    "lastModified": "2025-01-20",
    "changelog": "Added refund handling"
  }
}
```

### Use Templates for Reusability

```json
{
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "notification": "./templates/notification.asl.json",
    "errorHandling": "./templates/error-handling.asl.json"
  },
  "States": {
    "ValidateInput": {
      "Type": "Include",
      "Template": "validation",
      "Parameters": { "schema": "order" },
      "Next": "Process"
    }
  }
}
```

### Document Custom Agents

Create documentation for each agent:

```markdown
# OrderProcessor Agent

## Purpose
Processes validated orders and creates transactions.

## Parameters
| Name | Type | Required | Description |
|------|------|----------|-------------|
| orderId | string | Yes | Unique order identifier |
| items | array | Yes | List of order items |
| customer | object | Yes | Customer information |

## Output
```json
{
  "transactionId": "txn_123",
  "status": "completed",
  "total": 99.99
}
```

## Errors
- `InsufficientInventory` - Item not in stock
- `PaymentFailed` - Payment could not be processed
```

## Monitoring

### Enable Observability

```json
{
  "Observability": {
    "Tracing": {
      "Enabled": true,
      "SampleRate": 1.0
    },
    "Metrics": {
      "Enabled": true,
      "Dimensions": ["workflow", "state", "agent"]
    },
    "Logging": {
      "Level": "info",
      "IncludeInputOutput": false
    }
  }
}
```

### Track Key Metrics

Monitor these metrics for each workflow:
- Execution duration
- Success/failure rate
- Token usage
- Cost per execution
- State transition times

## Summary Checklist

Before deploying a workflow, verify:

- [ ] All states have descriptive names
- [ ] Workflow has version and comment
- [ ] All external calls have retry configuration
- [ ] Error handlers exist (including catch-all)
- [ ] Timeouts are appropriate for each task
- [ ] Tools use explicit allowlists
- [ ] Code execution is sandboxed
- [ ] Budget limits are set
- [ ] Cost alerts are configured
- [ ] Caching is enabled for expensive operations
- [ ] Unit tests cover all paths
- [ ] Validation passes
- [ ] Documentation is complete

## Related

- [Testing Workflows](testing-workflows.md)
- [Production Deployment](production-deployment.md)
- [Tutorial 11: Error Handling](../tutorials/11-error-handling.md)
- [Tutorial 10: Cost Management](../tutorials/10-cost-management.md)
