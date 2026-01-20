# Tutorial 6: Memory and Context

Learn how to manage persistent memory and context windows in your workflows.

## What You'll Learn

- Memory blocks for persistence
- Context management strategies
- Combining memory and context
- Best practices for state management

## Memory Block

The Memory block allows agents to read and write persistent data.

### Reading Memory

```json
{
  "PersonalizedGreeting": {
    "Type": "Task",
    "Agent": "Greeter",
    "Memory": {
      "Read": ["user_preferences", "greeting_history"]
    },
    "Next": "Continue"
  }
}
```

### Writing Memory

```json
{
  "SavePreferences": {
    "Type": "Task",
    "Agent": "PreferenceSaver",
    "Memory": {
      "Write": {
        "Key": "user_preferences",
        "Value.$": "$.preferences",
        "TTL": "30d"
      }
    },
    "Next": "Done"
  }
}
```

### Combined Read/Write

```json
{
  "UpdateHistory": {
    "Type": "Task",
    "Agent": "HistoryManager",
    "Memory": {
      "Read": ["interaction_history"],
      "Write": {
        "Key": "interaction_history",
        "Value.$": "$.updatedHistory",
        "Merge": true
      }
    }
  }
}
```

## Context Block

The Context block manages what information is passed to an agent's context window.

### Sliding Window Strategy

```json
{
  "ConversationalAgent": {
    "Type": "Task",
    "Agent": "Assistant",
    "Context": {
      "Strategy": "sliding_window",
      "MaxTokens": 8000
    }
  }
}
```

### Priority-Based Strategy

```json
{
  "AnalysisAgent": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Context": {
      "Strategy": "priority_based",
      "MaxTokens": 10000,
      "Priority": [
        "$.currentTask",
        "$.relevantData",
        "$.backgroundInfo"
      ]
    }
  }
}
```

### With Summarization

```json
{
  "LongConversation": {
    "Type": "Task",
    "Agent": "ChatAgent",
    "Context": {
      "Strategy": "sliding_window",
      "MaxTokens": 6000,
      "Summarize": {
        "When": "TokensExceed",
        "Using": "SummarizerAgent",
        "Target": "$.conversationHistory",
        "Ratio": 0.3
      }
    }
  }
}
```

## Complete Example

```json
{
  "Comment": "Personalized assistant with memory",
  "StartAt": "LoadUserContext",
  "States": {
    "LoadUserContext": {
      "Type": "Task",
      "Agent": "ContextLoader",
      "Memory": {
        "Read": {
          "Keys": ["user_profile", "preferences", "history"],
          "InjectAt": "$.userContext",
          "Default": { "isNewUser": true }
        }
      },
      "Next": "ProcessRequest"
    },
    "ProcessRequest": {
      "Type": "Task",
      "Agent": "Assistant",
      "Context": {
        "Strategy": "priority_based",
        "MaxTokens": 8000,
        "Priority": [
          "$.userMessage",
          "$.userContext.preferences",
          "$.userContext.history"
        ]
      },
      "ResultPath": "$.response",
      "Next": "UpdateHistory"
    },
    "UpdateHistory": {
      "Type": "Task",
      "Agent": "HistoryUpdater",
      "Memory": {
        "Write": {
          "Key": "history",
          "Value.$": "$.updatedHistory",
          "TTL": "90d",
          "Merge": true
        }
      },
      "End": true
    }
  }
}
```

## Summary

You've learned:

- ✅ Reading and writing persistent memory
- ✅ Context management strategies
- ✅ Automatic summarization
- ✅ Combining memory and context
