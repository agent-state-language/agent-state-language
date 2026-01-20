# 1. Introduction

## Overview

Agent State Language (ASL) is a JSON-based domain-specific language designed specifically for defining AI agent workflows. While inspired by AWS Step Functions' Amazon States Language, ASL extends the paradigm with primitives essential for modern AI applications.

## Why Agent State Language?

### The Problem

Building AI agent workflows today typically involves:

1. **Hardcoded orchestration** - Agent flows are embedded in application code
2. **Limited visibility** - Difficult to understand and debug complex agent interactions
3. **No standardization** - Each project invents its own patterns
4. **Safety concerns** - Agents may have unrestricted tool access
5. **Cost surprises** - No budget controls for LLM usage

### The Solution

ASL addresses these challenges by providing:

- **Declarative workflows** - Define what agents should do, not how
- **Visual comprehension** - JSON structure maps to execution flow
- **Standard patterns** - Common operations have standard representations
- **Built-in guardrails** - Tool permissions and content validation
- **Cost awareness** - Token budgets and spending limits

## Core Principles

### 1. Declarative Over Imperative

Instead of writing code that orchestrates agents, you declare the workflow structure:

```json
{
  "StartAt": "Analyze",
  "States": {
    "Analyze": {
      "Type": "Task",
      "Agent": "Analyzer",
      "Next": "Decide"
    },
    "Decide": {
      "Type": "Choice",
      "Choices": [
        { "Variable": "$.severity", "StringEquals": "high", "Next": "Escalate" }
      ],
      "Default": "Complete"
    }
  }
}
```

### 2. Composition Over Complexity

Complex workflows are built from simple, reusable components:

```json
{
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "notification": "./templates/notify.asl.json"
  }
}
```

### 3. Safety By Default

Agents operate within explicit boundaries:

```json
{
  "Tools": {
    "Allowed": ["read_file"],
    "Denied": ["execute_shell"],
    "Sandboxed": true
  }
}
```

### 4. Observable Execution

Every execution produces traces that can be analyzed:

```json
{
  "Reasoning": {
    "Required": true,
    "Store": "$.trace"
  }
}
```

## Relationship to Amazon States Language

ASL maintains compatibility with core AWS ASL concepts:

| AWS ASL Concept | ASL Support |
|-----------------|-------------|
| Task states | ✅ Fully compatible |
| Choice states | ✅ Fully compatible |
| Map states | ✅ Fully compatible |
| Parallel states | ✅ Fully compatible |
| Pass states | ✅ Fully compatible |
| Wait states | ✅ Fully compatible |
| Succeed/Fail | ✅ Fully compatible |
| Retry/Catch | ✅ Fully compatible |
| JSONPath | ✅ Fully compatible |

ASL adds agent-specific extensions:

| ASL Extension | Purpose |
|---------------|---------|
| Agent field | Specify which agent to use |
| Memory block | Persistent storage |
| Context block | Context window management |
| Tools block | Tool permissions |
| Budget block | Cost controls |
| Guardrails block | Safety validation |
| Approval state | Human-in-the-loop |
| Debate state | Multi-agent deliberation |
| Checkpoint state | Resumable execution |

## Use Cases

### Task Automation

Break down complex tasks into manageable steps:

```json
{
  "StartAt": "ParseRequest",
  "States": {
    "ParseRequest": { "Type": "Task", "Agent": "Parser", "Next": "Validate" },
    "Validate": { "Type": "Task", "Agent": "Validator", "Next": "Execute" },
    "Execute": { "Type": "Task", "Agent": "Executor", "End": true }
  }
}
```

### Code Review

Multi-agent review with human approval:

```json
{
  "StartAt": "Analyze",
  "States": {
    "Analyze": {
      "Type": "Parallel",
      "Branches": [
        { "StartAt": "SecurityReview", "States": { ... } },
        { "StartAt": "StyleReview", "States": { ... } }
      ],
      "Next": "HumanReview"
    },
    "HumanReview": {
      "Type": "Approval",
      "Next": "Merge"
    }
  }
}
```

### Research Assistant

Web research with synthesis:

```json
{
  "StartAt": "Search",
  "States": {
    "Search": {
      "Type": "Task",
      "Agent": "WebSearcher",
      "Tools": { "Allowed": ["web_search"] },
      "Next": "Synthesize"
    },
    "Synthesize": {
      "Type": "Task",
      "Agent": "Synthesizer",
      "End": true
    }
  }
}
```

### Customer Support

Intent classification and routing:

```json
{
  "StartAt": "ClassifyIntent",
  "States": {
    "ClassifyIntent": {
      "Type": "Task",
      "Agent": "IntentClassifier",
      "Next": "Route"
    },
    "Route": {
      "Type": "Choice",
      "Choices": [
        { "Variable": "$.intent", "StringEquals": "refund", "Next": "RefundFlow" },
        { "Variable": "$.intent", "StringEquals": "technical", "Next": "TechSupport" }
      ],
      "Default": "GeneralSupport"
    }
  }
}
```

## Getting Started

1. **Install** the reference implementation:
   ```bash
   composer require agent-state-language/asl
   ```

2. **Create** a workflow definition in JSON

3. **Register** your agents with the engine

4. **Run** the workflow with input data

See the [Getting Started Guide](../docs/getting-started.md) for detailed instructions.

## Specification Structure

This specification is organized into the following sections:

1. **Introduction** (this document) - Overview and motivation
2. **State Types** - All available state types
3. **Agent Primitives** - Agent-specific features
4. **Memory & Context** - State persistence and context management
5. **Tools & Permissions** - Tool access control
6. **Human-in-the-Loop** - Approval and feedback patterns
7. **Cost & Budget** - Token and cost management
8. **Error Handling** - Retry and catch mechanisms
9. **Streaming & Progress** - Real-time updates
10. **Composition** - Workflow reuse and templates
