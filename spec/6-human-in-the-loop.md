# 6. Human-in-the-Loop

This section covers how ASL integrates human oversight and feedback into agent workflows.

## Overview

Human-in-the-loop (HITL) patterns are essential for:

- **Safety** - Humans review critical decisions
- **Quality** - Human feedback improves agent outputs
- **Compliance** - Audit requirements may mandate human review
- **Learning** - Feedback enables agent improvement

## Approval State

The Approval state pauses workflow execution until a human makes a decision.

### Basic Usage

```json
{
  "ReviewChanges": {
    "Type": "Approval",
    "Prompt": "Review the proposed code changes",
    "Next": "ApplyChanges"
  }
}
```

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `Type` | `"Approval"` | Yes | State type |
| `Prompt` | string | Yes | Message for approver |
| `Options` | array | No | Available choices |
| `Timeout` | string | No | Max wait time |
| `Escalation` | object | No | Escalation configuration |
| `ResultPath` | string | No | Where to store decision |
| `Choices` | array | No | Route based on decision |
| `Default` | string | No | State if timeout |

### Options

Default options are `["approve", "reject"]`. Custom options:

```json
{
  "ReviewPullRequest": {
    "Type": "Approval",
    "Prompt": "Review PR #{{$.prNumber}}",
    "Options": [
      "approve",
      "request_changes",
      "comment",
      "reject"
    ],
    "ResultPath": "$.reviewDecision",
    "Next": "ProcessDecision"
  }
}
```

### Routing Based on Decision

```json
{
  "HumanReview": {
    "Type": "Approval",
    "Prompt": "Approve deployment to production?",
    "Options": ["approve", "reject", "delay"],
    "Choices": [
      {
        "Variable": "$.approval",
        "StringEquals": "approve",
        "Next": "Deploy"
      },
      {
        "Variable": "$.approval",
        "StringEquals": "delay",
        "Next": "ScheduleDeployment"
      }
    ],
    "Default": "Cancelled"
  }
}
```

### Approval Result

The approval decision is stored in the result:

```json
{
  "approval": "approve",
  "approver": "user@example.com",
  "timestamp": "2026-01-20T10:30:00Z",
  "comment": "Looks good to me!"
}
```

## Timeout and Escalation

### Basic Timeout

```json
{
  "QuickReview": {
    "Type": "Approval",
    "Prompt": "Quick review needed",
    "Timeout": "1h",
    "Default": "AutoApprove"
  }
}
```

### Escalation

```json
{
  "CriticalReview": {
    "Type": "Approval",
    "Prompt": "Critical security issue found",
    "Timeout": "48h",
    "Escalation": {
      "After": "4h",
      "Notify": ["manager@example.com"],
      "Repeat": {
        "Every": "8h",
        "MaxTimes": 3
      }
    },
    "Choices": [
      {
        "Variable": "$.approval",
        "StringEquals": "approve",
        "Next": "Proceed"
      }
    ],
    "Default": "AutoReject"
  }
}
```

### Escalation Fields

| Field | Type | Description |
|-------|------|-------------|
| `After` | string | Time before escalation |
| `Notify` | array | Notification recipients |
| `Message` | string | Custom escalation message |
| `Repeat` | object | Repeat configuration |
| `EscalateTo` | string | Escalation approver |

### Multi-Level Escalation

```json
{
  "Escalation": {
    "Levels": [
      {
        "After": "2h",
        "Notify": ["team-lead@example.com"]
      },
      {
        "After": "8h",
        "Notify": ["manager@example.com"]
      },
      {
        "After": "24h",
        "Notify": ["director@example.com"],
        "AutoApprove": false
      }
    ]
  }
}
```

## Rich Prompts

### Dynamic Prompts

```json
{
  "ReviewOrder": {
    "Type": "Approval",
    "Prompt.$": "States.Format('Review order #{} for ${:.2f}', $.orderId, $.total)",
    "Next": "ProcessOrder"
  }
}
```

### Structured Prompts

```json
{
  "DetailedReview": {
    "Type": "Approval",
    "Prompt": {
      "Title": "Code Review Required",
      "Description.$": "$.summary",
      "Details": {
        "Files Changed.$": "States.ArrayLength($.changedFiles)",
        "Lines Added.$": "$.stats.additions",
        "Lines Removed.$": "$.stats.deletions"
      },
      "Attachments": [
        {
          "Type": "diff",
          "Content.$": "$.diff"
        }
      ]
    },
    "Next": "Merge"
  }
}
```

## Feedback Collection

Collect structured feedback from humans:

```json
{
  "CollectFeedback": {
    "Type": "Task",
    "Agent": "ResponseGenerator",
    "Feedback": {
      "Collect": true,
      "Type": "rating",
      "Scale": [1, 5],
      "Labels": ["Poor", "Fair", "Good", "Great", "Excellent"],
      "Optional": ["comment"],
      "Store": "feedback_db"
    },
    "Next": "Continue"
  }
}
```

### Feedback Types

| Type | Description |
|------|-------------|
| `rating` | Numeric rating scale |
| `thumbs` | Thumbs up/down |
| `choice` | Multiple choice |
| `text` | Free-form text |
| `structured` | Custom fields |

### Rating Feedback

```json
{
  "Feedback": {
    "Type": "rating",
    "Scale": [1, 10],
    "Question": "How helpful was this response?"
  }
}
```

### Thumbs Feedback

```json
{
  "Feedback": {
    "Type": "thumbs",
    "Question": "Was this helpful?"
  }
}
```

### Structured Feedback

```json
{
  "Feedback": {
    "Type": "structured",
    "Fields": [
      {
        "Name": "accuracy",
        "Type": "rating",
        "Scale": [1, 5],
        "Label": "Accuracy"
      },
      {
        "Name": "helpfulness",
        "Type": "rating",
        "Scale": [1, 5],
        "Label": "Helpfulness"
      },
      {
        "Name": "comments",
        "Type": "text",
        "Label": "Additional Comments",
        "Optional": true
      }
    ]
  }
}
```

## Human Modification

Allow humans to modify agent output:

```json
{
  "GenerateEmail": {
    "Type": "Task",
    "Agent": "EmailWriter",
    "Next": "HumanEdit"
  },
  "HumanEdit": {
    "Type": "Approval",
    "Prompt": "Review and edit the generated email",
    "Options": ["send", "edit", "discard"],
    "Editable": {
      "Fields": ["$.email.subject", "$.email.body"],
      "ResultPath": "$.editedEmail"
    },
    "Choices": [
      {
        "Variable": "$.approval",
        "StringEquals": "send",
        "Next": "SendEmail"
      },
      {
        "Variable": "$.approval",
        "StringEquals": "edit",
        "Next": "SendEditedEmail"
      }
    ],
    "Default": "Discarded"
  }
}
```

## Batch Approvals

Review multiple items at once:

```json
{
  "BatchReview": {
    "Type": "Approval",
    "Prompt": "Review batch of items",
    "ItemsPath": "$.itemsToReview",
    "BatchMode": {
      "AllowPartial": true,
      "MinApproved": 0.8
    },
    "ResultPath": "$.batchDecisions",
    "Next": "ProcessApproved"
  }
}
```

### Batch Result

```json
{
  "batchDecisions": {
    "approved": ["item1", "item2", "item4"],
    "rejected": ["item3"],
    "approvalRate": 0.75
  }
}
```

## Notification Channels

Configure how approvers are notified:

```json
{
  "UrgentReview": {
    "Type": "Approval",
    "Prompt": "Urgent: Security vulnerability detected",
    "Notifications": {
      "Channels": [
        {
          "Type": "email",
          "To": ["security@example.com"]
        },
        {
          "Type": "slack",
          "Channel": "#security-alerts",
          "Mention": ["@security-team"]
        },
        {
          "Type": "sms",
          "To": ["+1234567890"],
          "OnlyAfter": "30m"
        }
      ]
    },
    "Next": "HandleVulnerability"
  }
}
```

## Approval Policies

Define who can approve:

```json
{
  "SensitiveOperation": {
    "Type": "Approval",
    "Prompt": "Approve sensitive data access",
    "Policy": {
      "Approvers": {
        "Roles": ["admin", "security-officer"],
        "Users": ["alice@example.com"]
      },
      "RequiredApprovals": 2,
      "ExcludeRequestor": true
    },
    "Next": "GrantAccess"
  }
}
```

### Policy Fields

| Field | Type | Description |
|-------|------|-------------|
| `Approvers` | object | Who can approve |
| `RequiredApprovals` | integer | Number of approvals needed |
| `ExcludeRequestor` | boolean | Requestor cannot approve |
| `RequireReason` | boolean | Require approval reason |

## Complete Example

```json
{
  "Comment": "Content Publishing Workflow with Human Review",
  "StartAt": "GenerateContent",
  "States": {
    "GenerateContent": {
      "Type": "Task",
      "Agent": "ContentGenerator",
      "Parameters": {
        "topic.$": "$.topic",
        "style.$": "$.style"
      },
      "ResultPath": "$.draft",
      "Next": "ModerateContent"
    },
    "ModerateContent": {
      "Type": "Task",
      "Agent": "ContentModerator",
      "Parameters": {
        "content.$": "$.draft"
      },
      "ResultPath": "$.moderation",
      "Next": "CheckModeration"
    },
    "CheckModeration": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.moderation.flagged",
          "BooleanEquals": true,
          "Next": "HumanReview"
        }
      ],
      "Default": "AutoPublish"
    },
    "HumanReview": {
      "Type": "Approval",
      "Prompt": {
        "Title": "Content flagged for review",
        "Description.$": "$.draft.title",
        "Flags.$": "$.moderation.flags"
      },
      "Options": ["approve", "edit", "reject"],
      "Timeout": "24h",
      "Escalation": {
        "After": "4h",
        "Notify": ["content-lead@example.com"]
      },
      "Editable": {
        "Fields": ["$.draft.content"],
        "ResultPath": "$.editedDraft"
      },
      "Choices": [
        {
          "Variable": "$.approval",
          "StringEquals": "approve",
          "Next": "Publish"
        },
        {
          "Variable": "$.approval",
          "StringEquals": "edit",
          "Next": "PublishEdited"
        }
      ],
      "Default": "Rejected"
    },
    "AutoPublish": {
      "Type": "Task",
      "Agent": "Publisher",
      "Parameters": {
        "content.$": "$.draft"
      },
      "End": true
    },
    "Publish": {
      "Type": "Task",
      "Agent": "Publisher",
      "Parameters": {
        "content.$": "$.draft"
      },
      "Feedback": {
        "Collect": true,
        "Type": "rating",
        "Scale": [1, 5]
      },
      "End": true
    },
    "PublishEdited": {
      "Type": "Task",
      "Agent": "Publisher",
      "Parameters": {
        "content.$": "$.editedDraft"
      },
      "End": true
    },
    "Rejected": {
      "Type": "Fail",
      "Error": "ContentRejected",
      "Cause": "Content failed human review"
    }
  }
}
```

## Best Practices

### 1. Clear, Actionable Prompts

```json
{
  "Prompt": "Approve deployment of v2.1.0 to production. Changes include: new payment flow, bug fixes."
}
```

### 2. Set Reasonable Timeouts

```json
{
  "Timeout": "24h",
  "Default": "AutoReject"
}
```

### 3. Use Escalation for Critical Items

```json
{
  "Escalation": {
    "After": "2h",
    "Notify": ["manager@example.com"]
  }
}
```

### 4. Collect Feedback for Improvement

```json
{
  "Feedback": {
    "Collect": true,
    "Store": "learning_db"
  }
}
```

### 5. Allow Edits When Appropriate

```json
{
  "Editable": {
    "Fields": ["$.content"],
    "ResultPath": "$.editedContent"
  }
}
```
