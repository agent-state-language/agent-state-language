# Task Breakdown Example

This example demonstrates a recursive task breakdown workflow that decomposes high-level goals into atomic, actionable tasks.

## Features

- **Clarification Questions** - Asks for project context before breaking down
- **Recursive Breakdown** - Iteratively breaks down complex tasks
- **Validation** - Checks if tasks are atomic
- **Depth Limiting** - Prevents infinite recursion
- **Web Research** - Can search for unfamiliar technologies

## Workflow Overview

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
                    (loop back or FinalizeBreakdown)
```

## Usage

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register agents
$registry = new AgentRegistry();
$registry->register('ClarifierAgent', new ClarifierAgent($llmClient));
$registry->register('BreakdownAgent', new BreakdownAgent($llmClient));
$registry->register('ValidatorAgent', new ValidatorAgent($llmClient));
$registry->register('ResultCollector', new ResultCollectorAgent());
$registry->register('FinalizerAgent', new FinalizerAgent());

// Load workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

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
        echo ($i + 1) . ". " . $task['title'] . "\n";
    }
}
```

## Agents Required

| Agent | Purpose |
|-------|---------|
| `ClarifierAgent` | Generates clarifying questions |
| `BreakdownAgent` | Breaks tasks into subtasks |
| `ValidatorAgent` | Determines if task is atomic |
| `ResultCollector` | Collects and organizes results |
| `FinalizerAgent` | Formats final output |

## Configuration

Customize the workflow by modifying:

- `maxDepth` - Maximum breakdown depth (default: 5)
- `Budget.MaxCost` - Maximum LLM spending
- `Tools.RateLimits` - Web search rate limiting
