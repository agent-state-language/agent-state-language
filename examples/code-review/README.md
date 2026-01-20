# Code Review Example

A multi-agent code review workflow with parallel analysis and human approval gates.

## Features

- **Parallel Analysis** - Security, performance, style, and test coverage reviews run simultaneously
- **Severity-Based Routing** - Different approval paths based on findings
- **Human Approval** - Escalation and timeout handling for critical issues
- **Auto-Approval** - Low-risk changes with passing checks approved automatically

## Workflow Diagram

```
LoadCode → ParallelReview → AggregateReviews → DetermineApprovalPath
                |                                    ↓
     ┌──────────┼──────────┐            ┌───────────┼───────────┐
   Security  Performance  Style       Critical   High   Low/Auto
     ↓           ↓          ↓             ↓        ↓       ↓
   [Reviews merged]              SeniorReview  LeadReview  AutoApprove
                                      ↓           ↓          ↓
                               ProcessApproval  →  →  →  FinalizeReview
```

## Quick Start

```bash
# From the examples/code-review directory
php run.php
```

## Expected Output

```
=== Code Review Workflow Example ===

Test 1: Clean Code (Auto-Approve)
----------------------------------------
Decision: auto_approved
Severity: low
Issues: 0

Test 2: Code with Security Issues
----------------------------------------
Decision: changes_requested
Severity: critical
Issues: 2
Summary: Found 2 issues: 2 security, 0 performance, 0 style, 1 test coverage

Test 3: Performance Issues
----------------------------------------
Decision: approved
Severity: medium
Issues: 1
```

## Using in Your Project

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register your agents
$registry = new AgentRegistry();
$registry->register('CodeLoader', new YourCodeLoaderAgent());
$registry->register('SecurityReviewer', new YourSecurityReviewerAgent());
$registry->register('PerformanceReviewer', new YourPerformanceReviewerAgent());
$registry->register('StyleReviewer', new YourStyleReviewerAgent());
$registry->register('TestReviewer', new YourTestReviewerAgent());
$registry->register('ReviewAggregator', new YourReviewAggregatorAgent());
$registry->register('ReviewFinalizer', new YourReviewFinalizerAgent());

// Load and run the workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'files' => ['src/Controller.php', 'src/Service.php'],
    'diff' => '+function newFeature() { ... }'
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "Review Decision: " . $output['decision'] . "\n";
    echo "Total Issues: " . $output['totalIssues'] . "\n";
}
```

## Review Categories

| Reviewer | Focus | Severity Range |
|----------|-------|----------------|
| SecurityReviewer | SQL injection, XSS, auth issues, sensitive data | critical, high |
| PerformanceReviewer | Complexity, memory, N+1 queries, efficiency | medium, low |
| StyleReviewer | PSR-12 compliance, formatting | low |
| TestReviewer | Test coverage analysis | medium, low |

## Approval Paths

| Severity | Approval Required | Timeout |
|----------|-------------------|---------|
| Critical | Senior Engineer | 48h (escalates at 24h) |
| High | Team Lead | 24h |
| Medium | Standard Review | 8h |
| Low + All Passing | Auto-Approved | None |

## Configuration

### Budget Limits

The workflow includes a $3.00 budget limit with fallback to cheaper models:

```json
{
  "Budget": {
    "MaxCost": "$3.00",
    "Fallback": {
      "When": "BudgetAt80Percent",
      "UseModel": "claude-opus-4-5"
    }
  }
}
```

### Customizing Checks

Each reviewer can be configured with specific checks:

```json
{
  "SecurityReview": {
    "Parameters": {
      "checkList": [
        "SQL injection",
        "XSS vulnerabilities",
        "Authentication issues",
        "Sensitive data exposure"
      ]
    }
  }
}
```

## Agent Interfaces

Each agent receives specific parameters and returns structured results:

### CodeLoader

**Input:**
```php
[
    'files' => ['src/Example.php'],
    'diff' => '+added code\n-removed code'
]
```

**Output:**
```php
[
    'files' => [...],
    'fileCount' => 1,
    'diff' => '...',
    'linesAdded' => 5,
    'linesRemoved' => 2
]
```

### SecurityReviewer

**Output:**
```php
[
    'category' => 'security',
    'issues' => [
        ['type' => 'xss', 'severity' => 'high', 'message' => '...']
    ],
    'passed' => false
]
```

## Files

- `workflow.asl.json` - The ASL workflow definition
- `run.php` - Example runner with mock agents
- `README.md` - This documentation

## Related

- [Tutorial 4: Parallel Execution](../../docs/tutorials/04-parallel-execution.md)
- [Tutorial 8: Human Approval](../../docs/tutorials/08-human-approval.md)
- [Best Practices Guide](../../docs/guides/best-practices.md)
