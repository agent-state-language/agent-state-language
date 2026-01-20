# 9. Streaming and Progress

This section covers how ASL handles real-time updates, streaming responses, and progress tracking.

## Overview

Long-running agent workflows benefit from:

- **Streaming** - Real-time token output
- **Progress tracking** - Milestones and percentage complete
- **Heartbeats** - Liveness monitoring
- **Events** - State change notifications

## Streaming Block

Enable streaming for task outputs:

```json
{
  "GenerateDocument": {
    "Type": "Task",
    "Agent": "DocumentGenerator",
    "Streaming": {
      "Enabled": true
    },
    "Next": "Review"
  }
}
```

### Streaming Fields

| Field | Type | Description |
|-------|------|-------------|
| `Enabled` | boolean | Enable streaming |
| `Mode` | string | Streaming mode |
| `ChunkSize` | integer | Minimum chunk size |
| `Callbacks` | object | Stream callbacks |
| `Buffer` | object | Buffering configuration |

### Streaming Modes

| Mode | Description |
|------|-------------|
| `token` | Stream token by token |
| `line` | Stream line by line |
| `chunk` | Stream in larger chunks |
| `sentence` | Stream sentence by sentence |

```json
{
  "Streaming": {
    "Enabled": true,
    "Mode": "sentence",
    "ChunkSize": 100
  }
}
```

### Stream Callbacks

Configure handlers for stream events:

```json
{
  "Streaming": {
    "Enabled": true,
    "Callbacks": {
      "OnChunk": "StreamHandler",
      "OnComplete": "StreamComplete",
      "OnError": "StreamError"
    }
  }
}
```

### Buffering

Control stream buffering behavior:

```json
{
  "Streaming": {
    "Enabled": true,
    "Buffer": {
      "Size": 1024,
      "FlushInterval": "100ms",
      "FlushOnNewline": true
    }
  }
}
```

## Progress Block

Track execution progress through milestones:

```json
{
  "LongAnalysis": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Progress": {
      "Enabled": true,
      "Path": "$.progress"
    },
    "Next": "Report"
  }
}
```

### Progress Fields

| Field | Type | Description |
|-------|------|-------------|
| `Enabled` | boolean | Enable progress tracking |
| `Path` | string | JSONPath to store progress |
| `Milestones` | array | Named milestones |
| `Emit` | object | Event emission config |

### Milestones

Define named milestones with percentage thresholds:

```json
{
  "Progress": {
    "Enabled": true,
    "Milestones": [
      { "At": 0, "Name": "started", "Emit": "analysis_started" },
      { "At": 25, "Name": "scanning", "Emit": "scanning_complete" },
      { "At": 50, "Name": "analyzing", "Emit": "analysis_halfway" },
      { "At": 75, "Name": "synthesizing", "Emit": "synthesis_started" },
      { "At": 100, "Name": "complete", "Emit": "analysis_complete" }
    ]
  }
}
```

### Progress Object

Progress is stored in the specified path:

```json
{
  "progress": {
    "percentage": 75,
    "milestone": "synthesizing",
    "message": "Generating final report...",
    "startedAt": "2026-01-20T10:00:00Z",
    "updatedAt": "2026-01-20T10:15:00Z",
    "estimatedRemaining": "5m"
  }
}
```

### Custom Progress Updates

Agents can report custom progress:

```php
class AnalyzerAgent implements AgentInterface, ProgressAwareInterface
{
    public function execute(array $parameters): array
    {
        $this->reportProgress(10, 'Loading data...');
        // ... work ...
        $this->reportProgress(50, 'Analyzing patterns...');
        // ... more work ...
        $this->reportProgress(90, 'Generating report...');
        
        return ['result' => $analysis];
    }
}
```

## Heartbeats

Monitor long-running task liveness:

```json
{
  "BatchProcess": {
    "Type": "Task",
    "Agent": "BatchProcessor",
    "TimeoutSeconds": 3600,
    "HeartbeatSeconds": 60,
    "Next": "Complete"
  }
}
```

### Heartbeat Behavior

- Task must send heartbeat within `HeartbeatSeconds`
- Missing heartbeat triggers `States.Timeout`
- Heartbeats reset the timeout countdown

### Heartbeat with Progress

Combine heartbeats with progress updates:

```json
{
  "Progress": {
    "Enabled": true,
    "HeartbeatOnUpdate": true
  },
  "HeartbeatSeconds": 30
}
```

## Events

Emit events at various points:

### State Events

```json
{
  "ImportantStep": {
    "Type": "Task",
    "Agent": "Processor",
    "Events": {
      "OnEnter": {
        "Type": "state_entered",
        "Include": ["$.taskId", "$.timestamp"]
      },
      "OnExit": {
        "Type": "state_completed",
        "Include": ["$.result", "$.duration"]
      },
      "OnError": {
        "Type": "state_failed",
        "Include": ["$.error"]
      }
    },
    "Next": "Continue"
  }
}
```

### Event Channels

Route events to different destinations:

```json
{
  "Events": {
    "Channels": {
      "metrics": {
        "Type": "prometheus",
        "Endpoint": "http://metrics:9090"
      },
      "logs": {
        "Type": "stdout",
        "Format": "json"
      },
      "notifications": {
        "Type": "webhook",
        "Url": "https://hooks.example.com/workflow"
      }
    },
    "OnStateChange": {
      "Channel": "logs"
    },
    "OnProgress": {
      "Channel": "metrics"
    },
    "OnComplete": {
      "Channel": "notifications"
    }
  }
}
```

## Workflow Progress

Track overall workflow progress:

```json
{
  "Comment": "Workflow with progress tracking",
  "Progress": {
    "Enabled": true,
    "TotalStates": 5,
    "WeightedProgress": {
      "Step1": 10,
      "Step2": 30,
      "Step3": 40,
      "Step4": 15,
      "Step5": 5
    }
  },
  "StartAt": "Step1",
  "States": { ... }
}
```

### Weighted Progress

Weight states by their relative importance:

```json
{
  "WeightedProgress": {
    "DataLoad": 5,
    "Analysis": 60,
    "Report": 25,
    "Cleanup": 10
  }
}
```

## Parallel Progress

Track progress across parallel branches:

```json
{
  "ParallelAnalysis": {
    "Type": "Parallel",
    "Branches": [
      { "StartAt": "SecurityScan", "States": { ... } },
      { "StartAt": "PerformanceScan", "States": { ... } },
      { "StartAt": "StyleCheck", "States": { ... } }
    ],
    "Progress": {
      "Aggregation": "average",
      "PerBranch": true
    },
    "Next": "Combine"
  }
}
```

### Progress Aggregation

| Strategy | Description |
|----------|-------------|
| `average` | Average of all branches |
| `minimum` | Minimum progress across branches |
| `weighted` | Weighted average |

## Map Progress

Track progress for Map iterations:

```json
{
  "ProcessItems": {
    "Type": "Map",
    "ItemsPath": "$.items",
    "MaxConcurrency": 5,
    "Progress": {
      "Enabled": true,
      "PerItem": true,
      "AggregateBy": "completed"
    },
    "Iterator": { ... },
    "Next": "Done"
  }
}
```

### Map Progress Object

```json
{
  "progress": {
    "total": 100,
    "completed": 45,
    "inProgress": 5,
    "pending": 50,
    "failed": 0,
    "percentage": 45
  }
}
```

## Real-Time Updates

Configure real-time update delivery:

```json
{
  "RealTime": {
    "Enabled": true,
    "Transport": "websocket",
    "Endpoint": "wss://updates.example.com/workflow",
    "Authentication": {
      "Type": "bearer",
      "TokenPath": "$.authToken"
    },
    "Events": ["progress", "milestone", "completion"]
  }
}
```

### Transport Options

| Transport | Description |
|-----------|-------------|
| `websocket` | WebSocket connection |
| `sse` | Server-Sent Events |
| `polling` | HTTP polling |
| `callback` | HTTP callbacks |

## Complete Example

```json
{
  "Comment": "Document processing with full progress tracking",
  "Progress": {
    "Enabled": true,
    "WeightedProgress": {
      "ParseDocument": 10,
      "ExtractSections": 20,
      "AnalyzeSections": 50,
      "GenerateSummary": 20
    }
  },
  "StartAt": "ParseDocument",
  "States": {
    "ParseDocument": {
      "Type": "Task",
      "Agent": "DocumentParser",
      "Streaming": {
        "Enabled": false
      },
      "Progress": {
        "Milestones": [
          { "At": 50, "Emit": "parsing_halfway" },
          { "At": 100, "Emit": "parsing_complete" }
        ]
      },
      "Events": {
        "OnEnter": { "Type": "started_parsing" },
        "OnExit": { "Type": "finished_parsing" }
      },
      "Next": "ExtractSections"
    },
    "ExtractSections": {
      "Type": "Task",
      "Agent": "SectionExtractor",
      "Progress": {
        "Enabled": true,
        "Path": "$.extractionProgress"
      },
      "Next": "AnalyzeSections"
    },
    "AnalyzeSections": {
      "Type": "Map",
      "ItemsPath": "$.sections",
      "MaxConcurrency": 3,
      "Progress": {
        "Enabled": true,
        "PerItem": true
      },
      "Iterator": {
        "StartAt": "AnalyzeSection",
        "States": {
          "AnalyzeSection": {
            "Type": "Task",
            "Agent": "SectionAnalyzer",
            "TimeoutSeconds": 120,
            "HeartbeatSeconds": 30,
            "Streaming": {
              "Enabled": true,
              "Mode": "token"
            },
            "End": true
          }
        }
      },
      "Next": "GenerateSummary"
    },
    "GenerateSummary": {
      "Type": "Task",
      "Agent": "Summarizer",
      "Streaming": {
        "Enabled": true,
        "Mode": "sentence",
        "Callbacks": {
          "OnChunk": "StreamToClient"
        }
      },
      "Progress": {
        "Milestones": [
          { "At": 25, "Name": "outline", "Emit": "outline_ready" },
          { "At": 75, "Name": "draft", "Emit": "draft_ready" },
          { "At": 100, "Name": "final", "Emit": "summary_complete" }
        ]
      },
      "End": true
    }
  }
}
```

## Best Practices

### 1. Enable Streaming for User-Facing Output

```json
{
  "Streaming": {
    "Enabled": true,
    "Mode": "token"
  }
}
```

### 2. Use Heartbeats for Long Tasks

```json
{
  "TimeoutSeconds": 600,
  "HeartbeatSeconds": 30
}
```

### 3. Define Meaningful Milestones

```json
{
  "Progress": {
    "Milestones": [
      { "At": 25, "Name": "preparation_complete" },
      { "At": 75, "Name": "processing_complete" }
    ]
  }
}
```

### 4. Weight Progress by Effort

```json
{
  "WeightedProgress": {
    "QuickStep": 5,
    "HeavyComputation": 80,
    "Cleanup": 15
  }
}
```

### 5. Emit Events for Observability

```json
{
  "Events": {
    "OnEnter": { "Type": "step_started" },
    "OnExit": { "Type": "step_completed" }
  }
}
```
