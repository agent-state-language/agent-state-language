# Tutorial 1: Hello World

Welcome to Agent State Language! In this tutorial, you'll create your first ASL workflow.

## What You'll Learn

- Basic workflow structure
- Task states
- Running a workflow with PHP

## Prerequisites

- PHP 8.1 or higher
- Composer installed
- Basic PHP knowledge

## Step 1: Create Your Project

```bash
mkdir asl-hello-world
cd asl-hello-world
composer init --name=myorg/asl-hello --require=agent-state-language/asl:*
composer install
```

## Step 2: Create a Simple Agent

Create `src/GreeterAgent.php`:

```php
<?php

namespace MyOrg\ASLHello;

use AgentStateLanguage\Agents\AgentInterface;

class GreeterAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $name = $parameters['name'] ?? 'World';
        
        return [
            'greeting' => "Hello, {$name}!",
            'timestamp' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'GreeterAgent';
    }
}
```

This agent takes a `name` parameter and returns a greeting.

## Step 3: Define Your Workflow

Create `workflow.asl.json`:

```json
{
  "Comment": "A simple hello world workflow",
  "StartAt": "SayHello",
  "States": {
    "SayHello": {
      "Type": "Task",
      "Agent": "GreeterAgent",
      "Parameters": {
        "name.$": "$.userName"
      },
      "End": true
    }
  }
}
```

### Understanding the Structure

| Field | Purpose |
|-------|---------|
| `Comment` | Human-readable description |
| `StartAt` | The first state to execute |
| `States` | Map of state names to definitions |
| `Type: Task` | Execute an agent |
| `Agent` | Which agent to use |
| `Parameters` | Values to pass to the agent |
| `End: true` | This state terminates the workflow |

### Understanding Parameters

The `.$` suffix indicates JSONPath interpolation:

- `"name.$": "$.userName"` - Get `userName` from the input
- Without `.$`, the value is used literally

## Step 4: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\ASLHello\GreeterAgent;

// Create registry and register our agent
$registry = new AgentRegistry();
$registry->register('GreeterAgent', new GreeterAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

// Run with input
$result = $engine->run([
    'userName' => 'Alice'
]);

// Check result
if ($result->isSuccess()) {
    print_r($result->getOutput());
} else {
    echo "Error: " . $result->getError() . "\n";
    echo "Cause: " . $result->getErrorCause() . "\n";
}
```

## Step 5: Execute

```bash
php run.php
```

Expected output:

```
Array
(
    [greeting] => Hello, Alice!
    [timestamp] => 2026-01-20T10:30:00+00:00
)
```

## What Just Happened?

1. **Registry Setup**: We created an `AgentRegistry` and registered our `GreeterAgent`
2. **Load Workflow**: The engine loaded our JSON workflow definition
3. **Start Execution**: Execution began at `SayHello` (specified by `StartAt`)
4. **Parameter Resolution**: `$.userName` was resolved from the input to "Alice"
5. **Agent Execution**: `GreeterAgent` received `{ "name": "Alice" }` and returned the greeting
6. **Workflow End**: Since `End: true`, the workflow completed with the agent's output

## Experiment

Try these modifications:

### Add a Second State

```json
{
  "Comment": "Two-state hello workflow",
  "StartAt": "SayHello",
  "States": {
    "SayHello": {
      "Type": "Task",
      "Agent": "GreeterAgent",
      "Parameters": {
        "name.$": "$.userName"
      },
      "ResultPath": "$.greetingResult",
      "Next": "FormatOutput"
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "message.$": "$.greetingResult.greeting",
        "processedAt.$": "$.greetingResult.timestamp"
      },
      "End": true
    }
  }
}
```

### Use Static Values

```json
{
  "Parameters": {
    "name": "Static Name",
    "dynamicValue.$": "$.fromInput"
  }
}
```

## Common Mistakes

### Missing Agent Registration

```
Error: States.AgentNotFound
Cause: Agent 'GreeterAgent' is not registered
```

**Fix**: Ensure you call `$registry->register('GreeterAgent', new GreeterAgent())` before running.

### Invalid JSONPath

```
Error: States.ParameterPathFailure
```

**Fix**: Check that your `.$` paths reference fields that exist in the input.

### Missing End or Next

```
Error: States.ValidationError
Cause: State 'SayHello' must have either Next or End: true
```

**Fix**: Every non-terminal state needs `"Next": "StateName"` or `"End": true`.

## Summary

You've learned:

- ✅ Basic workflow structure
- ✅ Creating and registering agents
- ✅ Task states and parameters
- ✅ Running workflows with the engine

## Next Steps

- [Tutorial 2: Simple Workflow](02-simple-workflow.md) - Sequential states and data flow
- [Tutorial 3: Conditional Logic](03-conditional-logic.md) - Choice states
