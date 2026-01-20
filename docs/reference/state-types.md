# State Types Reference

This document provides a complete reference for all state types in Agent State Language.

## Table of Contents

1. [Task State](#task-state)
2. [Choice State](#choice-state)
3. [Map State](#map-state)
4. [Parallel State](#parallel-state)
5. [Pass State](#pass-state)
6. [Wait State](#wait-state)
7. [Succeed State](#succeed-state)
8. [Fail State](#fail-state)
9. [Approval State](#approval-state) (Agent-Native)
10. [Debate State](#debate-state) (Agent-Native)
11. [Checkpoint State](#checkpoint-state) (Agent-Native)

---

## Task State

Executes an agent with the given parameters.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Task"` | Yes | State type identifier |
| `Agent` | string | Yes | Name of the registered agent to execute |
| `Parameters` | object | No | Parameters to pass to the agent |
| `ResultPath` | string | No | JSONPath where to store the result |
| `OutputPath` | string | No | JSONPath to filter output |
| `InputPath` | string | No | JSONPath to filter input |
| `Next` | string | No* | Next state to transition to |
| `End` | boolean | No* | Whether this is a terminal state |
| `Retry` | array | No | Retry configuration |
| `Catch` | array | No | Error handlers |
| `TimeoutSeconds` | integer | No | Maximum execution time |
| `HeartbeatSeconds` | integer | No | Heartbeat interval for long tasks |
| `Memory` | object | No | Memory read/write configuration |
| `Context` | object | No | Context window configuration |
| `Tools` | object | No | Tool permissions |
| `Budget` | object | No | Cost budget for this state |
| `Guardrails` | object | No | Input/output validation |
| `Reasoning` | object | No | Reasoning trace configuration |

*Either `Next` or `End: true` is required.

### Example

```json
{
  "AnalyzeCode": {
    "Type": "Task",
    "Agent": "CodeAnalyzer",
    "Parameters": {
      "code.$": "$.sourceCode",
      "language": "php"
    },
    "ResultPath": "$.analysis",
    "TimeoutSeconds": 60,
    "Retry": [
      {
        "ErrorEquals": ["RateLimitExceeded"],
        "IntervalSeconds": 5,
        "MaxAttempts": 3,
        "BackoffRate": 2.0
      }
    ],
    "Next": "ProcessResults"
  }
}
```

---

## Choice State

Adds branching logic based on conditions.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Choice"` | Yes | State type identifier |
| `Choices` | array | Yes | Array of choice rules |
| `Default` | string | No | Default state if no choices match |
| `InputPath` | string | No | JSONPath to filter input |
| `OutputPath` | string | No | JSONPath to filter output |

### Choice Rules

Each choice rule must have a comparison operator and a `Next` field:

| Operator | Description |
|----------|-------------|
| `StringEquals` | Exact string match |
| `StringEqualsPath` | String match against JSONPath value |
| `StringGreaterThan` | String comparison (lexicographic) |
| `StringLessThan` | String comparison (lexicographic) |
| `StringMatches` | Glob pattern matching |
| `NumericEquals` | Numeric equality |
| `NumericGreaterThan` | Numeric greater than |
| `NumericLessThan` | Numeric less than |
| `NumericGreaterThanEquals` | Numeric >= |
| `NumericLessThanEquals` | Numeric <= |
| `BooleanEquals` | Boolean comparison |
| `IsNull` | Check if null |
| `IsPresent` | Check if field exists |
| `IsString` | Type check |
| `IsNumeric` | Type check |
| `IsBoolean` | Type check |

### Compound Operators

| Operator | Description |
|----------|-------------|
| `And` | All conditions must be true |
| `Or` | Any condition must be true |
| `Not` | Negate condition |

### Example

```json
{
  "RouteByScore": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable": "$.score",
        "NumericGreaterThanEquals": 90,
        "Next": "Excellent"
      },
      {
        "Variable": "$.score",
        "NumericGreaterThanEquals": 70,
        "Next": "Good"
      },
      {
        "And": [
          { "Variable": "$.score", "NumericLessThan": 70 },
          { "Variable": "$.retryCount", "NumericLessThan": 3 }
        ],
        "Next": "Retry"
      }
    ],
    "Default": "Failed"
  }
}
```

---

## Map State

Iterates over an array, executing states for each element.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Map"` | Yes | State type identifier |
| `ItemsPath` | string | Yes | JSONPath to the array to iterate |
| `Iterator` | object | Yes | State machine to run for each item |
| `MaxConcurrency` | integer | No | Max parallel executions (default: 1) |
| `ResultPath` | string | No | Where to store results |
| `ItemSelector` | object | No | Transform each item before processing |
| `ResultSelector` | object | No | Transform results after processing |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "ProcessTasks": {
    "Type": "Map",
    "ItemsPath": "$.tasks",
    "MaxConcurrency": 3,
    "ItemSelector": {
      "taskId.$": "$$.Map.Item.Value.id",
      "taskName.$": "$$.Map.Item.Value.name",
      "context.$": "$.globalContext"
    },
    "Iterator": {
      "StartAt": "ValidateTask",
      "States": {
        "ValidateTask": {
          "Type": "Task",
          "Agent": "Validator",
          "Next": "ExecuteTask"
        },
        "ExecuteTask": {
          "Type": "Task",
          "Agent": "Executor",
          "End": true
        }
      }
    },
    "ResultPath": "$.processedTasks",
    "Next": "Summarize"
  }
}
```

---

## Parallel State

Executes multiple branches concurrently.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Parallel"` | Yes | State type identifier |
| `Branches` | array | Yes | Array of state machine definitions |
| `ResultPath` | string | No | Where to store results |
| `ResultSelector` | object | No | Transform results |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |
| `Retry` | array | No | Retry configuration |
| `Catch` | array | No | Error handlers |

### Example

```json
{
  "ParallelAnalysis": {
    "Type": "Parallel",
    "Branches": [
      {
        "StartAt": "SecurityScan",
        "States": {
          "SecurityScan": {
            "Type": "Task",
            "Agent": "SecurityScanner",
            "End": true
          }
        }
      },
      {
        "StartAt": "PerformanceCheck",
        "States": {
          "PerformanceCheck": {
            "Type": "Task",
            "Agent": "PerformanceAnalyzer",
            "End": true
          }
        }
      }
    ],
    "ResultPath": "$.analysisResults",
    "Next": "CombineResults"
  }
}
```

---

## Pass State

Passes input to output, optionally transforming data.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Pass"` | Yes | State type identifier |
| `Result` | any | No | Static result value |
| `ResultPath` | string | No | Where to store result |
| `Parameters` | object | No | Dynamic parameters |
| `InputPath` | string | No | Filter input |
| `OutputPath` | string | No | Filter output |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "PrepareData": {
    "Type": "Pass",
    "Parameters": {
      "timestamp.$": "$$.State.EnteredTime",
      "input.$": "$.originalInput",
      "config": {
        "maxRetries": 3,
        "timeout": 60
      }
    },
    "ResultPath": "$.prepared",
    "Next": "Process"
  }
}
```

---

## Wait State

Pauses execution for a specified time.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Wait"` | Yes | State type identifier |
| `Seconds` | integer | No* | Wait duration in seconds |
| `Timestamp` | string | No* | ISO 8601 timestamp to wait until |
| `SecondsPath` | string | No* | JSONPath to seconds value |
| `TimestampPath` | string | No* | JSONPath to timestamp |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

*One of `Seconds`, `Timestamp`, `SecondsPath`, or `TimestampPath` is required.

### Example

```json
{
  "WaitForProcessing": {
    "Type": "Wait",
    "Seconds": 30,
    "Next": "CheckStatus"
  }
}
```

---

## Succeed State

Terminates execution successfully.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Succeed"` | Yes | State type identifier |
| `InputPath` | string | No | Filter input |
| `OutputPath` | string | No | Filter output |

### Example

```json
{
  "WorkflowComplete": {
    "Type": "Succeed"
  }
}
```

---

## Fail State

Terminates execution with a failure.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Fail"` | Yes | State type identifier |
| `Error` | string | No | Error name |
| `Cause` | string | No | Human-readable error message |

### Example

```json
{
  "ValidationFailed": {
    "Type": "Fail",
    "Error": "ValidationError",
    "Cause": "Input data did not pass validation"
  }
}
```

---

## Approval State (Agent-Native)

Pauses workflow for human approval. When an `ApprovalHandlerInterface` is configured, the workflow can pause and resume based on human decisions.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Approval"` | Yes | State type identifier |
| `Prompt` | string | Yes | Message shown to approver |
| `Options` | array | No | Available choices (default: approve/reject) |
| `Timeout` | string | No | Max wait time (e.g., "24h", "7d") |
| `Escalation` | object | No | Escalation configuration |
| `Editable` | object | No | Fields that can be edited during approval |
| `ResultPath` | string | No | Where to store approval result |
| `Next` | string | No | Next state after approval |
| `Choices` | array | No | Route based on approval decision |

### Editable Fields

| Field | Type | Description |
|-------|------|-------------|
| `Fields` | array | JSONPath expressions for editable fields |
| `ResultPath` | string | Where to store edited content |

### Example

```json
{
  "ReviewChanges": {
    "Type": "Approval",
    "Prompt": "Review the proposed code changes",
    "Options": ["approve", "reject", "request_changes"],
    "Timeout": "48h",
    "Editable": {
      "Fields": ["$.draft.title", "$.draft.content"],
      "ResultPath": "$.editedDraft"
    },
    "Escalation": {
      "After": "24h",
      "Notify": ["manager@example.com"]
    },
    "Choices": [
      { "Variable": "$.approval", "StringEquals": "approve", "Next": "ApplyChanges" },
      { "Variable": "$.approval", "StringEquals": "request_changes", "Next": "Revise" }
    ],
    "Default": "Cancelled"
  }
}
```

### Approval Handler Integration

To enable pause/resume functionality, implement `ApprovalHandlerInterface`:

```php
<?php

use AgentStateLanguage\Handlers\ApprovalHandlerInterface;

class MyApprovalHandler implements ApprovalHandlerInterface
{
    public function requestApproval(array $request): ?array
    {
        // Return null to pause workflow and wait for human input
        // Return array with decision to continue immediately
        return null;
    }
}

// Configure the engine
$engine->setApprovalHandler(new MyApprovalHandler());
```

### Workflow Result for Paused Approvals

When the workflow pauses:

```php
$result = $engine->run($input);

if ($result->isPaused()) {
    $state = $result->getPausedAtState();       // "ReviewChanges"
    $checkpoint = $result->getCheckpointData(); // Data to restore
    $pending = $result->getPendingInput();      // Approval details
}
```

### Resuming After Approval

```php
$result = $engine->run(
    $checkpointData,    // Original state data
    'ReviewChanges',    // State to resume from
    [                   // Human's decision
        'approval' => 'approve',
        'approver' => 'user@example.com',
        'comment' => 'Approved!',
        'edited_content' => ['title' => 'New Title'],
    ]
);
```
```

---

## Debate State (Agent-Native)

Facilitates multi-agent deliberation.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Debate"` | Yes | State type identifier |
| `Agents` | array | Yes | Participating agents |
| `Topic` | string | No | Static debate topic |
| `TopicPath` | string | No | JSONPath to topic |
| `Rounds` | integer | No | Number of debate rounds (default: 3) |
| `Communication` | object | No | Communication configuration |
| `Consensus` | object | No | Consensus requirements |
| `ResultPath` | string | No | Where to store outcome |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "DebateSolution": {
    "Type": "Debate",
    "Agents": ["ProponentAgent", "OpponentAgent", "JudgeAgent"],
    "TopicPath": "$.proposedSolution",
    "Rounds": 3,
    "Communication": {
      "Style": "turn_based",
      "VisibleHistory": "all"
    },
    "Consensus": {
      "Required": true,
      "Arbiter": "JudgeAgent",
      "Threshold": 0.7
    },
    "ResultPath": "$.debateResult",
    "Next": "ProcessDecision"
  }
}
```

---

## Checkpoint State (Agent-Native)

Creates a save point for workflow resumption.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Checkpoint"` | Yes | State type identifier |
| `Name` | string | Yes | Checkpoint identifier |
| `Storage` | string | No | Storage backend |
| `TTL` | string | No | How long to retain checkpoint |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "SaveProgress": {
    "Type": "Checkpoint",
    "Name": "after_analysis",
    "TTL": "7d",
    "Next": "ContinueProcessing"
  }
}
```
