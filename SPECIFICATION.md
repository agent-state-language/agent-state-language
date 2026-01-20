# Agent State Language Specification

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-20

## Abstract

Agent State Language (ASL) is a JSON-based domain-specific language for defining configurable, composable AI agent workflows. It extends the concepts of Amazon States Language with agent-native primitives for memory management, tool orchestration, human-in-the-loop patterns, multi-agent communication, cost management, and safety guardrails.

## Table of Contents

1. [Introduction](#1-introduction)
2. [Workflow Structure](#2-workflow-structure)
3. [State Types](#3-state-types)
4. [Data Flow](#4-data-flow)
5. [Agent Primitives](#5-agent-primitives)
6. [Memory and Context](#6-memory-and-context)
7. [Tools and Permissions](#7-tools-and-permissions)
8. [Human-in-the-Loop](#8-human-in-the-loop)
9. [Cost and Budget](#9-cost-and-budget)
10. [Error Handling](#10-error-handling)
11. [Streaming and Progress](#11-streaming-and-progress)
12. [Composition](#12-composition)

---

## 1. Introduction

### 1.1 Purpose

Agent State Language provides a declarative way to define AI agent workflows that are:

- **Configurable** - Workflows can be modified without code changes
- **Composable** - Complex workflows built from simple components
- **Observable** - Execution can be traced and monitored
- **Safe** - Guardrails and permissions limit agent capabilities
- **Cost-Aware** - Budget controls prevent runaway spending

### 1.2 Design Goals

1. **AWS ASL Compatibility** - Core state types match Amazon States Language
2. **Agent-Native** - First-class support for LLM-specific concerns
3. **Language Agnostic** - JSON specification implementable in any language
4. **Extensible** - Custom state types and functions can be added

### 1.3 Terminology

| Term | Definition |
|------|------------|
| **Workflow** | A complete ASL document defining a state machine |
| **State** | A single step in the workflow |
| **Agent** | An AI model or service that processes input and produces output |
| **Tool** | A capability an agent can invoke (e.g., web search, file read) |
| **Context** | Information passed to an agent for processing |
| **Memory** | Persistent storage accessible across workflow executions |

---

## 2. Workflow Structure

### 2.1 Top-Level Fields

A workflow document is a JSON object with the following structure:

```json
{
  "Comment": "Optional description of the workflow",
  "StartAt": "FirstStateName",
  "States": {
    "FirstStateName": { ... },
    "SecondStateName": { ... }
  },
  "Version": "1.0",
  "Budget": { ... },
  "Checkpointing": { ... },
  "Imports": { ... }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Comment` | string | No | Human-readable workflow description |
| `StartAt` | string | Yes | Name of the first state to execute |
| `States` | object | Yes | Map of state names to state definitions |
| `Version` | string | No | ASL version (default: "1.0") |
| `Budget` | object | No | Workflow-level cost constraints |
| `Checkpointing` | object | No | Checkpoint configuration |
| `Imports` | object | No | External workflow templates |

### 2.2 State Names

State names:
- Must be unique within the workflow
- Must be non-empty strings
- Should be descriptive of the state's purpose
- Are case-sensitive

### 2.3 Execution Flow

1. Execution begins at the state named in `StartAt`
2. Each state executes and transitions to the next state
3. Execution ends when a state has `"End": true` or is type `Succeed`/`Fail`

---

## 3. State Types

### 3.1 Core State Types

#### Task State

Executes an agent with parameters.

```json
{
  "Type": "Task",
  "Agent": "AgentName",
  "Parameters": { ... },
  "ResultPath": "$.result",
  "Next": "NextState"
}
```

#### Choice State

Conditional branching based on input data.

```json
{
  "Type": "Choice",
  "Choices": [
    {
      "Variable": "$.score",
      "NumericGreaterThan": 80,
      "Next": "HighScore"
    }
  ],
  "Default": "DefaultState"
}
```

#### Map State

Iterates over an array, executing states for each element.

```json
{
  "Type": "Map",
  "ItemsPath": "$.items",
  "Iterator": {
    "StartAt": "ProcessItem",
    "States": { ... }
  },
  "MaxConcurrency": 3,
  "Next": "Done"
}
```

#### Parallel State

Executes multiple branches concurrently.

```json
{
  "Type": "Parallel",
  "Branches": [
    { "StartAt": "Branch1", "States": { ... } },
    { "StartAt": "Branch2", "States": { ... } }
  ],
  "Next": "Combine"
}
```

#### Pass State

Passes input to output, optionally transforming data.

```json
{
  "Type": "Pass",
  "Result": { "status": "ready" },
  "ResultPath": "$.setup",
  "Next": "Process"
}
```

#### Wait State

Pauses execution for a specified duration.

```json
{
  "Type": "Wait",
  "Seconds": 30,
  "Next": "Continue"
}
```

#### Succeed State

Terminates execution successfully.

```json
{
  "Type": "Succeed"
}
```

#### Fail State

Terminates execution with failure.

```json
{
  "Type": "Fail",
  "Error": "ValidationError",
  "Cause": "Input validation failed"
}
```

### 3.2 Agent-Native State Types

#### Approval State

Pauses for human approval.

```json
{
  "Type": "Approval",
  "Prompt": "Review the changes",
  "Options": ["approve", "reject", "modify"],
  "Timeout": "24h",
  "Next": "Continue"
}
```

#### Debate State

Facilitates multi-agent deliberation.

```json
{
  "Type": "Debate",
  "Agents": ["Agent1", "Agent2", "Judge"],
  "Topic.$": "$.question",
  "Rounds": 3,
  "Consensus": { "Required": true },
  "Next": "ProcessResult"
}
```

#### Checkpoint State

Creates a resumable save point.

```json
{
  "Type": "Checkpoint",
  "Name": "after-analysis",
  "TTL": "7d",
  "Next": "Continue"
}
```

---

## 4. Data Flow

### 4.1 JSONPath

ASL uses JSONPath expressions to reference data. The `$` symbol represents the root of the current state's input.

| Expression | Description |
|------------|-------------|
| `$` | Entire input |
| `$.field` | Field named "field" |
| `$.nested.field` | Nested field |
| `$.array[0]` | First array element |
| `$.array[*]` | All array elements |

### 4.2 Path Operators

| Operator | Purpose |
|----------|---------|
| `InputPath` | Filter input before processing |
| `Parameters` | Create new input object |
| `ResultPath` | Where to store result |
| `ResultSelector` | Transform result before storing |
| `OutputPath` | Filter output after processing |

### 4.3 Context Object

The `$$` prefix accesses the execution context:

| Path | Description |
|------|-------------|
| `$$.Execution.Id` | Unique execution ID |
| `$$.State.Name` | Current state name |
| `$$.State.EnteredTime` | State entry timestamp |
| `$$.State.RetryCount` | Current retry count |
| `$$.Map.Item.Index` | Current Map index |
| `$$.Map.Item.Value` | Current Map item |

---

## 5. Agent Primitives

### 5.1 Agent Field

The `Agent` field specifies which registered agent to execute:

```json
{
  "Type": "Task",
  "Agent": "CodeAnalyzer"
}
```

### 5.2 Parameters

Parameters passed to the agent, with JSONPath interpolation:

```json
{
  "Parameters": {
    "prompt.$": "$.userInput",
    "temperature": 0.7,
    "maxTokens": 1000
  }
}
```

### 5.3 Reasoning Block

Require and capture reasoning traces:

```json
{
  "Reasoning": {
    "Required": true,
    "Format": "chain_of_thought",
    "MinSteps": 3,
    "Store": "$.reasoningTrace"
  }
}
```

---

## 6. Memory and Context

### 6.1 Memory Block

Read and write persistent memory:

```json
{
  "Memory": {
    "Read": ["user_preferences", "past_interactions"],
    "Write": {
      "Key": "analysis_result",
      "TTL": "7d"
    }
  }
}
```

### 6.2 Context Block

Configure context window management:

```json
{
  "Context": {
    "Strategy": "sliding_window",
    "MaxTokens": 8000,
    "Priority": ["$.currentTask", "$.history"],
    "Summarize": {
      "When": "TokensExceed",
      "Using": "SummarizerAgent"
    }
  }
}
```

### 6.3 Context Strategies

| Strategy | Description |
|----------|-------------|
| `sliding_window` | Keep most recent context within token limit |
| `priority_based` | Include high-priority items first |
| `semantic` | Select most relevant context via embedding similarity |

---

## 7. Tools and Permissions

### 7.1 Tools Block

Define which tools an agent can use:

```json
{
  "Tools": {
    "Allowed": ["web_search", "read_file"],
    "Denied": ["write_file", "execute_shell"],
    "RateLimits": {
      "web_search": { "MaxPerMinute": 10 }
    },
    "Sandboxed": true
  }
}
```

### 7.2 Permission Model

- **Allowed**: Explicit list of permitted tools
- **Denied**: Explicit list of forbidden tools
- **Sandboxed**: Run tool calls in isolation

### 7.3 Rate Limits

```json
{
  "RateLimits": {
    "tool_name": {
      "MaxPerMinute": 10,
      "MaxPerHour": 100,
      "MaxConcurrent": 3
    }
  }
}
```

---

## 8. Human-in-the-Loop

### 8.1 Approval State

```json
{
  "Type": "Approval",
  "Prompt": "Review proposed changes",
  "Options": ["approve", "reject", "modify"],
  "Timeout": "48h",
  "Escalation": {
    "After": "24h",
    "Notify": ["manager@example.com"]
  },
  "Choices": [
    { "Variable": "$.approval", "StringEquals": "approve", "Next": "Apply" }
  ],
  "Default": "Cancelled"
}
```

### 8.2 Feedback Collection

```json
{
  "Type": "Task",
  "Agent": "Generator",
  "Feedback": {
    "Collect": true,
    "Source": "human_rating",
    "Scale": [1, 5],
    "Store": "feedback_store"
  }
}
```

---

## 9. Cost and Budget

### 9.1 Workflow Budget

```json
{
  "Budget": {
    "MaxTokens": 100000,
    "MaxCost": "$5.00",
    "OnExceed": "PauseAndNotify",
    "Fallback": {
      "Agent": "CheaperAgent",
      "When": "BudgetAt80Percent"
    }
  }
}
```

### 9.2 State-Level Budget

```json
{
  "Type": "Task",
  "Agent": "ExpensiveAgent",
  "Budget": {
    "MaxTokens": 10000,
    "Priority": "high"
  }
}
```

### 9.3 OnExceed Actions

| Action | Description |
|--------|-------------|
| `Fail` | Terminate with budget error |
| `PauseAndNotify` | Pause and wait for approval |
| `UseFallback` | Switch to fallback agent |
| `Continue` | Log warning but continue |

---

## 10. Error Handling

### 10.1 Retry

```json
{
  "Retry": [
    {
      "ErrorEquals": ["RateLimitExceeded"],
      "IntervalSeconds": 30,
      "MaxAttempts": 3,
      "BackoffRate": 2.0
    },
    {
      "ErrorEquals": ["States.Timeout"],
      "MaxAttempts": 2
    }
  ]
}
```

### 10.2 Catch

```json
{
  "Catch": [
    {
      "ErrorEquals": ["ValidationError"],
      "ResultPath": "$.error",
      "Next": "HandleValidationError"
    },
    {
      "ErrorEquals": ["States.ALL"],
      "Next": "CatchAll"
    }
  ]
}
```

### 10.3 Predefined Errors

| Error | Description |
|-------|-------------|
| `States.ALL` | Matches any error |
| `States.Timeout` | Execution timeout |
| `States.TaskFailed` | Task execution failed |
| `States.Permissions` | Permission denied |
| `States.BudgetExceeded` | Cost budget exceeded |

---

## 11. Streaming and Progress

### 11.1 Streaming Block

```json
{
  "Streaming": {
    "Enabled": true,
    "ProgressPath": "$.progress",
    "Milestones": [
      { "At": 25, "Emit": "quarter_complete" },
      { "At": 50, "Emit": "half_complete" },
      { "At": 75, "Emit": "three_quarters" }
    ]
  }
}
```

### 11.2 Heartbeat

```json
{
  "TimeoutSeconds": 300,
  "HeartbeatSeconds": 30
}
```

---

## 12. Composition

### 12.1 Imports

```json
{
  "Imports": {
    "clarification": "./templates/clarification.asl.json",
    "validation": "./templates/validation.asl.json"
  },
  "States": {
    "AskQuestions": {
      "Type": "Include",
      "Template": "clarification",
      "Parameters": {
        "maxQuestions": 5
      },
      "Next": "Continue"
    }
  }
}
```

### 12.2 Template Parameters

Templates can accept parameters for customization:

```json
{
  "Type": "Include",
  "Template": "generic_workflow",
  "Parameters": {
    "agent": "CustomAgent",
    "retries": 3,
    "timeout": 60
  }
}
```

---

## Appendix A: Complete Example

```json
{
  "Comment": "Code Review Workflow",
  "Version": "1.0",
  "StartAt": "AnalyzeCode",
  "Budget": {
    "MaxCost": "$2.00"
  },
  "States": {
    "AnalyzeCode": {
      "Type": "Task",
      "Agent": "CodeAnalyzer",
      "Parameters": {
        "code.$": "$.sourceCode",
        "language.$": "$.language"
      },
      "Tools": {
        "Allowed": ["read_file", "grep"]
      },
      "ResultPath": "$.analysis",
      "Next": "CheckSeverity"
    },
    "CheckSeverity": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.analysis.severity",
          "StringEquals": "critical",
          "Next": "RequireApproval"
        }
      ],
      "Default": "AutoApprove"
    },
    "RequireApproval": {
      "Type": "Approval",
      "Prompt": "Critical issues found. Review required.",
      "Timeout": "48h",
      "Next": "Complete"
    },
    "AutoApprove": {
      "Type": "Pass",
      "Result": { "approved": true },
      "Next": "Complete"
    },
    "Complete": {
      "Type": "Succeed"
    }
  }
}
```

---

## Appendix B: Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-20 | Initial specification |
