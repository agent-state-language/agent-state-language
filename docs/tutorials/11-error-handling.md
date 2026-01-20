# Tutorial 11: Error Handling

Learn how to handle errors gracefully in your workflows.

## What You'll Learn

- Retry configurations
- Catch handlers
- Fallback patterns
- Timeout management

## Basic Retry

```json
{
  "UnreliableService": {
    "Type": "Task",
    "Agent": "FlakeyAPI",
    "Retry": [
      {
        "ErrorEquals": ["States.Timeout", "States.TaskFailed"],
        "MaxAttempts": 3,
        "IntervalSeconds": 5,
        "BackoffRate": 2.0
      }
    ]
  }
}
```

## Multiple Retry Policies

```json
{
  "Retry": [
    {
      "ErrorEquals": ["States.RateLimitExceeded"],
      "IntervalSeconds": 30,
      "MaxAttempts": 5
    },
    {
      "ErrorEquals": ["States.Timeout"],
      "IntervalSeconds": 5,
      "MaxAttempts": 2
    }
  ]
}
```

## Catch Handlers

```json
{
  "RiskyOperation": {
    "Type": "Task",
    "Agent": "RiskyAgent",
    "Catch": [
      {
        "ErrorEquals": ["ValidationError"],
        "ResultPath": "$.error",
        "Next": "HandleValidation"
      },
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.error",
        "Next": "CatchAll"
      }
    ]
  }
}
```

## Timeouts

```json
{
  "LongRunningTask": {
    "Type": "Task",
    "Agent": "SlowProcessor",
    "TimeoutSeconds": 300,
    "HeartbeatSeconds": 60
  }
}
```

## Fallback Pattern

```json
{
  "TryPrimary": {
    "Type": "Task",
    "Agent": "PrimaryService",
    "Catch": [
      { "ErrorEquals": ["States.ALL"], "Next": "TrySecondary" }
    ],
    "Next": "UseResult"
  },
  "TrySecondary": {
    "Type": "Task",
    "Agent": "BackupService",
    "Catch": [
      { "ErrorEquals": ["States.ALL"], "Next": "UseDefault" }
    ],
    "Next": "UseResult"
  }
}
```

## Summary

You've learned:

- ✅ Retry with exponential backoff
- ✅ Multiple retry policies
- ✅ Catch handlers
- ✅ Timeouts and heartbeats
- ✅ Fallback patterns
