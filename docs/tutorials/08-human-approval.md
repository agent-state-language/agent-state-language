# Tutorial 8: Human Approval

Learn how to integrate human oversight into your agent workflows.

## What You'll Learn

- Approval states
- Timeout and escalation
- Routing based on decisions
- Feedback collection

## Basic Approval

```json
{
  "ReviewChanges": {
    "Type": "Approval",
    "Prompt": "Review the proposed changes",
    "Next": "ApplyChanges"
  }
}
```

## With Options

```json
{
  "CodeReview": {
    "Type": "Approval",
    "Prompt": "Review PR #{{$.prNumber}}",
    "Options": ["approve", "request_changes", "comment", "reject"],
    "ResultPath": "$.review",
    "Next": "ProcessReview"
  }
}
```

## Timeout and Escalation

```json
{
  "UrgentReview": {
    "Type": "Approval",
    "Prompt": "Critical security issue requires review",
    "Timeout": "4h",
    "Escalation": {
      "After": "1h",
      "Notify": ["security-team@example.com"]
    },
    "Default": "AutoReject"
  }
}
```

## Decision Routing

```json
{
  "DeploymentApproval": {
    "Type": "Approval",
    "Prompt": "Approve deployment to production?",
    "Options": ["approve", "delay", "reject"],
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

## Editable Approvals

```json
{
  "ReviewEmail": {
    "Type": "Approval",
    "Prompt": "Review and edit the email",
    "Options": ["send", "edit", "discard"],
    "Editable": {
      "Fields": ["$.email.subject", "$.email.body"],
      "ResultPath": "$.editedEmail"
    }
  }
}
```

## Summary

You've learned:

- ✅ Basic approval states
- ✅ Custom options
- ✅ Timeout and escalation
- ✅ Decision-based routing
- ✅ Editable content approvals
