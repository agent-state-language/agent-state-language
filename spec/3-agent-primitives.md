# 3. Agent Primitives

This section covers the agent-specific features that distinguish ASL from standard state machine languages.

## The Agent Field

The `Agent` field in a Task state specifies which registered agent to execute:

```json
{
  "Type": "Task",
  "Agent": "CodeAnalyzer"
}
```

### Agent Registration

Agents must be registered with the workflow engine before execution:

```php
$registry = new AgentRegistry();
$registry->register('CodeAnalyzer', new CodeAnalyzerAgent());
$registry->register('Summarizer', new SummarizerAgent());

$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
```

### Agent Interface

All agents must implement the `AgentInterface`:

```php
interface AgentInterface
{
    public function execute(array $parameters): array;
    public function getName(): string;
}
```

## Parameters

Parameters are passed to the agent when executed. Values ending in `.$` are evaluated as JSONPath expressions:

```json
{
  "Parameters": {
    "staticValue": "hello",
    "dynamicValue.$": "$.inputField",
    "nested": {
      "fixed": true,
      "computed.$": "$.computation.result"
    }
  }
}
```

### Parameter Resolution

1. Static values are passed as-is
2. Values ending in `.$` are resolved from the state input
3. Nested objects are processed recursively
4. Arrays are processed element by element

### Intrinsic Functions in Parameters

```json
{
  "Parameters": {
    "id.$": "States.UUID()",
    "message.$": "States.Format('Processing {}', $.itemName)",
    "count.$": "States.ArrayLength($.items)"
  }
}
```

## Reasoning Block

The Reasoning block configures how agents should explain their decisions.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Required` | boolean | Whether reasoning is mandatory |
| `Format` | string | Reasoning format |
| `MinSteps` | integer | Minimum reasoning steps |
| `MaxSteps` | integer | Maximum reasoning steps |
| `Store` | string | JSONPath to store trace |

### Formats

| Format | Description |
|--------|-------------|
| `chain_of_thought` | Step-by-step reasoning |
| `tree_of_thoughts` | Branching reasoning paths |
| `reflection` | Self-critique and refinement |
| `free_form` | Unstructured explanation |

### Example

```json
{
  "MakeDecision": {
    "Type": "Task",
    "Agent": "DecisionMaker",
    "Reasoning": {
      "Required": true,
      "Format": "chain_of_thought",
      "MinSteps": 3,
      "MaxSteps": 10,
      "Store": "$.reasoningTrace"
    },
    "Next": "ExecuteDecision"
  }
}
```

### Output Format

When reasoning is enabled, the agent output includes:

```json
{
  "result": { ... },
  "reasoning": {
    "steps": [
      { "step": 1, "thought": "First, I need to..." },
      { "step": 2, "thought": "Given that, I should..." },
      { "step": 3, "thought": "Therefore, the answer is..." }
    ],
    "conclusion": "Final decision based on reasoning"
  }
}
```

## Guardrails Block

The Guardrails block defines input and output validation rules.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Input` | object | Input validation rules |
| `Output` | object | Output validation rules |

### Input Guardrails

```json
{
  "Guardrails": {
    "Input": {
      "Validator": "InputValidatorAgent",
      "Rules": [
        { "Type": "length", "Max": 10000 },
        { "Type": "pattern", "Block": ["<script>", "javascript:"] }
      ],
      "OnFail": "reject"
    }
  }
}
```

### Output Guardrails

```json
{
  "Guardrails": {
    "Output": {
      "Validator": "ContentModerator",
      "Rules": [
        { "Type": "regex", "Pattern": "API_KEY|password", "Action": "redact" },
        { "Type": "semantic", "Check": "harmful_content", "Action": "block" },
        { "Type": "pii", "Detect": true, "Action": "mask" }
      ],
      "MaxRetries": 2,
      "OnFail": "fail_state"
    }
  }
}
```

### Rule Types

| Type | Description |
|------|-------------|
| `length` | Check content length |
| `pattern` | Pattern matching (block/allow) |
| `regex` | Regular expression matching |
| `semantic` | AI-based content analysis |
| `pii` | Personal information detection |
| `custom` | Custom validation agent |

### Actions

| Action | Description |
|--------|-------------|
| `block` | Reject the content entirely |
| `redact` | Remove matching content |
| `mask` | Replace with placeholders |
| `warn` | Log warning but allow |
| `retry` | Ask agent to regenerate |

## Audit Block

The Audit block configures execution logging and tracking.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `LogLevel` | string | Detail level for logs |
| `Redact` | array | JSONPaths to redact from logs |
| `Metrics` | array | Metrics to collect |
| `Trace` | boolean | Enable distributed tracing |

### Example

```json
{
  "ProcessSensitiveData": {
    "Type": "Task",
    "Agent": "DataProcessor",
    "Audit": {
      "LogLevel": "full",
      "Redact": ["$.credentials", "$.apiKey", "$.password"],
      "Metrics": ["latency", "tokens", "cost"],
      "Trace": true
    },
    "Next": "Continue"
  }
}
```

### Log Levels

| Level | Description |
|-------|-------------|
| `minimal` | State transitions only |
| `standard` | Transitions + basic I/O |
| `full` | Complete execution details |
| `debug` | Full + internal agent state |

## Idempotency

The idempotency configuration ensures safe retries.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Idempotent` | boolean | Mark as idempotent |
| `IdempotencyKey` | string | JSONPath to unique key |
| `CacheResult` | boolean | Cache successful results |
| `CacheTTL` | string | Cache duration |

### Example

```json
{
  "SendNotification": {
    "Type": "Task",
    "Agent": "Notifier",
    "Idempotent": true,
    "IdempotencyKey.$": "$.notificationId",
    "CacheResult": true,
    "CacheTTL": "1h",
    "Next": "Continue"
  }
}
```

## Agent Metadata

Additional metadata can be attached to agent calls:

```json
{
  "CallAgent": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Metadata": {
      "Priority": "high",
      "Tags": ["analysis", "code-review"],
      "Version": "2.0",
      "Tenant.$": "$.tenantId"
    },
    "Next": "Process"
  }
}
```

## Model Selection

For agents that can use different models:

```json
{
  "GenerateResponse": {
    "Type": "Task",
    "Agent": "Generator",
    "Model": {
      "Preferred": "claude-opus-4-5",
      "Fallback": "claude-sonnet-4-5",
      "FallbackOn": ["BudgetExceeded", "RateLimitExceeded"]
    },
    "Next": "Deliver"
  }
}
```

## Temperature and Sampling

Control generation parameters:

```json
{
  "CreativeGeneration": {
    "Type": "Task",
    "Agent": "Writer",
    "Generation": {
      "Temperature": 0.9,
      "TopP": 0.95,
      "MaxTokens": 2000,
      "StopSequences": ["\n\n---\n\n"]
    },
    "Next": "Review"
  }
}
```

## Complete Example

```json
{
  "AnalyzeAndExplain": {
    "Type": "Task",
    "Comment": "Analyze code with full reasoning and guardrails",
    "Agent": "CodeAnalyzer",
    "Parameters": {
      "code.$": "$.sourceCode",
      "language.$": "$.language",
      "focus": "security"
    },
    "Reasoning": {
      "Required": true,
      "Format": "chain_of_thought",
      "MinSteps": 3
    },
    "Guardrails": {
      "Input": {
        "Rules": [
          { "Type": "length", "Max": 50000 }
        ]
      },
      "Output": {
        "Rules": [
          { "Type": "pii", "Detect": true, "Action": "redact" }
        ]
      }
    },
    "Audit": {
      "LogLevel": "full",
      "Metrics": ["tokens", "latency"]
    },
    "Model": {
      "Preferred": "claude-opus-4-5",
      "Fallback": "claude-sonnet-4-5"
    },
    "Generation": {
      "Temperature": 0.3,
      "MaxTokens": 4000
    },
    "ResultPath": "$.analysis",
    "TimeoutSeconds": 120,
    "Next": "ProcessFindings"
  }
}
```
