# Getting Started with Agent State Language

This guide will help you get up and running with Agent State Language (ASL) in just a few minutes.

## Prerequisites

- PHP 8.1 or higher
- Composer
- An AI provider (Claude, OpenAI, etc.) with API access

## Installation

Install ASL via Composer:

```bash
composer require agent-state-language/asl
```

## Your First Workflow

Let's create a simple workflow that uses an agent to analyze text sentiment.

### Step 1: Define the Workflow

Create a file called `sentiment.asl.json`:

```json
{
  "Comment": "Analyze text sentiment",
  "StartAt": "AnalyzeSentiment",
  "States": {
    "AnalyzeSentiment": {
      "Type": "Task",
      "Agent": "SentimentAnalyzer",
      "Parameters": {
        "text.$": "$.inputText"
      },
      "ResultPath": "$.sentiment",
      "Next": "FormatResponse"
    },
    "FormatResponse": {
      "Type": "Pass",
      "Parameters": {
        "analysis.$": "$.sentiment",
        "originalText.$": "$.inputText"
      },
      "End": true
    }
  }
}
```

### Step 2: Create an Agent

Create a simple sentiment analyzer agent:

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class SentimentAnalyzer implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $text = $parameters['text'] ?? '';
        
        // In a real implementation, you'd call an LLM here
        $positiveWords = ['good', 'great', 'excellent', 'happy', 'love'];
        $negativeWords = ['bad', 'terrible', 'hate', 'sad', 'awful'];
        
        $textLower = strtolower($text);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($textLower, $word);
        }
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($textLower, $word);
        }
        
        if ($positiveCount > $negativeCount) {
            return ['sentiment' => 'positive', 'confidence' => 0.8];
        } elseif ($negativeCount > $positiveCount) {
            return ['sentiment' => 'negative', 'confidence' => 0.8];
        }
        
        return ['sentiment' => 'neutral', 'confidence' => 0.6];
    }
    
    public function getName(): string
    {
        return 'SentimentAnalyzer';
    }
}
```

### Step 3: Run the Workflow

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Create agent registry and register your agent
$registry = new AgentRegistry();
$registry->register('SentimentAnalyzer', new SentimentAnalyzer());

// Load and run the workflow
$engine = WorkflowEngine::fromFile('sentiment.asl.json', $registry);

$result = $engine->run([
    'inputText' => 'I love this product! It works great!'
]);

print_r($result->getOutput());
// Output: ['analysis' => ['sentiment' => 'positive', 'confidence' => 0.8], 'originalText' => '...']
```

## Understanding the Workflow

Let's break down what happened:

1. **StartAt**: The workflow begins at the `AnalyzeSentiment` state
2. **Task State**: Calls the `SentimentAnalyzer` agent with the input text
3. **ResultPath**: Stores the agent's output at `$.sentiment` in the state
4. **Next**: Transitions to the `FormatResponse` state
5. **Pass State**: Transforms the data without calling an agent
6. **End**: Marks this as a terminal state

## JSONPath Expressions

ASL uses JSONPath expressions (prefixed with `$`) to reference data:

- `$.inputText` - Access the `inputText` field from the input
- `$.sentiment.confidence` - Access nested fields
- `$` - Reference the entire state

The `.$` suffix on parameter keys indicates the value should be evaluated as a JSONPath:

```json
{
  "Parameters": {
    "staticValue": "hello",
    "dynamicValue.$": "$.someField"
  }
}
```

## Next Steps

Now that you've created your first workflow, explore these topics:

1. **[Tutorial 1: Hello World](tutorials/01-hello-world.md)** - Detailed first steps
2. **[Tutorial 2: Simple Workflow](tutorials/02-simple-workflow.md)** - Sequential states
3. **[Tutorial 3: Conditional Logic](tutorials/03-conditional-logic.md)** - Choice states

## Common Patterns

### Sequential Processing

```json
{
  "StartAt": "Step1",
  "States": {
    "Step1": { "Type": "Task", "Agent": "Agent1", "Next": "Step2" },
    "Step2": { "Type": "Task", "Agent": "Agent2", "Next": "Step3" },
    "Step3": { "Type": "Task", "Agent": "Agent3", "End": true }
  }
}
```

### Conditional Branching

```json
{
  "CheckCondition": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable": "$.score",
        "NumericGreaterThan": 80,
        "Next": "HighScore"
      }
    ],
    "Default": "LowScore"
  }
}
```

### Parallel Execution

```json
{
  "ParallelAnalysis": {
    "Type": "Parallel",
    "Branches": [
      { "StartAt": "Analyze1", "States": { ... } },
      { "StartAt": "Analyze2", "States": { ... } }
    ],
    "Next": "Combine"
  }
}
```

## Getting Help

- Read the [full specification](../SPECIFICATION.md)
- Browse the [examples](../examples/)
- Check the [reference documentation](reference/)
