# 2. State Types

This section provides comprehensive documentation for all state types in Agent State Language.

## Common Fields

All states share these common fields:

| Field | Type | Description |
|-------|------|-------------|
| `Type` | string | **Required.** The state type |
| `Comment` | string | Human-readable description |
| `InputPath` | string | JSONPath to filter input |
| `OutputPath` | string | JSONPath to filter output |

## Task State

The Task state executes an agent with specified parameters.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Task"` | Yes | State type |
| `Agent` | string | Yes | Name of the registered agent |
| `Parameters` | object | No | Parameters to pass to agent |
| `ResultPath` | string | No | Where to store the result |
| `ResultSelector` | object | No | Transform result before storing |
| `TimeoutSeconds` | integer | No | Maximum execution time |
| `HeartbeatSeconds` | integer | No | Heartbeat interval |
| `Retry` | array | No | Retry configuration |
| `Catch` | array | No | Error handlers |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state flag |

*Either `Next` or `End: true` is required.

### Agent-Specific Fields

| Field | Type | Description |
|-------|------|-------------|
| `Memory` | object | Memory read/write configuration |
| `Context` | object | Context window configuration |
| `Tools` | object | Tool permissions |
| `Budget` | object | Cost budget |
| `Guardrails` | object | Input/output validation |
| `Reasoning` | object | Reasoning trace configuration |

### Example

```json
{
  "AnalyzeCode": {
    "Type": "Task",
    "Comment": "Analyze source code for issues",
    "Agent": "CodeAnalyzer",
    "Parameters": {
      "code.$": "$.sourceCode",
      "language": "php",
      "options": {
        "checkSecurity": true,
        "checkPerformance": true
      }
    },
    "Tools": {
      "Allowed": ["read_file", "grep"],
      "Denied": ["write_file"]
    },
    "Budget": {
      "MaxTokens": 5000
    },
    "ResultPath": "$.analysis",
    "TimeoutSeconds": 120,
    "Retry": [
      {
        "ErrorEquals": ["RateLimitExceeded"],
        "IntervalSeconds": 30,
        "MaxAttempts": 3
      }
    ],
    "Catch": [
      {
        "ErrorEquals": ["States.Timeout"],
        "ResultPath": "$.error",
        "Next": "HandleTimeout"
      }
    ],
    "Next": "ProcessResults"
  }
}
```

---

## Choice State

The Choice state adds conditional branching based on input data.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Choice"` | Yes | State type |
| `Choices` | array | Yes | Array of choice rules |
| `Default` | string | No | Default next state |

### Choice Rules

Each rule must specify a comparison and a `Next` state:

```json
{
  "Variable": "$.score",
  "NumericGreaterThan": 80,
  "Next": "HighScore"
}
```

### Comparison Operators

#### String Comparisons

| Operator | Description |
|----------|-------------|
| `StringEquals` | Exact match |
| `StringEqualsPath` | Match against JSONPath value |
| `StringGreaterThan` | Lexicographic > |
| `StringGreaterThanEquals` | Lexicographic >= |
| `StringLessThan` | Lexicographic < |
| `StringLessThanEquals` | Lexicographic <= |
| `StringMatches` | Glob pattern match |

#### Numeric Comparisons

| Operator | Description |
|----------|-------------|
| `NumericEquals` | Equal |
| `NumericEqualsPath` | Equal to JSONPath value |
| `NumericGreaterThan` | Greater than |
| `NumericGreaterThanEquals` | Greater than or equal |
| `NumericLessThan` | Less than |
| `NumericLessThanEquals` | Less than or equal |

#### Boolean Comparisons

| Operator | Description |
|----------|-------------|
| `BooleanEquals` | Boolean comparison |
| `BooleanEqualsPath` | Compare to JSONPath value |

#### Type Checks

| Operator | Description |
|----------|-------------|
| `IsNull` | Check if null |
| `IsPresent` | Check if field exists |
| `IsNumeric` | Check if numeric |
| `IsString` | Check if string |
| `IsBoolean` | Check if boolean |
| `IsTimestamp` | Check if ISO 8601 timestamp |

### Compound Operators

```json
{
  "And": [
    { "Variable": "$.score", "NumericGreaterThan": 80 },
    { "Variable": "$.verified", "BooleanEquals": true }
  ],
  "Next": "VerifiedHighScore"
}
```

| Operator | Description |
|----------|-------------|
| `And` | All conditions must be true |
| `Or` | Any condition must be true |
| `Not` | Negate a condition |

### Example

```json
{
  "RouteByIntent": {
    "Type": "Choice",
    "Comment": "Route based on classified intent",
    "Choices": [
      {
        "And": [
          { "Variable": "$.intent", "StringEquals": "purchase" },
          { "Variable": "$.amount", "NumericGreaterThan": 1000 }
        ],
        "Next": "HighValuePurchase"
      },
      {
        "Variable": "$.intent",
        "StringEquals": "purchase",
        "Next": "StandardPurchase"
      },
      {
        "Variable": "$.intent",
        "StringEquals": "support",
        "Next": "CustomerSupport"
      },
      {
        "Or": [
          { "Variable": "$.intent", "StringEquals": "refund" },
          { "Variable": "$.intent", "StringEquals": "cancel" }
        ],
        "Next": "RefundFlow"
      }
    ],
    "Default": "GeneralInquiry"
  }
}
```

---

## Map State

The Map state iterates over an array, executing a sub-workflow for each element.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Map"` | Yes | State type |
| `ItemsPath` | string | Yes | JSONPath to the array |
| `Iterator` | object | Yes | State machine for each item |
| `MaxConcurrency` | integer | No | Max parallel executions |
| `ItemSelector` | object | No | Transform each item |
| `ResultPath` | string | No | Where to store results |
| `ResultSelector` | object | No | Transform results |
| `Retry` | array | No | Retry configuration |
| `Catch` | array | No | Error handlers |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Context Variables

Within the iterator, these context variables are available:

| Variable | Description |
|----------|-------------|
| `$$.Map.Item.Value` | Current item value |
| `$$.Map.Item.Index` | Current item index (0-based) |

### Example

```json
{
  "ProcessAllTasks": {
    "Type": "Map",
    "Comment": "Process each task in parallel",
    "ItemsPath": "$.tasks",
    "MaxConcurrency": 5,
    "ItemSelector": {
      "task.$": "$$.Map.Item.Value",
      "index.$": "$$.Map.Item.Index",
      "globalConfig.$": "$.config"
    },
    "Iterator": {
      "StartAt": "ValidateTask",
      "States": {
        "ValidateTask": {
          "Type": "Task",
          "Agent": "Validator",
          "Parameters": {
            "task.$": "$.task"
          },
          "Next": "ExecuteTask"
        },
        "ExecuteTask": {
          "Type": "Task",
          "Agent": "Executor",
          "Parameters": {
            "task.$": "$.task",
            "config.$": "$.globalConfig"
          },
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

The Parallel state executes multiple branches concurrently.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Parallel"` | Yes | State type |
| `Branches` | array | Yes | Array of state machines |
| `ResultPath` | string | No | Where to store results |
| `ResultSelector` | object | No | Transform results |
| `Retry` | array | No | Retry configuration |
| `Catch` | array | No | Error handlers |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Result Format

The result is an array with one element per branch, in order:

```json
[
  { "securityIssues": [...] },
  { "performanceMetrics": {...} },
  { "styleViolations": [...] }
]
```

### Example

```json
{
  "ComprehensiveReview": {
    "Type": "Parallel",
    "Comment": "Run multiple review types simultaneously",
    "Branches": [
      {
        "StartAt": "SecurityReview",
        "States": {
          "SecurityReview": {
            "Type": "Task",
            "Agent": "SecurityAnalyzer",
            "End": true
          }
        }
      },
      {
        "StartAt": "PerformanceReview",
        "States": {
          "PerformanceReview": {
            "Type": "Task",
            "Agent": "PerformanceAnalyzer",
            "End": true
          }
        }
      },
      {
        "StartAt": "StyleReview",
        "States": {
          "StyleReview": {
            "Type": "Task",
            "Agent": "StyleChecker",
            "End": true
          }
        }
      }
    ],
    "ResultPath": "$.reviews",
    "Next": "CombineResults"
  }
}
```

---

## Pass State

The Pass state passes input to output, optionally transforming data.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Pass"` | Yes | State type |
| `Result` | any | No | Static result value |
| `ResultPath` | string | No | Where to store result |
| `Parameters` | object | No | Dynamic parameters |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "InitializeState": {
    "Type": "Pass",
    "Comment": "Set up initial state",
    "Parameters": {
      "requestId.$": "States.UUID()",
      "timestamp.$": "$$.State.EnteredTime",
      "input.$": "$",
      "config": {
        "maxRetries": 3,
        "timeout": 60
      }
    },
    "ResultPath": "$.metadata",
    "Next": "Process"
  }
}
```

---

## Wait State

The Wait state pauses execution for a specified duration.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Wait"` | Yes | State type |
| `Seconds` | integer | No* | Wait duration |
| `Timestamp` | string | No* | ISO 8601 timestamp |
| `SecondsPath` | string | No* | JSONPath to seconds |
| `TimestampPath` | string | No* | JSONPath to timestamp |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

*One of `Seconds`, `Timestamp`, `SecondsPath`, or `TimestampPath` is required.

### Example

```json
{
  "WaitForRateLimit": {
    "Type": "Wait",
    "Seconds": 60,
    "Next": "RetryRequest"
  }
}
```

---

## Succeed State

The Succeed state terminates execution successfully.

### Example

```json
{
  "WorkflowComplete": {
    "Type": "Succeed",
    "Comment": "Workflow completed successfully"
  }
}
```

---

## Fail State

The Fail state terminates execution with failure.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Fail"` | Yes | State type |
| `Error` | string | No | Error code |
| `Cause` | string | No | Error message |

### Example

```json
{
  "ValidationFailed": {
    "Type": "Fail",
    "Error": "ValidationError",
    "Cause": "Input data failed validation checks"
  }
}
```

---

## Approval State (Agent-Native)

The Approval state pauses execution for human approval.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Approval"` | Yes | State type |
| `Prompt` | string | Yes | Message for approver |
| `Options` | array | No | Available choices |
| `Timeout` | string | No | Maximum wait time |
| `Escalation` | object | No | Escalation rules |
| `ResultPath` | string | No | Where to store decision |
| `Choices` | array | No | Route based on decision |
| `Default` | string | No | Default if timeout |
| `Next` | string | No | Next state (if no Choices) |

### Example

See section 6 for detailed examples.

---

## Debate State (Agent-Native)

The Debate state facilitates multi-agent deliberation.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Debate"` | Yes | State type |
| `Agents` | array | Yes | Participating agents |
| `Topic` | string | No | Static topic |
| `TopicPath` | string | No | JSONPath to topic |
| `Rounds` | integer | No | Number of rounds |
| `Communication` | object | No | Communication style |
| `Consensus` | object | No | Consensus requirements |
| `ResultPath` | string | No | Where to store outcome |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

See section 6 for detailed examples.

---

## Checkpoint State (Agent-Native)

The Checkpoint state creates a resumable save point.

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Checkpoint"` | Yes | State type |
| `Name` | string | Yes | Checkpoint identifier |
| `Storage` | string | No | Storage backend |
| `TTL` | string | No | Retention period |
| `Next` | string | No* | Next state |
| `End` | boolean | No* | Terminal state |

### Example

```json
{
  "SaveProgress": {
    "Type": "Checkpoint",
    "Name": "after-expensive-computation",
    "TTL": "7d",
    "Next": "ContinueProcessing"
  }
}
```
