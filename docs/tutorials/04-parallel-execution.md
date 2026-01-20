# Tutorial 4: Parallel Execution

Learn how to run multiple branches of your workflow simultaneously.

## What You'll Learn

- Parallel states for concurrent execution
- Aggregating results from branches
- Error handling in parallel workflows
- Use cases for parallelization

## The Scenario

We'll build a comprehensive code analysis workflow that runs multiple analyzers in parallel:

- Security scanner
- Performance analyzer
- Style checker
- Documentation validator

## Step 1: Define the Workflow

Create `parallel-analysis.asl.json`:

```json
{
  "Comment": "Parallel code analysis workflow",
  "StartAt": "PrepareAnalysis",
  "States": {
    "PrepareAnalysis": {
      "Type": "Pass",
      "Parameters": {
        "codebase.$": "$.codebase",
        "options.$": "$.options",
        "startedAt.$": "$$.State.EnteredTime"
      },
      "Next": "ParallelAnalysis"
    },
    "ParallelAnalysis": {
      "Type": "Parallel",
      "Branches": [
        {
          "StartAt": "SecurityScan",
          "States": {
            "SecurityScan": {
              "Type": "Task",
              "Agent": "SecurityScanner",
              "Parameters": {
                "codebase.$": "$.codebase",
                "depth": "full"
              },
              "End": true
            }
          }
        },
        {
          "StartAt": "PerformanceCheck",
          "States": {
            "PerformanceCheck": {
              "Type": "Task",
              "Agent": "PerformanceAnalyzer",
              "Parameters": {
                "codebase.$": "$.codebase",
                "metrics": ["memory", "cpu", "io"]
              },
              "End": true
            }
          }
        },
        {
          "StartAt": "StyleCheck",
          "States": {
            "StyleCheck": {
              "Type": "Task",
              "Agent": "StyleChecker",
              "Parameters": {
                "codebase.$": "$.codebase",
                "ruleset": "standard"
              },
              "End": true
            }
          }
        },
        {
          "StartAt": "DocCheck",
          "States": {
            "DocCheck": {
              "Type": "Task",
              "Agent": "DocValidator",
              "Parameters": {
                "codebase.$": "$.codebase"
              },
              "End": true
            }
          }
        }
      ],
      "ResultPath": "$.analysisResults",
      "Next": "AggregateResults"
    },
    "AggregateResults": {
      "Type": "Task",
      "Agent": "ResultAggregator",
      "Parameters": {
        "security.$": "$.analysisResults[0]",
        "performance.$": "$.analysisResults[1]",
        "style.$": "$.analysisResults[2]",
        "documentation.$": "$.analysisResults[3]"
      },
      "ResultPath": "$.summary",
      "Next": "FormatReport"
    },
    "FormatReport": {
      "Type": "Pass",
      "Parameters": {
        "report": {
          "summary.$": "$.summary",
          "codebase.$": "$.codebase",
          "completedAt.$": "$$.State.EnteredTime"
        }
      },
      "End": true
    }
  }
}
```

## Understanding Parallel States

### Basic Structure

```json
{
  "Type": "Parallel",
  "Branches": [
    {
      "StartAt": "FirstBranchState",
      "States": { ... }
    },
    {
      "StartAt": "SecondBranchState",
      "States": { ... }
    }
  ],
  "ResultPath": "$.results",
  "Next": "CombineResults"
}
```

### How It Works

1. All branches receive the **same input** (the current state)
2. Branches execute **concurrently**
3. Results are collected as an **array** (in branch order)
4. Workflow continues after **all branches complete**

### Result Structure

If you have 3 branches, the result is:

```json
{
  "results": [
    { "branch1": "output" },
    { "branch2": "output" },
    { "branch3": "output" }
  ]
}
```

## Step 2: Create the Agents

### SecurityScanner

```php
<?php

namespace MyOrg\CodeAnalysis;

use AgentStateLanguage\Agents\AgentInterface;

class SecurityScanner implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $codebase = $parameters['codebase'] ?? '';
        $depth = $parameters['depth'] ?? 'quick';
        
        // Simulate security scanning
        return [
            'vulnerabilities' => [
                ['severity' => 'high', 'type' => 'SQL Injection', 'file' => 'db.php'],
                ['severity' => 'medium', 'type' => 'XSS', 'file' => 'output.php']
            ],
            'score' => 72,
            'scanDepth' => $depth,
            'scannedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'SecurityScanner';
    }
}
```

### PerformanceAnalyzer

```php
<?php

class PerformanceAnalyzer implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $metrics = $parameters['metrics'] ?? ['cpu'];
        
        return [
            'metrics' => [
                'memory' => ['usage' => '45%', 'peak' => '78%'],
                'cpu' => ['avg' => '23%', 'peak' => '67%'],
                'io' => ['reads' => 1234, 'writes' => 567]
            ],
            'hotspots' => ['processData()', 'renderView()'],
            'score' => 85
        ];
    }

    public function getName(): string
    {
        return 'PerformanceAnalyzer';
    }
}
```

## Step 3: Run the Workflow

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

$registry = new AgentRegistry();
$registry->register('SecurityScanner', new SecurityScanner());
$registry->register('PerformanceAnalyzer', new PerformanceAnalyzer());
$registry->register('StyleChecker', new StyleChecker());
$registry->register('DocValidator', new DocValidator());
$registry->register('ResultAggregator', new ResultAggregator());

$engine = WorkflowEngine::fromFile('parallel-analysis.asl.json', $registry);

$result = $engine->run([
    'codebase' => '/path/to/project',
    'options' => ['verbose' => true]
]);

if ($result->isSuccess()) {
    $report = $result->getOutput()['report'];
    echo "Security Score: " . $report['summary']['securityScore'] . "\n";
    echo "Performance Score: " . $report['summary']['performanceScore'] . "\n";
    echo "Overall Grade: " . $report['summary']['grade'] . "\n";
}
```

## Multi-Step Branches

Branches can contain multiple states:

```json
{
  "Type": "Parallel",
  "Branches": [
    {
      "StartAt": "FetchData",
      "States": {
        "FetchData": {
          "Type": "Task",
          "Agent": "DataFetcher",
          "Next": "ProcessData"
        },
        "ProcessData": {
          "Type": "Task",
          "Agent": "DataProcessor",
          "Next": "ValidateData"
        },
        "ValidateData": {
          "Type": "Task",
          "Agent": "Validator",
          "End": true
        }
      }
    }
  ]
}
```

## Error Handling in Parallel

### Retry in Branches

Each branch can have its own retry logic:

```json
{
  "Type": "Parallel",
  "Branches": [
    {
      "StartAt": "UnreliableService",
      "States": {
        "UnreliableService": {
          "Type": "Task",
          "Agent": "FlakeyAPI",
          "Retry": [
            {
              "ErrorEquals": ["States.Timeout"],
              "MaxAttempts": 3,
              "IntervalSeconds": 5
            }
          ],
          "End": true
        }
      }
    }
  ]
}
```

### Catch at Parallel Level

Handle errors from any branch:

```json
{
  "Type": "Parallel",
  "Branches": [...],
  "Catch": [
    {
      "ErrorEquals": ["States.ALL"],
      "ResultPath": "$.parallelError",
      "Next": "HandleParallelFailure"
    }
  ],
  "Next": "ContinueOnSuccess"
}
```

## ResultSelector

Transform branch results before applying ResultPath:

```json
{
  "Type": "Parallel",
  "Branches": [...],
  "ResultSelector": {
    "security.$": "$[0]",
    "performance.$": "$[1]",
    "combined": {
      "allScores.$": "States.Array($[0].score, $[1].score)"
    }
  },
  "ResultPath": "$.analysis"
}
```

## Use Cases

### 1. Multiple API Calls

Fetch data from multiple services simultaneously:

```json
{
  "Branches": [
    { "StartAt": "FetchUserProfile", "States": {...} },
    { "StartAt": "FetchUserOrders", "States": {...} },
    { "StartAt": "FetchRecommendations", "States": {...} }
  ]
}
```

### 2. Multi-Model Analysis

Get perspectives from different AI models:

```json
{
  "Branches": [
    { "StartAt": "GPT4Analysis", "States": {...} },
    { "StartAt": "ClaudeAnalysis", "States": {...} },
    { "StartAt": "GeminiAnalysis", "States": {...} }
  ]
}
```

### 3. Data Enrichment

Enrich data from multiple sources:

```json
{
  "Branches": [
    { "StartAt": "GeoLookup", "States": {...} },
    { "StartAt": "CompanyInfo", "States": {...} },
    { "StartAt": "SocialProfiles", "States": {...} }
  ]
}
```

## Performance Considerations

### Branch Independence

Branches should be **independent** - they shouldn't depend on each other's results.

### Resource Usage

With concurrent execution, resource usage multiplies. Consider:

- API rate limits
- Memory consumption
- Token budgets

### Timeouts

Set appropriate timeouts for branches:

```json
{
  "SlowBranch": {
    "Type": "Task",
    "Agent": "SlowService",
    "TimeoutSeconds": 300,
    "End": true
  }
}
```

## Summary

You've learned:

- ✅ Parallel state structure and execution
- ✅ Result aggregation from branches
- ✅ Error handling with Retry and Catch
- ✅ Multi-step branches
- ✅ Common use cases for parallelization

## Next Steps

- [Tutorial 5: Recursive Workflows](05-recursive-workflows.md) - Map iterations
- [Tutorial 6: Memory and Context](06-memory-and-context.md) - State persistence
