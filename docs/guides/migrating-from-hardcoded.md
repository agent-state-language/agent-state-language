# Migrating from Hardcoded Workflows

This guide helps you convert hardcoded PHP agent workflows to ASL.

## Before: Hardcoded Workflow

```php
<?php

class TaskBreakdownWorkflow
{
    public function run(string $goal): array
    {
        // Step 1: Get clarification
        $questions = $this->clarifier->askQuestions($goal);
        $answers = $this->getAnswers($questions);
        
        // Step 2: Initial breakdown
        $tasks = $this->breakdownAgent->breakdown($goal, $answers);
        
        // Step 3: Validate and recurse
        $result = [];
        foreach ($tasks as $task) {
            if ($this->validator->isAtomic($task)) {
                $result[] = $task;
            } else {
                $subtasks = $this->breakdownAgent->breakdown($task);
                $result = array_merge($result, $subtasks);
            }
        }
        
        return $result;
    }
}
```

## After: ASL Workflow

```json
{
  "Comment": "Task Breakdown Workflow",
  "StartAt": "AskClarifyingQuestions",
  "States": {
    "AskClarifyingQuestions": {
      "Type": "Task",
      "Agent": "ClarifierAgent",
      "Parameters": { "goal.$": "$.goal" },
      "ResultPath": "$.clarification",
      "Next": "InitialBreakdown"
    },
    "InitialBreakdown": {
      "Type": "Task",
      "Agent": "BreakdownAgent",
      "Parameters": {
        "goal.$": "$.goal",
        "context.$": "$.clarification"
      },
      "ResultPath": "$.tasks",
      "Next": "ProcessTasks"
    },
    "ProcessTasks": {
      "Type": "Map",
      "ItemsPath": "$.tasks",
      "Iterator": {
        "StartAt": "ValidateTask",
        "States": {
          "ValidateTask": {
            "Type": "Task",
            "Agent": "ValidatorAgent",
            "ResultPath": "$.validation",
            "Next": "CheckAtomic"
          },
          "CheckAtomic": {
            "Type": "Choice",
            "Choices": [
              {
                "Variable": "$.validation.isAtomic",
                "BooleanEquals": true,
                "Next": "MarkComplete"
              }
            ],
            "Default": "BreakdownFurther"
          },
          "BreakdownFurther": {
            "Type": "Task",
            "Agent": "BreakdownAgent",
            "End": true
          },
          "MarkComplete": {
            "Type": "Pass",
            "End": true
          }
        }
      },
      "End": true
    }
  }
}
```

## Migration Steps

### 1. Identify Agents

Extract each distinct operation into an agent class:

| Hardcoded Method | ASL Agent |
|-----------------|-----------|
| `$this->clarifier->askQuestions()` | `ClarifierAgent` |
| `$this->breakdownAgent->breakdown()` | `BreakdownAgent` |
| `$this->validator->isAtomic()` | `ValidatorAgent` |

### 2. Map Control Flow

| PHP Construct | ASL State |
|---------------|-----------|
| `if/else` | `Choice` |
| `foreach` | `Map` |
| Parallel tasks | `Parallel` |
| Sequential calls | `Task` → `Next` |

### 3. Convert Conditionals

```php
// Before
if ($score > 80) {
    return $this->highScoreHandler($data);
} else {
    return $this->lowScoreHandler($data);
}
```

```json
{
  "CheckScore": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable": "$.score",
        "NumericGreaterThan": 80,
        "Next": "HighScoreHandler"
      }
    ],
    "Default": "LowScoreHandler"
  }
}
```

### 4. Convert Loops

```php
// Before
foreach ($items as $item) {
    $results[] = $this->processor->process($item);
}
```

```json
{
  "ProcessItems": {
    "Type": "Map",
    "ItemsPath": "$.items",
    "Iterator": {
      "StartAt": "ProcessItem",
      "States": {
        "ProcessItem": {
          "Type": "Task",
          "Agent": "ProcessorAgent",
          "End": true
        }
      }
    }
  }
}
```

## Benefits After Migration

1. **Configurable** - Change workflow without code changes
2. **Visible** - Understand flow from JSON structure
3. **Testable** - Validate workflow structure
4. **Reusable** - Compose workflows from templates
5. **Observable** - Built-in execution traces
