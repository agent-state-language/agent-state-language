# Task Breakdown Example

A recursive task breakdown workflow that decomposes high-level project goals into atomic, actionable subtasks.

## Features

- **Clarification Questions** - Asks for project context before breaking down
- **Recursive Breakdown** - Iteratively breaks down complex tasks
- **Validation** - Checks if tasks are atomic (small enough to be actionable)
- **Depth Limiting** - Prevents infinite recursion (configurable max depth)
- **Web Research** - Can search for unfamiliar technologies
- **Concurrent Processing** - Processes multiple tasks in parallel

## Workflow Diagram

```
GetProjectGoal → AskClarifyingQuestions → WaitForAnswers
                         ↓
                  InitialBreakdown
                         ↓
              ProcessTaskBatch (Map)
                    ↙    ↓    ↘
           Validate  Validate  Validate
              ↓         ↓         ↓
           Atomic?  Atomic?   Atomic?
           ↙   ↘   ↙   ↘   ↙   ↘
         Done  Breakdown ...  ...
                         ↓
              CollectResults → CheckForMoreWork
                                    ↓
              ┌─────────────────────┴─────────────────────┐
         Has pending tasks               All tasks atomic
              ↓                                 ↓
    PrepareNextIteration               FinalizeBreakdown
              ↓                                 ↓
         (loop back)                      FormatOutput
```

## Quick Start

```bash
# From the examples/task-breakdown directory
php run.php
```

## Expected Output

```
=== Task Breakdown Workflow Example ===

Test 1: REST API Project
------------------------------------------------------------
Goal: Build a REST API with user authentication
Summary: Broke down "Build a REST API with user authentication" into 12 actionable tasks, estimated 38 hours total

Tasks:
1. Set up project structure (2h)
2. Create user model (1h)
3. Implement registration endpoint (2h)
4. Implement login endpoint (2h)
5. Add JWT token generation (2h)
6. Create auth middleware (1h)
...

Statistics:
  Total Tasks: 12
  Max Depth: 2

Test 2: Website Project
------------------------------------------------------------
Goal: Create a company website with blog
Summary: Broke down "Create a company website with blog" into 5 actionable tasks, estimated 33 hours total

Tasks:
1. Create wireframes
2. Set up frontend framework
3. Implement pages
4. Add styling
5. Deploy to production

=== Task Breakdown Workflow Complete ===
```

## Using in Your Project

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register agents
$registry = new AgentRegistry();
$registry->register('ClarifierAgent', new YourClarifierAgent());
$registry->register('BreakdownAgent', new YourBreakdownAgent());
$registry->register('ValidatorAgent', new YourValidatorAgent());
$registry->register('ResultCollector', new YourResultCollectorAgent());
$registry->register('FinalizerAgent', new YourFinalizerAgent());

// Load workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

// Run
$result = $engine->run([
    'goal' => 'Build a REST API with user authentication',
    'projectContext' => [
        'language' => 'PHP',
        'framework' => 'Laravel',
        'team_size' => 2
    ]
]);

// Output
if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "Goal: " . $output['goal'] . "\n\n";
    echo "Tasks:\n";
    foreach ($output['tasks'] as $i => $task) {
        echo ($i + 1) . ". " . $task['title'] . " ({$task['estimatedHours']}h)\n";
    }
    echo "\nTotal: {$output['statistics']['totalTasks']} tasks\n";
}
```

## Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `goal` | string | Yes | High-level project goal to break down |
| `projectContext` | object | No | Context about the project |
| `projectContext.language` | string | No | Programming language |
| `projectContext.framework` | string | No | Framework being used |
| `projectContext.team_size` | number | No | Number of team members |

## Workflow Configuration

### Budget Limits

```json
{
  "Budget": {
    "MaxCost": "$5.00",
    "MaxTokens": 50000,
    "OnExceed": "PauseAndNotify"
  }
}
```

### Recursion Control

```json
{
  "maxDepth": 5
}
```

### Tool Access

```json
{
  "Tools": {
    "Allowed": ["web_search", "fetch_webpage"],
    "RateLimits": {
      "web_search": { "MaxPerMinute": 5 }
    }
  }
}
```

## Agents Required

| Agent | Purpose | Input | Output |
|-------|---------|-------|--------|
| ClarifierAgent | Generate clarifying questions | `goal`, `projectContext` | `{ hasQuestions, questions }` |
| BreakdownAgent | Break tasks into subtasks | `goal`, `projectContext`, `parentTask` | `[{ id, title, complexity }]` |
| ValidatorAgent | Check if task is atomic | `task`, `depth` | `{ isAtomic, confidence }` |
| ResultCollector | Collect and organize results | `processedTasks` | `{ completedTasks, pendingTasks }` |
| FinalizerAgent | Format final output | `allTasks`, `goal` | `{ organizedTasks, summary }` |

## Task Validation Criteria

A task is considered "atomic" when:

| Criterion | Threshold |
|-----------|-----------|
| Complexity | Low |
| Estimated Hours | ≤ 2 hours |
| Depth | ≥ Max depth |

## Map State Iterator

The workflow uses a Map state to process tasks in parallel:

```json
{
  "Type": "Map",
  "ItemsPath": "$.tasks",
  "MaxConcurrency": 3,
  "ItemSelector": {
    "task.$": "$$.Map.Item.Value",
    "taskIndex.$": "$$.Map.Item.Index",
    "depth.$": "$.currentDepth"
  },
  "Iterator": {
    "StartAt": "ValidateTask",
    "States": { ... }
  }
}
```

## Output Format

```php
[
    'goal' => 'Original project goal',
    'tasks' => [
        [
            'id' => 'task_1',
            'title' => 'Task title',
            'description' => 'What needs to be done',
            'complexity' => 'low|medium|high',
            'estimatedHours' => 2,
            'depth' => 1
        ],
        // ...
    ],
    'summary' => 'Broke down "..." into N actionable tasks',
    'statistics' => [
        'totalTasks' => 12,
        'maxDepthReached' => 2,
        'completedAt' => '2025-01-20T...'
    ]
]
```

## Files

- `workflow.asl.json` - The ASL workflow definition
- `run.php` - Example runner with mock agents
- `README.md` - This documentation

## Related

- [Tutorial 5: Recursive Workflows](../../docs/tutorials/05-recursive-workflows.md)
- [State Types Reference](../../docs/reference/state-types.md) (Map state)
- [Best Practices](../../docs/guides/best-practices.md)
