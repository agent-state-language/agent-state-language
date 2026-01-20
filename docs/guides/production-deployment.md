# Production Deployment

This guide covers deploying ASL workflows in production environments.

## Pre-Deployment Checklist

- [ ] All workflows validated
- [ ] Agents tested with production configurations
- [ ] Error handling configured
- [ ] Budgets and rate limits set
- [ ] Monitoring and alerting configured
- [ ] Logging enabled

## Validation

Always validate before deployment:

```php
<?php

$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

try {
    $engine->validate();
    echo "Workflow is valid\n";
} catch (ValidationException $e) {
    echo "Validation failed:\n";
    foreach ($e->getErrors() as $error) {
        echo "- $error\n";
    }
    exit(1);
}
```

## Configuration Management

### Environment-Specific Workflows

```php
$environment = getenv('APP_ENV') ?: 'development';
$workflowFile = "workflows/{$environment}/main.asl.json";

$engine = WorkflowEngine::fromFile($workflowFile, $registry);
```

### External Configuration

```json
{
  "Budget": {
    "MaxCost.$": "env.MAX_WORKFLOW_COST",
    "MaxTokens.$": "env.MAX_WORKFLOW_TOKENS"
  }
}
```

## Monitoring

### Execution Metrics

```php
$result = $engine->run($input);

// Log metrics
$metrics->record('workflow.duration', $result->getDuration());
$metrics->record('workflow.tokens', $result->getTokensUsed());
$metrics->record('workflow.cost', $result->getCost());
$metrics->record('workflow.success', $result->isSuccess() ? 1 : 0);
```

### Execution Traces

```php
if (!$result->isSuccess()) {
    $logger->error('Workflow failed', [
        'error' => $result->getError(),
        'cause' => $result->getErrorCause(),
        'trace' => $result->getTrace()
    ]);
}
```

## Error Handling

### Global Error Handler

```json
{
  "States": {
    "MainProcess": {
      "Type": "Task",
      "Agent": "MainAgent",
      "Catch": [
        {
          "ErrorEquals": ["States.ALL"],
          "ResultPath": "$.error",
          "Next": "ErrorNotification"
        }
      ]
    },
    "ErrorNotification": {
      "Type": "Task",
      "Agent": "ErrorNotifier",
      "End": true
    }
  }
}
```

### Graceful Degradation

```json
{
  "Budget": {
    "Fallback": {
      "Cascade": [
        { "When": "BudgetAt80Percent", "UseModel": "cheaper-model" }
      ]
    }
  }
}
```

## Scaling

### Horizontal Scaling

Run multiple workflow engine instances behind a load balancer.

### Rate Limiting

```json
{
  "Tools": {
    "RateLimits": {
      "web_search": { "MaxPerMinute": 30 },
      "api_call": { "MaxPerMinute": 100 }
    }
  }
}
```

### Concurrency Control

```json
{
  "Type": "Map",
  "MaxConcurrency": 5
}
```

## Security Checklist

- [ ] Tool allowlists configured
- [ ] File system restrictions in place
- [ ] Network access limited
- [ ] Sensitive data redacted from logs
- [ ] API keys stored securely
- [ ] Sandboxing enabled for code execution

## Backup and Recovery

### Checkpoints

```json
{
  "Type": "Checkpoint",
  "Name": "after-expensive-operation",
  "TTL": "7d"
}
```

### State Persistence

Configure memory backend for production:

```json
{
  "Memory": {
    "Backend": "redis",
    "Connection": "redis://prod-redis:6379"
  }
}
```

## Pause/Resume Workflows

For human-in-the-loop workflows that pause and wait for input.

### Handling Paused Workflows

```php
<?php

$result = $engine->run($input);

if ($result->isPaused()) {
    // Save workflow state for later resumption
    $this->saveWorkflowState([
        'id' => uniqid('wf_'),
        'paused_at' => $result->getPausedAtState(),
        'checkpoint' => $result->getCheckpointData(),
        'pending' => $result->getPendingInput(),
        'created_at' => date('c'),
    ]);
    
    // Return pending status to client
    return ['status' => 'pending_approval'];
}
```

### Resuming Workflows

```php
<?php

// Load saved state
$saved = $this->loadWorkflowState($workflowId);

// Resume with human decision
$result = $engine->run(
    $saved['checkpoint'],      // Original input data
    $saved['paused_at'],       // State to resume from
    [                          // Human's decision
        'approval' => $decision,
        'approver' => $userEmail,
        'comment' => $comment,
    ]
);
```

### Production Approval Handler

```php
<?php

use AgentStateLanguage\Handlers\ApprovalHandlerInterface;

class ProductionApprovalHandler implements ApprovalHandlerInterface
{
    public function __construct(
        private PDO $db,
        private NotificationService $notifications,
        private LoggerInterface $logger
    ) {}
    
    public function requestApproval(array $request): ?array
    {
        // Log the approval request
        $this->logger->info('Approval requested', [
            'state' => $request['state'],
            'prompt' => $request['prompt'],
        ]);
        
        // Create database record
        $requestId = $this->createApprovalRequest($request);
        
        // Send notifications
        $this->notifications->sendApprovalRequest($requestId, $request);
        
        // Return null to pause workflow
        return null;
    }
    
    private function createApprovalRequest(array $request): string
    {
        $id = uniqid('apr_');
        $stmt = $this->db->prepare(
            'INSERT INTO approval_requests 
             (id, state_name, prompt, options, created_at) 
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $id,
            $request['state'],
            $request['prompt'],
            json_encode($request['options']),
        ]);
        return $id;
    }
}
```

### State Lifecycle Monitoring

Track workflow progress with lifecycle callbacks:

```php
<?php

$engine->onStateEnter(function (string $stateName, array $data) use ($metrics) {
    $metrics->increment("workflow.state.{$stateName}.entered");
    $metrics->gauge('workflow.current_state', $stateName);
});

$engine->onStateExit(function (string $stateName, mixed $output, float $duration) use ($metrics) {
    $metrics->timing("workflow.state.{$stateName}.duration", $duration);
    $metrics->increment("workflow.state.{$stateName}.completed");
});
```

## Health Checks

```php
// Workflow health check endpoint
function healthCheck(): array
{
    $registry = new AgentRegistry();
    // Register minimal test agents

    try {
        $engine = WorkflowEngine::fromFile('health-check.asl.json', $registry);
        $engine->validate();
        
        $result = $engine->run(['test' => true]);
        
        return [
            'status' => 'healthy',
            'latency' => $result->getDuration()
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
}
```
