# Tutorial 6: Memory and Context

Learn how to manage persistent memory and context windows in your workflows.

## What You'll Learn

- Memory blocks for persistence across workflow executions
- Context management strategies for LLM agents
- Building a personalized assistant that remembers users
- Best practices for state management

## Prerequisites

- Completed [Tutorial 5: Recursive Workflows](05-recursive-workflows.md)
- Understanding of Task states and data flow

## The Scenario

We'll build a personalized assistant that:

1. Remembers user preferences across sessions
2. Tracks conversation history
3. Uses context management to stay within token limits
4. Provides personalized responses based on past interactions

## Step 1: Understanding Memory

Memory allows workflows to persist data between executions. Think of it as a key-value store that agents can read from and write to.

### Memory Operations

| Operation | Purpose |
|-----------|---------|
| `Read` | Load stored data into the workflow state |
| `Write` | Save data for future executions |
| `Merge` | Combine new data with existing data |
| `TTL` | Set expiration time for stored data |

## Step 2: Create the Agents

### ContextLoaderAgent

This agent loads user context from memory:

```php
<?php

namespace MyOrg\PersonalAssistant;

use AgentStateLanguage\Agents\AgentInterface;

class ContextLoaderAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $userId = $parameters['userId'] ?? 'anonymous';
        $memoryData = $parameters['_memory'] ?? [];
        
        // Memory data is injected by the engine based on Memory.Read config
        $userProfile = $memoryData['user_profile'] ?? null;
        $preferences = $memoryData['preferences'] ?? null;
        $history = $memoryData['history'] ?? [];
        
        if ($userProfile === null) {
            // New user - return defaults
            return [
                'isNewUser' => true,
                'userId' => $userId,
                'profile' => [
                    'name' => 'Friend',
                    'createdAt' => date('c')
                ],
                'preferences' => [
                    'tone' => 'friendly',
                    'verbosity' => 'normal'
                ],
                'history' => []
            ];
        }
        
        return [
            'isNewUser' => false,
            'userId' => $userId,
            'profile' => $userProfile,
            'preferences' => $preferences,
            'history' => array_slice($history, -10) // Last 10 interactions
        ];
    }

    public function getName(): string
    {
        return 'ContextLoaderAgent';
    }
}
```

### AssistantAgent

The main assistant that processes requests:

```php
<?php

namespace MyOrg\PersonalAssistant;

use AgentStateLanguage\Agents\AgentInterface;

class AssistantAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $userMessage = $parameters['userMessage'] ?? '';
        $userContext = $parameters['userContext'] ?? [];
        $isNewUser = $userContext['isNewUser'] ?? true;
        $preferences = $userContext['preferences'] ?? [];
        $userName = $userContext['profile']['name'] ?? 'Friend';
        
        // Build personalized response
        $greeting = $isNewUser 
            ? "Hello! Nice to meet you. " 
            : "Welcome back, {$userName}! ";
        
        // Simulate processing based on preferences
        $tone = $preferences['tone'] ?? 'friendly';
        $response = $this->generateResponse($userMessage, $tone);
        
        return [
            'message' => $greeting . $response,
            'processedAt' => date('c'),
            'tokens' => strlen($userMessage) + strlen($response),
            'newInteraction' => [
                'timestamp' => date('c'),
                'userMessage' => $userMessage,
                'response' => $response
            ]
        ];
    }
    
    private function generateResponse(string $message, string $tone): string
    {
        // In a real implementation, this would call an LLM
        $responses = [
            'friendly' => "I'd be happy to help you with that! ",
            'professional' => "Certainly. Let me assist you with your request. ",
            'concise' => "Sure. "
        ];
        
        $prefix = $responses[$tone] ?? $responses['friendly'];
        
        if (stripos($message, 'weather') !== false) {
            return $prefix . "The weather looks great today!";
        }
        if (stripos($message, 'help') !== false) {
            return $prefix . "I can help with questions, tasks, and more.";
        }
        
        return $prefix . "I understand. How else can I assist you?";
    }

    public function getName(): string
    {
        return 'AssistantAgent';
    }
}
```

### HistoryUpdaterAgent

Manages the conversation history:

```php
<?php

namespace MyOrg\PersonalAssistant;

use AgentStateLanguage\Agents\AgentInterface;

class HistoryUpdaterAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $existingHistory = $parameters['history'] ?? [];
        $newInteraction = $parameters['newInteraction'] ?? [];
        $userContext = $parameters['userContext'] ?? [];
        
        // Append new interaction
        $existingHistory[] = $newInteraction;
        
        // Keep only last 50 interactions to manage memory size
        if (count($existingHistory) > 50) {
            $existingHistory = array_slice($existingHistory, -50);
        }
        
        // Prepare data for memory storage
        return [
            'updatedHistory' => $existingHistory,
            'historyCount' => count($existingHistory),
            'userProfile' => $userContext['profile'] ?? [],
            'preferences' => $userContext['preferences'] ?? [],
            'lastInteraction' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'HistoryUpdaterAgent';
    }
}
```

## Step 3: Define the Workflow

Create `personalized-assistant.asl.json`:

```json
{
  "Comment": "Personalized assistant with memory and context management",
  "StartAt": "LoadUserContext",
  "States": {
    "LoadUserContext": {
      "Type": "Task",
      "Agent": "ContextLoaderAgent",
      "Parameters": {
        "userId.$": "$.userId"
      },
      "Memory": {
        "Read": {
          "Keys": ["user_profile", "preferences", "history"],
          "InjectAt": "$._memory",
          "Default": {}
        }
      },
      "ResultPath": "$.userContext",
      "Next": "CheckNewUser"
    },
    "CheckNewUser": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.userContext.isNewUser",
          "BooleanEquals": true,
          "Next": "WelcomeNewUser"
        }
      ],
      "Default": "ProcessRequest"
    },
    "WelcomeNewUser": {
      "Type": "Pass",
      "Parameters": {
        "userId.$": "$.userId",
        "userMessage.$": "$.userMessage",
        "userContext.$": "$.userContext",
        "welcomeShown": true
      },
      "Next": "ProcessRequest"
    },
    "ProcessRequest": {
      "Type": "Task",
      "Agent": "AssistantAgent",
      "Parameters": {
        "userMessage.$": "$.userMessage",
        "userContext.$": "$.userContext"
      },
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
      "Agent": "HistoryUpdaterAgent",
      "Parameters": {
        "history.$": "$.userContext.history",
        "newInteraction.$": "$.response.newInteraction",
        "userContext.$": "$.userContext"
      },
      "ResultPath": "$.historyUpdate",
      "Next": "SaveToMemory"
    },
    "SaveToMemory": {
      "Type": "Pass",
      "Parameters": {
        "message.$": "$.response.message",
        "userId.$": "$.userId",
        "isNewUser.$": "$.userContext.isNewUser",
        "interactionCount.$": "$.historyUpdate.historyCount"
      },
      "Memory": {
        "Write": [
          {
            "Key": "user_profile",
            "Value.$": "$.historyUpdate.userProfile",
            "TTL": "365d"
          },
          {
            "Key": "preferences",
            "Value.$": "$.historyUpdate.preferences",
            "TTL": "365d"
          },
          {
            "Key": "history",
            "Value.$": "$.historyUpdate.updatedHistory",
            "TTL": "90d",
            "Merge": false
          }
        ]
      },
      "End": true
    }
  }
}
```

## Step 4: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\PersonalAssistant\ContextLoaderAgent;
use MyOrg\PersonalAssistant\AssistantAgent;
use MyOrg\PersonalAssistant\HistoryUpdaterAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('ContextLoaderAgent', new ContextLoaderAgent());
$registry->register('AssistantAgent', new AssistantAgent());
$registry->register('HistoryUpdaterAgent', new HistoryUpdaterAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('personalized-assistant.asl.json', $registry);

// First interaction - new user
$result1 = $engine->run([
    'userId' => 'user_123',
    'userMessage' => 'Hello! Can you help me with something?'
]);

if ($result1->isSuccess()) {
    echo "First Response:\n";
    echo $result1->getOutput()['message'] . "\n";
    echo "Is new user: " . ($result1->getOutput()['isNewUser'] ? 'Yes' : 'No') . "\n";
    echo "---\n";
}

// Second interaction - returning user (simulated)
$result2 = $engine->run([
    'userId' => 'user_123',
    'userMessage' => 'What\'s the weather like today?'
]);

if ($result2->isSuccess()) {
    echo "Second Response:\n";
    echo $result2->getOutput()['message'] . "\n";
    echo "Interaction count: " . $result2->getOutput()['interactionCount'] . "\n";
}
```

## Expected Output

```
First Response:
Hello! Nice to meet you. I'd be happy to help you with that! I can help with questions, tasks, and more.
Is new user: Yes
---
Second Response:
Welcome back, Friend! I'd be happy to help you with that! The weather looks great today!
Interaction count: 2
```

## Understanding Context Management

### Context Strategies

| Strategy | Description | Best For |
|----------|-------------|----------|
| `sliding_window` | Keeps most recent content within token limit | Chat applications |
| `priority_based` | Prioritizes specified paths | Complex workflows |
| `summarize` | Compresses old content | Long conversations |

### Priority-Based Strategy

The priority list determines what gets included when context is limited:

```json
{
  "Context": {
    "Strategy": "priority_based",
    "MaxTokens": 8000,
    "Priority": [
      "$.userMessage",
      "$.userContext.preferences",
      "$.userContext.history"
    ]
  }
}
```

1. `$.userMessage` - Always included first (highest priority)
2. `$.userContext.preferences` - Included if space allows
3. `$.userContext.history` - Included last, may be truncated

### Automatic Summarization

For long-running conversations, use automatic summarization:

```json
{
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
```

This configuration:
- Triggers summarization when tokens exceed the limit
- Uses `SummarizerAgent` to compress the history
- Targets the conversation history field
- Compresses to 30% of original size

## Memory Configuration Options

### TTL (Time To Live)

```json
{
  "Memory": {
    "Write": {
      "Key": "session_data",
      "Value.$": "$.data",
      "TTL": "24h"
    }
  }
}
```

| Format | Example | Duration |
|--------|---------|----------|
| Hours | `"24h"` | 24 hours |
| Days | `"30d"` | 30 days |
| Weeks | `"2w"` | 2 weeks |

### Merge Mode

When `Merge: true`, new values are merged with existing:

```json
{
  "Memory": {
    "Write": {
      "Key": "user_tags",
      "Value.$": "$.newTags",
      "Merge": true
    }
  }
}
```

**Before**: `["tag1", "tag2"]`
**New value**: `["tag3"]`
**After merge**: `["tag1", "tag2", "tag3"]`

## Experiment

Try these modifications:

### Add a Preference Update Flow

```json
{
  "UpdatePreferences": {
    "Type": "Task",
    "Agent": "PreferenceUpdater",
    "Parameters": {
      "currentPrefs.$": "$.userContext.preferences",
      "requestedChanges.$": "$.preferenceChanges"
    },
    "Memory": {
      "Write": {
        "Key": "preferences",
        "Value.$": "$.updatedPreferences",
        "Merge": true
      }
    },
    "Next": "ConfirmUpdate"
  }
}
```

### Add Memory Read Defaults

```json
{
  "Memory": {
    "Read": {
      "Keys": ["user_settings"],
      "Default": {
        "theme": "light",
        "language": "en",
        "notifications": true
      }
    }
  }
}
```

## Common Mistakes

### Missing Memory Configuration

```
Error: States.MemoryNotConfigured
```

**Fix**: Ensure the workflow has a `Memory` block configured at the workflow or state level.

### TTL Format Error

```
Error: States.InvalidTTL
```

**Fix**: Use valid duration format: `"30d"`, `"24h"`, `"2w"`.

### Reading Non-Existent Keys

**Problem**: Memory read fails when key doesn't exist.

**Fix**: Always provide a `Default` value:

```json
{
  "Memory": {
    "Read": {
      "Keys": ["optional_data"],
      "Default": null
    }
  }
}
```

### Context Overflow

**Problem**: Agent receives truncated context.

**Fix**: Use priority-based strategy or summarization to manage context size intelligently.

## Summary

You've learned:

- ✅ Reading and writing persistent memory
- ✅ Memory TTL and merge options
- ✅ Context management strategies (sliding window, priority-based)
- ✅ Automatic summarization for long conversations
- ✅ Building a complete personalized assistant
- ✅ Common patterns and mistakes

## Next Steps

- [Tutorial 7: Tool Orchestration](07-tool-orchestration.md) - Control tool access
- [Tutorial 8: Human Approval](08-human-approval.md) - Add approval gates
