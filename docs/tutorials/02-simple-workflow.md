# Tutorial 2: Simple Workflow

In this tutorial, you'll learn how to create multi-step workflows with data flowing between states.

## What You'll Learn

- Sequential state execution
- Data flow with ResultPath
- Pass states for data transformation
- The execution context

## The Workflow

We'll build a document processing pipeline:

1. **Extract** - Parse a document
2. **Analyze** - Analyze the content
3. **Summarize** - Generate a summary

## Step 1: Create the Agents

### ExtractorAgent

```php
<?php

namespace MyOrg\DocProcessor;

use AgentStateLanguage\Agents\AgentInterface;

class ExtractorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $document = $parameters['document'] ?? '';
        
        // Simulate extraction
        $paragraphs = array_filter(explode("\n\n", $document));
        $wordCount = str_word_count($document);
        
        return [
            'paragraphs' => array_values($paragraphs),
            'wordCount' => $wordCount,
            'extractedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ExtractorAgent';
    }
}
```

### AnalyzerAgent

```php
<?php

namespace MyOrg\DocProcessor;

use AgentStateLanguage\Agents\AgentInterface;

class AnalyzerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $paragraphs = $parameters['paragraphs'] ?? [];
        $wordCount = $parameters['wordCount'] ?? 0;
        
        // Simulate analysis
        $avgWordsPerParagraph = count($paragraphs) > 0 
            ? round($wordCount / count($paragraphs)) 
            : 0;
        
        return [
            'paragraphCount' => count($paragraphs),
            'wordCount' => $wordCount,
            'avgWordsPerParagraph' => $avgWordsPerParagraph,
            'complexity' => $wordCount > 500 ? 'high' : ($wordCount > 100 ? 'medium' : 'low')
        ];
    }

    public function getName(): string
    {
        return 'AnalyzerAgent';
    }
}
```

### SummarizerAgent

```php
<?php

namespace MyOrg\DocProcessor;

use AgentStateLanguage\Agents\AgentInterface;

class SummarizerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $paragraphs = $parameters['paragraphs'] ?? [];
        $analysis = $parameters['analysis'] ?? [];
        
        // Simulate summarization
        $firstParagraph = $paragraphs[0] ?? 'No content';
        $summary = strlen($firstParagraph) > 100 
            ? substr($firstParagraph, 0, 100) . '...'
            : $firstParagraph;
        
        return [
            'summary' => $summary,
            'stats' => $analysis,
            'generatedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'SummarizerAgent';
    }
}
```

## Step 2: Define the Workflow

Create `document-pipeline.asl.json`:

```json
{
  "Comment": "Document processing pipeline",
  "StartAt": "ExtractDocument",
  "States": {
    "ExtractDocument": {
      "Type": "Task",
      "Agent": "ExtractorAgent",
      "Parameters": {
        "document.$": "$.inputDocument"
      },
      "ResultPath": "$.extraction",
      "Next": "AnalyzeContent"
    },
    "AnalyzeContent": {
      "Type": "Task",
      "Agent": "AnalyzerAgent",
      "Parameters": {
        "paragraphs.$": "$.extraction.paragraphs",
        "wordCount.$": "$.extraction.wordCount"
      },
      "ResultPath": "$.analysis",
      "Next": "GenerateSummary"
    },
    "GenerateSummary": {
      "Type": "Task",
      "Agent": "SummarizerAgent",
      "Parameters": {
        "paragraphs.$": "$.extraction.paragraphs",
        "analysis.$": "$.analysis"
      },
      "ResultPath": "$.result",
      "Next": "FormatOutput"
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "summary.$": "$.result.summary",
        "statistics.$": "$.result.stats",
        "processedAt.$": "$.result.generatedAt"
      },
      "End": true
    }
  }
}
```

## Understanding Data Flow

### State 1: ExtractDocument

**Input:**
```json
{
  "inputDocument": "This is paragraph one.\n\nThis is paragraph two."
}
```

**After extraction (ResultPath: $.extraction):**
```json
{
  "inputDocument": "...",
  "extraction": {
    "paragraphs": ["This is paragraph one.", "This is paragraph two."],
    "wordCount": 10,
    "extractedAt": "2026-01-20T..."
  }
}
```

### State 2: AnalyzeContent

**Input (receives full state):**
```json
{
  "inputDocument": "...",
  "extraction": { ... }
}
```

**After analysis (ResultPath: $.analysis):**
```json
{
  "inputDocument": "...",
  "extraction": { ... },
  "analysis": {
    "paragraphCount": 2,
    "wordCount": 10,
    "avgWordsPerParagraph": 5,
    "complexity": "low"
  }
}
```

### State 3: GenerateSummary

Receives all accumulated data, adds result at `$.result`.

### State 4: FormatOutput

The Pass state transforms the final output:

```json
{
  "summary": "This is paragraph one...",
  "statistics": { ... },
  "processedAt": "2026-01-20T..."
}
```

## Step 3: Run the Workflow

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\DocProcessor\{ExtractorAgent, AnalyzerAgent, SummarizerAgent};

$registry = new AgentRegistry();
$registry->register('ExtractorAgent', new ExtractorAgent());
$registry->register('AnalyzerAgent', new AnalyzerAgent());
$registry->register('SummarizerAgent', new SummarizerAgent());

$engine = WorkflowEngine::fromFile('document-pipeline.asl.json', $registry);

$result = $engine->run([
    'inputDocument' => "Agent State Language is a powerful tool for defining workflows.

It enables you to create configurable, composable AI agent workflows.

This tutorial demonstrates a simple document processing pipeline."
]);

if ($result->isSuccess()) {
    echo "Summary: " . $result->getOutput()['summary'] . "\n";
    echo "Word Count: " . $result->getOutput()['statistics']['wordCount'] . "\n";
    echo "Complexity: " . $result->getOutput()['statistics']['complexity'] . "\n";
} else {
    echo "Error: " . $result->getErrorCause() . "\n";
}
```

## Key Concepts

### ResultPath

`ResultPath` determines where to store the agent's output:

| ResultPath | Effect |
|------------|--------|
| `"$.foo"` | Store at `$.foo`, preserve input |
| `"$"` | Replace entire state with result |
| `null` | Discard result, keep input unchanged |

### Parameters

Parameters let you select specific data for each agent:

```json
{
  "Parameters": {
    "paragraphs.$": "$.extraction.paragraphs",
    "wordCount.$": "$.extraction.wordCount"
  }
}
```

The agent only receives the specified fields, not the entire state.

### Pass State

Use Pass states to transform data without calling an agent:

```json
{
  "Type": "Pass",
  "Parameters": {
    "newField.$": "$.existingField",
    "staticValue": "hardcoded"
  }
}
```

## Execution Trace

The workflow result includes an execution trace:

```php
foreach ($result->getTrace() as $entry) {
    echo $entry['type'] . ': ' . ($entry['stateName'] ?? '') . "\n";
}
```

Output:
```
workflow_start:
state_enter: ExtractDocument
state_exit: ExtractDocument
state_enter: AnalyzeContent
state_exit: AnalyzeContent
state_enter: GenerateSummary
state_exit: GenerateSummary
state_enter: FormatOutput
state_exit: FormatOutput
workflow_complete:
```

## Common Patterns

### Accumulating Results

Each state adds to the state, building up a complete result:

```json
{
  "Step1": { "ResultPath": "$.step1Result", "Next": "Step2" },
  "Step2": { "ResultPath": "$.step2Result", "Next": "Step3" },
  "Step3": { "ResultPath": "$.step3Result", "End": true }
}
```

### Transforming for Next Step

Use Pass to reshape data between incompatible agents:

```json
{
  "TransformData": {
    "Type": "Pass",
    "Parameters": {
      "expectedField.$": "$.agentOutput.differentField"
    },
    "Next": "NextAgent"
  }
}
```

## Summary

You've learned:

- ✅ Sequential workflow execution
- ✅ Using ResultPath to accumulate data
- ✅ Parameter selection for agents
- ✅ Pass states for transformation
- ✅ Understanding the execution trace

## Next Steps

- [Tutorial 3: Conditional Logic](03-conditional-logic.md) - Branch based on data
- [Tutorial 4: Parallel Execution](04-parallel-execution.md) - Run states concurrently
