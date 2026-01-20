# Tutorial 5: Recursive Workflows

Learn how to iterate over collections and build recursive workflows with Map states.

## What You'll Learn

- Map states for array iteration
- Item selectors and context variables
- Controlling concurrency
- Nested iterations

## The Scenario

We'll build a task breakdown system that:

1. Takes a list of tasks
2. Analyzes each task
3. Breaks down complex tasks into subtasks
4. Recursively processes until all tasks are atomic

## Step 1: Basic Map State

Create `task-processor.asl.json`:

```json
{
  "Comment": "Process a list of tasks",
  "StartAt": "ProcessTasks",
  "States": {
    "ProcessTasks": {
      "Type": "Map",
      "ItemsPath": "$.tasks",
      "MaxConcurrency": 3,
      "Iterator": {
        "StartAt": "AnalyzeTask",
        "States": {
          "AnalyzeTask": {
            "Type": "Task",
            "Agent": "TaskAnalyzer",
            "End": true
          }
        }
      },
      "ResultPath": "$.processedTasks",
      "Next": "Summarize"
    },
    "Summarize": {
      "Type": "Task",
      "Agent": "Summarizer",
      "Parameters": {
        "tasks.$": "$.processedTasks"
      },
      "End": true
    }
  }
}
```

## Understanding Map States

### Basic Structure

```json
{
  "Type": "Map",
  "ItemsPath": "$.arrayField",
  "Iterator": {
    "StartAt": "FirstState",
    "States": { ... }
  },
  "ResultPath": "$.results"
}
```

### How It Works

1. `ItemsPath` specifies the array to iterate over
2. For each item, the `Iterator` workflow runs
3. Each iteration receives the item as input
4. Results are collected into an array

### Input to Each Iteration

By default, each iteration receives just the array item:

```json
// Original input
{
  "tasks": [
    { "id": 1, "name": "Task A" },
    { "id": 2, "name": "Task B" }
  ]
}

// First iteration receives
{ "id": 1, "name": "Task A" }

// Second iteration receives
{ "id": 2, "name": "Task B" }
```

## Step 2: Using ItemSelector

Pass additional context to each iteration:

```json
{
  "Type": "Map",
  "ItemsPath": "$.tasks",
  "ItemSelector": {
    "task.$": "$$.Map.Item.Value",
    "taskIndex.$": "$$.Map.Item.Index",
    "projectId.$": "$.projectId",
    "totalTasks.$": "States.ArrayLength($.tasks)"
  },
  "Iterator": {
    "StartAt": "ProcessWithContext",
    "States": {
      "ProcessWithContext": {
        "Type": "Task",
        "Agent": "TaskProcessor",
        "End": true
      }
    }
  }
}
```

### Context Variables

| Variable | Description |
|----------|-------------|
| `$$.Map.Item.Value` | Current item value |
| `$$.Map.Item.Index` | Current index (0-based) |

Each iteration now receives:

```json
{
  "task": { "id": 1, "name": "Task A" },
  "taskIndex": 0,
  "projectId": "proj-123",
  "totalTasks": 5
}
```

## Step 3: Controlling Concurrency

### Sequential Processing

```json
{
  "Type": "Map",
  "ItemsPath": "$.items",
  "MaxConcurrency": 1,
  "Iterator": { ... }
}
```

### Parallel Processing

```json
{
  "Type": "Map",
  "ItemsPath": "$.items",
  "MaxConcurrency": 10,
  "Iterator": { ... }
}
```

### Unlimited Concurrency

```json
{
  "Type": "Map",
  "ItemsPath": "$.items",
  "MaxConcurrency": 0,
  "Iterator": { ... }
}
```

## Step 4: Building the Task Breakdown System

Create `task-breakdown.asl.json`:

```json
{
  "Comment": "Recursive task breakdown system",
  "StartAt": "InitializeBreakdown",
  "States": {
    "InitializeBreakdown": {
      "Type": "Pass",
      "Parameters": {
        "tasks.$": "States.Array($.rootTask)",
        "depth": 0,
        "maxDepth": 5
      },
      "Next": "ProcessTaskBatch"
    },
    "ProcessTaskBatch": {
      "Type": "Map",
      "ItemsPath": "$.tasks",
      "MaxConcurrency": 3,
      "ItemSelector": {
        "task.$": "$$.Map.Item.Value",
        "depth.$": "$.depth",
        "maxDepth.$": "$.maxDepth"
      },
      "Iterator": {
        "StartAt": "ValidateTask",
        "States": {
          "ValidateTask": {
            "Type": "Task",
            "Agent": "TaskValidator",
            "Parameters": {
              "task.$": "$.task",
              "depth.$": "$.depth"
            },
            "ResultPath": "$.validation",
            "Next": "CheckIfAtomic"
          },
          "CheckIfAtomic": {
            "Type": "Choice",
            "Choices": [
              {
                "Variable": "$.validation.isAtomic",
                "BooleanEquals": true,
                "Next": "MarkComplete"
              },
              {
                "Variable": "$.depth",
                "NumericGreaterThanEquals": 5,
                "Next": "ForceComplete"
              }
            ],
            "Default": "BreakdownTask"
          },
          "BreakdownTask": {
            "Type": "Task",
            "Agent": "TaskBreakdown",
            "Parameters": {
              "task.$": "$.task"
            },
            "ResultPath": "$.breakdown",
            "Next": "ReturnSubtasks"
          },
          "MarkComplete": {
            "Type": "Pass",
            "Parameters": {
              "task.$": "$.task",
              "status": "atomic",
              "subtasks": []
            },
            "End": true
          },
          "ForceComplete": {
            "Type": "Pass",
            "Parameters": {
              "task.$": "$.task",
              "status": "max_depth",
              "subtasks": []
            },
            "End": true
          },
          "ReturnSubtasks": {
            "Type": "Pass",
            "Parameters": {
              "task.$": "$.task",
              "status": "broken_down",
              "subtasks.$": "$.breakdown.subtasks"
            },
            "End": true
          }
        }
      },
      "ResultPath": "$.processed",
      "Next": "CollectSubtasks"
    },
    "CollectSubtasks": {
      "Type": "Task",
      "Agent": "SubtaskCollector",
      "Parameters": {
        "processed.$": "$.processed",
        "currentDepth.$": "$.depth"
      },
      "ResultPath": "$.collection",
      "Next": "CheckForMoreWork"
    },
    "CheckForMoreWork": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.collection.hasMoreWork",
          "BooleanEquals": true,
          "Next": "PrepareNextBatch"
        }
      ],
      "Default": "FinalizeBreakdown"
    },
    "PrepareNextBatch": {
      "Type": "Pass",
      "Parameters": {
        "tasks.$": "$.collection.pendingTasks",
        "depth.$": "States.MathAdd($.depth, 1)",
        "maxDepth.$": "$.maxDepth",
        "completedTasks.$": "$.collection.completedTasks"
      },
      "Next": "ProcessTaskBatch"
    },
    "FinalizeBreakdown": {
      "Type": "Task",
      "Agent": "BreakdownFinalizer",
      "Parameters": {
        "allTasks.$": "$.collection.completedTasks"
      },
      "End": true
    }
  }
}
```

## Step 5: The Agents

### TaskValidator

```php
<?php

class TaskValidator implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $task = $parameters['task'] ?? [];
        $depth = $parameters['depth'] ?? 0;
        
        $title = $task['title'] ?? '';
        
        // Check if task is atomic (simple heuristics)
        $isAtomic = true;
        
        // Tasks with certain keywords need breakdown
        $complexKeywords = ['and', 'also', 'including', 'with'];
        foreach ($complexKeywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                $isAtomic = false;
                break;
            }
        }
        
        // Very short tasks are usually atomic
        if (strlen($title) < 30) {
            $isAtomic = true;
        }
        
        return [
            'isAtomic' => $isAtomic,
            'confidence' => $isAtomic ? 0.9 : 0.7,
            'reason' => $isAtomic ? 'Task is specific enough' : 'Task appears complex'
        ];
    }

    public function getName(): string
    {
        return 'TaskValidator';
    }
}
```

### TaskBreakdown

```php
<?php

class TaskBreakdown implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $task = $parameters['task'] ?? [];
        $title = $task['title'] ?? 'Unnamed task';
        
        // Simulate breaking down into subtasks
        $subtasks = [
            ['title' => "Research {$title}", 'id' => uniqid()],
            ['title' => "Implement {$title}", 'id' => uniqid()],
            ['title' => "Test {$title}", 'id' => uniqid()],
        ];
        
        return [
            'subtasks' => $subtasks,
            'originalTask' => $task
        ];
    }

    public function getName(): string
    {
        return 'TaskBreakdown';
    }
}
```

## Step 6: Run the Workflow

```php
<?php

$registry = new AgentRegistry();
$registry->register('TaskValidator', new TaskValidator());
$registry->register('TaskBreakdown', new TaskBreakdown());
$registry->register('SubtaskCollector', new SubtaskCollector());
$registry->register('BreakdownFinalizer', new BreakdownFinalizer());

$engine = WorkflowEngine::fromFile('task-breakdown.asl.json', $registry);

$result = $engine->run([
    'rootTask' => [
        'title' => 'Build a REST API with authentication and rate limiting',
        'id' => 'root-1'
    ]
]);

if ($result->isSuccess()) {
    $tasks = $result->getOutput()['tasks'] ?? [];
    echo "Generated " . count($tasks) . " atomic tasks\n";
    foreach ($tasks as $task) {
        echo "- " . $task['title'] . "\n";
    }
}
```

## Nested Map States

You can nest Map states for multi-dimensional iteration:

```json
{
  "ProcessCategories": {
    "Type": "Map",
    "ItemsPath": "$.categories",
    "Iterator": {
      "StartAt": "ProcessItemsInCategory",
      "States": {
        "ProcessItemsInCategory": {
          "Type": "Map",
          "ItemsPath": "$.items",
          "Iterator": {
            "StartAt": "ProcessItem",
            "States": {
              "ProcessItem": {
                "Type": "Task",
                "Agent": "ItemProcessor",
                "End": true
              }
            }
          },
          "End": true
        }
      }
    }
  }
}
```

## Error Handling in Map

### Per-Item Retry

```json
{
  "Type": "Map",
  "ItemsPath": "$.items",
  "Iterator": {
    "StartAt": "ProcessItem",
    "States": {
      "ProcessItem": {
        "Type": "Task",
        "Agent": "Processor",
        "Retry": [
          {
            "ErrorEquals": ["States.TaskFailed"],
            "MaxAttempts": 3
          }
        ],
        "Catch": [
          {
            "ErrorEquals": ["States.ALL"],
            "ResultPath": "$.error",
            "Next": "HandleItemError"
          }
        ],
        "Next": "Done"
      },
      "HandleItemError": {
        "Type": "Pass",
        "Parameters": {
          "status": "failed",
          "error.$": "$.error"
        },
        "End": true
      },
      "Done": { "Type": "Succeed" }
    }
  }
}
```

## Summary

You've learned:

- ✅ Map states for array iteration
- ✅ ItemSelector for passing context
- ✅ Controlling concurrency with MaxConcurrency
- ✅ Building recursive workflows
- ✅ Nested Map states
- ✅ Error handling within iterations

## Next Steps

- [Tutorial 6: Memory and Context](06-memory-and-context.md) - State persistence
- [Tutorial 7: Tool Orchestration](07-tool-orchestration.md) - Tool permissions
