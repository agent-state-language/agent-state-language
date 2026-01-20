# Tutorial 8: Human Approval

Learn how to integrate human oversight into your agent workflows.

## What You'll Learn

- Adding approval gates to workflows
- Configuring timeouts and escalation
- Routing based on approval decisions
- Editable content approvals
- Building a complete content review workflow

## Prerequisites

- Completed [Tutorial 7: Tool Orchestration](07-tool-orchestration.md)
- Understanding of Choice states

## The Scenario

We'll build a content publishing workflow that:

1. Generates content using an AI agent
2. Requires human review before publishing
3. Allows editors to approve, request changes, or reject
4. Escalates to senior editors after timeout
5. Supports inline editing of content

## Step 1: Understanding Approval States

Approval states pause workflow execution until a human provides input. They're essential for:

- **Quality control** - Review AI-generated content
- **Security** - Authorize sensitive operations
- **Compliance** - Ensure human oversight

### Approval Flow

```
Workflow → Approval State → [PAUSED] → Human Decision → Continue
```

## Step 2: Create the Agents

### ContentGeneratorAgent

Generates content for review:

```php
<?php

namespace MyOrg\ContentReview;

use AgentStateLanguage\Agents\AgentInterface;

class ContentGeneratorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? 'General';
        $type = $parameters['type'] ?? 'article';
        $tone = $parameters['tone'] ?? 'professional';
        
        // Simulate content generation
        $content = $this->generateContent($topic, $type, $tone);
        
        return [
            'title' => "Understanding {$topic}",
            'content' => $content,
            'wordCount' => str_word_count($content),
            'type' => $type,
            'generatedAt' => date('c'),
            'metadata' => [
                'topic' => $topic,
                'tone' => $tone,
                'aiGenerated' => true
            ]
        ];
    }
    
    private function generateContent(string $topic, string $type, string $tone): string
    {
        // Simulate AI-generated content
        $templates = [
            'article' => "This comprehensive article explores {$topic} in detail. " .
                         "We'll cover the key concepts, best practices, and common pitfalls. " .
                         "By the end, you'll have a solid understanding of {$topic}.",
            'blog' => "Let's dive into {$topic}! This is something many people ask about, " .
                      "and today I'm going to break it down for you in simple terms.",
            'technical' => "Technical Overview: {$topic}\n\n" .
                          "This document provides a technical analysis of {$topic}, " .
                          "including implementation details and performance considerations."
        ];
        
        return $templates[$type] ?? $templates['article'];
    }

    public function getName(): string
    {
        return 'ContentGeneratorAgent';
    }
}
```

### ContentPublisherAgent

Publishes approved content:

```php
<?php

namespace MyOrg\ContentReview;

use AgentStateLanguage\Agents\AgentInterface;

class ContentPublisherAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $title = $parameters['title'] ?? 'Untitled';
        $content = $parameters['content'] ?? '';
        $approvedBy = $parameters['approvedBy'] ?? 'unknown';
        
        // Simulate publishing
        $publishId = 'pub_' . uniqid();
        
        return [
            'published' => true,
            'publishId' => $publishId,
            'url' => "https://example.com/posts/{$publishId}",
            'title' => $title,
            'publishedAt' => date('c'),
            'approvedBy' => $approvedBy
        ];
    }

    public function getName(): string
    {
        return 'ContentPublisherAgent';
    }
}
```

### RevisionAgent

Handles revision requests:

```php
<?php

namespace MyOrg\ContentReview;

use AgentStateLanguage\Agents\AgentInterface;

class RevisionAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $originalContent = $parameters['content'] ?? '';
        $feedback = $parameters['feedback'] ?? '';
        $changes = $parameters['requestedChanges'] ?? [];
        
        // Simulate content revision based on feedback
        $revisedContent = $this->applyRevisions($originalContent, $feedback, $changes);
        
        return [
            'revised' => true,
            'content' => $revisedContent,
            'changesMade' => count($changes),
            'feedbackAddressed' => !empty($feedback),
            'revisedAt' => date('c')
        ];
    }
    
    private function applyRevisions(string $content, string $feedback, array $changes): string
    {
        // Simulate revision - in reality, would use LLM
        $revised = $content;
        
        if (stripos($feedback, 'shorter') !== false) {
            $revised = substr($content, 0, (int)(strlen($content) * 0.7)) . '...';
        }
        
        if (stripos($feedback, 'formal') !== false) {
            $revised = str_replace("Let's", "We will", $revised);
        }
        
        return $revised . "\n\n[Revised based on feedback]";
    }

    public function getName(): string
    {
        return 'RevisionAgent';
    }
}
```

## Step 3: Define the Workflow

Create `content-review.asl.json`:

```json
{
  "Comment": "Content review workflow with human approval",
  "StartAt": "GenerateContent",
  "States": {
    "GenerateContent": {
      "Type": "Task",
      "Agent": "ContentGeneratorAgent",
      "Parameters": {
        "topic.$": "$.topic",
        "type.$": "$.contentType",
        "tone.$": "$.tone"
      },
      "ResultPath": "$.draft",
      "Next": "ReviewContent"
    },
    "ReviewContent": {
      "Type": "Approval",
      "Prompt": {
        "Title": "Content Review Required",
        "Description": "Please review the following AI-generated content before publishing.",
        "Content.$": "$.draft.content",
        "Metadata": {
          "topic.$": "$.topic",
          "wordCount.$": "$.draft.wordCount",
          "generatedAt.$": "$.draft.generatedAt"
        }
      },
      "Options": ["approve", "request_changes", "reject"],
      "Editable": {
        "Fields": ["$.draft.title", "$.draft.content"],
        "ResultPath": "$.editedDraft"
      },
      "Timeout": "24h",
      "Escalation": {
        "After": "4h",
        "Notify": ["senior-editor@example.com"],
        "Message": "Content awaiting review for over 4 hours"
      },
      "ResultPath": "$.review",
      "Choices": [
        {
          "Variable": "$.review.decision",
          "StringEquals": "approve",
          "Next": "PrepareForPublishing"
        },
        {
          "Variable": "$.review.decision",
          "StringEquals": "request_changes",
          "Next": "ReviseContent"
        }
      ],
      "Default": "ContentRejected"
    },
    "ReviseContent": {
      "Type": "Task",
      "Agent": "RevisionAgent",
      "Parameters": {
        "content.$": "$.draft.content",
        "feedback.$": "$.review.feedback",
        "requestedChanges.$": "$.review.changes"
      },
      "ResultPath": "$.revision",
      "Next": "UpdateDraft"
    },
    "UpdateDraft": {
      "Type": "Pass",
      "Parameters": {
        "topic.$": "$.topic",
        "contentType.$": "$.contentType",
        "tone.$": "$.tone",
        "draft": {
          "title.$": "$.draft.title",
          "content.$": "$.revision.content",
          "wordCount.$": "$.draft.wordCount",
          "generatedAt.$": "$.draft.generatedAt",
          "revisedAt.$": "$.revision.revisedAt"
        },
        "revisionCount.$": "States.MathAdd($.revisionCount, 1)"
      },
      "Next": "CheckRevisionLimit"
    },
    "CheckRevisionLimit": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.revisionCount",
          "NumericGreaterThanEquals": 3,
          "Next": "EscalateToSeniorEditor"
        }
      ],
      "Default": "ReviewContent"
    },
    "EscalateToSeniorEditor": {
    "Type": "Approval",
      "Prompt": {
        "Title": "Senior Editor Review Required",
        "Description": "This content has been revised 3+ times. Final decision needed.",
        "Content.$": "$.draft.content",
        "RevisionHistory.$": "$.revisionCount"
      },
      "Options": ["force_approve", "final_reject"],
      "Timeout": "48h",
      "ResultPath": "$.seniorReview",
      "Choices": [
        {
          "Variable": "$.seniorReview.decision",
          "StringEquals": "force_approve",
          "Next": "PrepareForPublishing"
        }
      ],
      "Default": "ContentRejected"
    },
    "PrepareForPublishing": {
      "Type": "Pass",
      "Parameters": {
        "title.$": "$.editedDraft.title",
        "content.$": "$.editedDraft.content",
        "approvedBy.$": "$.review.approver",
        "originalDraft.$": "$.draft"
      },
      "ResultPath": "$.publishData",
      "Next": "PublishContent"
    },
    "PublishContent": {
      "Type": "Task",
      "Agent": "ContentPublisherAgent",
      "Parameters": {
        "title.$": "$.publishData.title",
        "content.$": "$.publishData.content",
        "approvedBy.$": "$.publishData.approvedBy"
      },
      "ResultPath": "$.published",
      "Next": "PublishSuccess"
    },
    "PublishSuccess": {
      "Type": "Pass",
      "Parameters": {
        "status": "published",
        "url.$": "$.published.url",
        "publishId.$": "$.published.publishId",
        "title.$": "$.published.title"
      },
      "End": true
    },
    "ContentRejected": {
      "Type": "Pass",
      "Parameters": {
        "status": "rejected",
        "reason.$": "$.review.feedback",
        "rejectedAt.$": "$$.State.EnteredTime"
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
use MyOrg\ContentReview\ContentGeneratorAgent;
use MyOrg\ContentReview\ContentPublisherAgent;
use MyOrg\ContentReview\RevisionAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('ContentGeneratorAgent', new ContentGeneratorAgent());
$registry->register('ContentPublisherAgent', new ContentPublisherAgent());
$registry->register('RevisionAgent', new RevisionAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('content-review.asl.json', $registry);

// Start the workflow
$result = $engine->run([
    'topic' => 'Machine Learning Best Practices',
    'contentType' => 'article',
    'tone' => 'professional',
    'revisionCount' => 0
]);

// In a real application, the workflow would pause at the Approval state
// and resume when a human provides input via an API or UI

if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "Workflow Status: " . $output['status'] . "\n";
    
    if ($output['status'] === 'published') {
        echo "Published URL: " . $output['url'] . "\n";
        echo "Title: " . $output['title'] . "\n";
    } else {
        echo "Reason: " . ($output['reason'] ?? 'N/A') . "\n";
    }
} else {
    echo "Workflow paused or failed\n";
    echo "Current state: Check trace for Approval state\n";
}

// Check execution trace
echo "\nExecution Trace:\n";
foreach ($result->getTrace() as $entry) {
    $type = $entry['type'] ?? 'unknown';
    $state = $entry['stateName'] ?? '';
    echo "- {$type}: {$state}\n";
}
```

## Step 5: Simulating Approval (Testing)

For testing, you can create a mock approval handler:

```php
<?php

// Mock approval provider for testing
class MockApprovalProvider
{
    private array $decisions = [];
    
    public function setDecision(string $stateName, array $decision): void
    {
        $this->decisions[$stateName] = $decision;
    }
    
    public function getDecision(string $stateName): ?array
    {
        return $this->decisions[$stateName] ?? null;
    }
}

// Usage in tests
$mockApproval = new MockApprovalProvider();
$mockApproval->setDecision('ReviewContent', [
    'decision' => 'approve',
    'approver' => 'editor@example.com',
    'feedback' => 'Looks great!',
    'timestamp' => date('c')
]);

// The engine would use this provider to auto-resolve approval states
```

## Expected Output

When approval is granted:
```
Workflow Status: published
Published URL: https://example.com/posts/pub_abc123
Title: Understanding Machine Learning Best Practices

Execution Trace:
- state_enter: GenerateContent
- state_exit: GenerateContent
- state_enter: ReviewContent
- approval_granted: ReviewContent
- state_exit: ReviewContent
- state_enter: PrepareForPublishing
- state_exit: PrepareForPublishing
- state_enter: PublishContent
- state_exit: PublishContent
- state_enter: PublishSuccess
- workflow_complete:
```

## Approval State Options

### Basic Options

| Option | Description |
|--------|-------------|
| `approve` | Accept and continue |
| `reject` | Decline and stop |
| `request_changes` | Request modifications |

### Custom Options

```json
{
  "Options": [
    "publish_now",
    "schedule_later",
    "save_draft",
    "needs_legal_review",
    "reject"
  ]
}
```

### Timeout Configuration

```json
{
  "Timeout": "24h",
  "OnTimeout": "AutoReject"
}
```

| OnTimeout | Behavior |
|-----------|----------|
| `AutoReject` | Automatically reject |
| `AutoApprove` | Automatically approve (risky!) |
| `Escalate` | Move to escalation path |
| `Fail` | Fail the workflow |

### Escalation

```json
{
  "Escalation": {
    "After": "4h",
    "Notify": ["manager@example.com", "#slack-channel"],
    "Message": "Urgent: Approval needed",
    "Repeat": "2h"
  }
}
```

## Experiment

Try these modifications:

### Add Multi-Level Approval

```json
{
  "FirstReview": {
    "Type": "Approval",
    "Prompt": "Technical review",
    "Options": ["approve", "reject"],
    "RequiredApprovers": 1,
    "Next": "SecondReview"
  },
  "SecondReview": {
    "Type": "Approval",
    "Prompt": "Editorial review",
    "Options": ["approve", "reject"],
    "RequiredApprovers": 2,
    "Next": "Publish"
  }
}
```

### Add Approval with Attachments

```json
{
    "Type": "Approval",
  "Prompt": {
    "Title": "Review Report",
    "Attachments": [
      { "type": "pdf", "path.$": "$.reportPath" },
      { "type": "image", "path.$": "$.chartPath" }
    ]
  }
}
```

## Common Mistakes

### Missing Default Path

```json
{
    "Type": "Approval",
  "Choices": [
    { "Variable": "$.approval", "StringEquals": "approve", "Next": "Continue" }
  ]
}
```

**Problem**: No handler for other decisions.

**Fix**: Always include a `Default` path.

### Timeout Without Handler

```json
{
  "Timeout": "24h"
}
```

**Problem**: What happens when timeout expires?

**Fix**: Specify `OnTimeout` behavior.

### Editable Without ResultPath

```json
{
    "Editable": {
    "Fields": ["$.content"]
  }
}
```

**Problem**: Edited content not saved.

**Fix**: Add `ResultPath` to Editable block.

## Summary

You've learned:

- ✅ Adding approval gates to workflows
- ✅ Configuring timeout and escalation
- ✅ Routing based on approval decisions
- ✅ Editable content approvals
- ✅ Multi-level approval patterns
- ✅ Building production-ready review workflows

## Next Steps

- [Tutorial 9: Multi-Agent Debate](09-multi-agent-debate.md) - Agent collaboration
- [Tutorial 10: Cost Management](10-cost-management.md) - Budget control
