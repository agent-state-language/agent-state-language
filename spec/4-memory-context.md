# 4. Memory and Context

This section covers how ASL manages persistent memory and context windows for agents.

## Overview

AI agents face two fundamental challenges:

1. **Context Limits** - LLMs have finite context windows
2. **State Persistence** - Information needs to survive across executions

ASL addresses these with two complementary systems:

- **Context** - Manages what goes into the current agent call
- **Memory** - Persists information across workflow executions

## Context Block

The Context block controls what information is passed to an agent.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Strategy` | string | Context selection strategy |
| `MaxTokens` | integer | Maximum context tokens |
| `Priority` | array | Priority-ordered JSONPaths |
| `Include` | array | Always include these paths |
| `Exclude` | array | Always exclude these paths |
| `Summarize` | object | Summarization configuration |

### Strategies

#### sliding_window

Keeps the most recent content within the token limit:

```json
{
  "Context": {
    "Strategy": "sliding_window",
    "MaxTokens": 8000
  }
}
```

#### priority_based

Includes content in priority order until token limit:

```json
{
  "Context": {
    "Strategy": "priority_based",
    "MaxTokens": 8000,
    "Priority": [
      "$.currentTask",
      "$.recentHistory",
      "$.systemContext",
      "$.backgroundInfo"
    ]
  }
}
```

#### semantic

Selects content based on semantic relevance:

```json
{
  "Context": {
    "Strategy": "semantic",
    "MaxTokens": 8000,
    "Query.$": "$.currentTask",
    "Candidates": ["$.documents", "$.history"],
    "TopK": 10
  }
}
```

### Include and Exclude

```json
{
  "Context": {
    "Strategy": "sliding_window",
    "MaxTokens": 6000,
    "Include": ["$.systemPrompt", "$.currentTask"],
    "Exclude": ["$.debugInfo", "$.internalState"]
  }
}
```

### Summarization

When context exceeds limits, summarization can compress content:

```json
{
  "Context": {
    "Strategy": "sliding_window",
    "MaxTokens": 8000,
    "Summarize": {
      "When": "TokensExceed",
      "Using": "SummarizerAgent",
      "Target": "$.history",
      "Ratio": 0.25
    }
  }
}
```

#### Summarization Triggers

| Trigger | Description |
|---------|-------------|
| `TokensExceed` | When total exceeds MaxTokens |
| `Always` | Always summarize specified paths |
| `Never` | Never summarize (truncate instead) |

### Example

```json
{
  "ConversationalAgent": {
    "Type": "Task",
    "Agent": "Assistant",
    "Parameters": {
      "message.$": "$.userMessage"
    },
    "Context": {
      "Strategy": "priority_based",
      "MaxTokens": 12000,
      "Priority": [
        "$.userMessage",
        "$.conversationHistory",
        "$.userPreferences",
        "$.systemKnowledge"
      ],
      "Include": ["$.systemPrompt"],
      "Summarize": {
        "When": "TokensExceed",
        "Using": "ConversationSummarizer",
        "Target": "$.conversationHistory",
        "Ratio": 0.3
      }
    },
    "Next": "ProcessResponse"
  }
}
```

## Memory Block

The Memory block configures persistent storage operations.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Read` | array/object | Memory keys to read |
| `Write` | object | Memory key to write |

### Reading Memory

Simple read (array of keys):

```json
{
  "Memory": {
    "Read": ["user_preferences", "past_interactions", "learned_patterns"]
  }
}
```

Read with options:

```json
{
  "Memory": {
    "Read": {
      "Keys": ["user_preferences"],
      "InjectAt": "$.context.preferences",
      "Default": { "theme": "light" }
    }
  }
}
```

### Writing Memory

```json
{
  "Memory": {
    "Write": {
      "Key": "analysis_results",
      "Value.$": "$.analysis",
      "TTL": "7d",
      "Merge": true
    }
  }
}
```

#### Write Options

| Field | Type | Description |
|-------|------|-------------|
| `Key` | string | Memory key |
| `Value` | any | Static value |
| `Value.$` | string | JSONPath to value |
| `TTL` | string | Time to live |
| `Merge` | boolean | Merge with existing |
| `Overwrite` | boolean | Overwrite existing |

### TTL Formats

| Format | Example | Description |
|--------|---------|-------------|
| Seconds | `"300"` | 300 seconds |
| Minutes | `"30m"` | 30 minutes |
| Hours | `"24h"` | 24 hours |
| Days | `"7d"` | 7 days |
| Never | `"never"` | Never expire |

### Example

```json
{
  "LearnFromInteraction": {
    "Type": "Task",
    "Agent": "LearningAgent",
    "Parameters": {
      "currentInteraction.$": "$.interaction"
    },
    "Memory": {
      "Read": {
        "Keys": ["user_profile", "interaction_history"],
        "Default": { "interactions": [] }
      },
      "Write": {
        "Key": "interaction_history",
        "Value.$": "$.updatedHistory",
        "TTL": "30d",
        "Merge": true
      }
    },
    "Next": "Respond"
  }
}
```

## Memory Namespaces

Memories can be organized into namespaces:

```json
{
  "Memory": {
    "Namespace": "project_abc",
    "Read": ["settings", "history"],
    "Write": {
      "Key": "results",
      "Value.$": "$.output"
    }
  }
}
```

### Namespace Patterns

| Pattern | Description |
|---------|-------------|
| Static | `"project_abc"` |
| Dynamic | `"user_{{$.userId}}"` |
| Hierarchical | `"org/team/project"` |

## Memory Types

### Key-Value Memory

Standard key-value storage:

```json
{
  "Memory": {
    "Type": "key_value",
    "Read": ["settings"]
  }
}
```

### Semantic Memory

Vector-based retrieval:

```json
{
  "Memory": {
    "Type": "semantic",
    "Read": {
      "Query.$": "$.currentQuestion",
      "Collection": "knowledge_base",
      "TopK": 5,
      "MinScore": 0.7
    }
  }
}
```

### Episodic Memory

Time-ordered event storage:

```json
{
  "Memory": {
    "Type": "episodic",
    "Read": {
      "Collection": "user_sessions",
      "Filter": {
        "userId.$": "$.userId"
      },
      "Limit": 10,
      "Order": "desc"
    }
  }
}
```

## Workflow-Level Memory Configuration

Configure memory at the workflow level:

```json
{
  "Comment": "Workflow with memory",
  "Memory": {
    "Backend": "redis",
    "Connection": "redis://localhost:6379",
    "DefaultTTL": "24h",
    "Namespace": "my_workflow"
  },
  "StartAt": "Begin",
  "States": { ... }
}
```

### Backend Options

| Backend | Description |
|---------|-------------|
| `memory` | In-process (testing) |
| `file` | File-based storage |
| `redis` | Redis storage |
| `dynamodb` | AWS DynamoDB |
| `custom` | Custom implementation |

## Context + Memory Integration

Combine context and memory for powerful patterns:

```json
{
  "IntelligentAssistant": {
    "Type": "Task",
    "Agent": "Assistant",
    "Memory": {
      "Read": {
        "Keys": ["user_profile", "conversation_summary"],
        "InjectAt": "$.background"
      }
    },
    "Context": {
      "Strategy": "priority_based",
      "MaxTokens": 10000,
      "Priority": [
        "$.currentMessage",
        "$.recentMessages",
        "$.background"
      ]
    },
    "Next": "SaveInteraction"
  },
  "SaveInteraction": {
    "Type": "Task",
    "Agent": "Summarizer",
    "Memory": {
      "Write": {
        "Key": "conversation_summary",
        "Value.$": "$.summary",
        "Merge": true
      }
    },
    "End": true
  }
}
```

## Best Practices

### 1. Use Namespaces for Isolation

```json
{
  "Memory": {
    "Namespace.$": "States.Format('user_{}', $.userId)"
  }
}
```

### 2. Set Appropriate TTLs

- Short-lived data: `"1h"` to `"24h"`
- Session data: `"7d"`
- Learning data: `"30d"` or longer
- Reference data: `"never"`

### 3. Prefer Priority-Based Context

```json
{
  "Context": {
    "Strategy": "priority_based",
    "Priority": ["critical", "important", "background"]
  }
}
```

### 4. Summarize Long Content

```json
{
  "Context": {
    "Summarize": {
      "When": "TokensExceed",
      "Target": "$.history"
    }
  }
}
```

### 5. Handle Missing Memory

```json
{
  "Memory": {
    "Read": {
      "Keys": ["preferences"],
      "Default": { "initialized": false }
    }
  }
}
```
