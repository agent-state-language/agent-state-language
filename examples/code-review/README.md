# Code Review Example

A multi-agent code review workflow with parallel analysis and human approval gates.

## Features

- **Parallel Analysis** - Security, performance, style, and test coverage
- **Severity-Based Routing** - Different approval paths based on findings
- **Human Approval** - Escalation and timeout handling
- **Auto-Approval** - Low-risk changes approved automatically

## Workflow

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

## Usage

```php
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'files' => ['src/Controller.php', 'src/Service.php'],
    'diff' => '+function newFeature() { ... }'
]);
```

## Review Categories

| Reviewer | Focus |
|----------|-------|
| SecurityReviewer | SQL injection, XSS, auth issues |
| PerformanceReviewer | Complexity, memory, efficiency |
| StyleReviewer | PSR-12 compliance |
| TestReviewer | Test coverage analysis |
