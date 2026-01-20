# JSONPath Reference

Agent State Language uses JSONPath expressions to reference and manipulate data within workflows.

## Basic Syntax

JSONPath expressions always start with `$`, which represents the root of the current state's input data.

### Root Reference

```
$           → The entire input object
```

### Property Access

```
$.name      → The "name" property
$.user.name → Nested property access
```

### Array Access

```
$.items[0]      → First element
$.items[-1]     → Last element
$.items[1:3]    → Elements 1 and 2 (slice)
$.items[*]      → All elements
```

## Parameter Syntax

In ASL, JSONPath expressions are indicated with a `.$` suffix on parameter keys:

```json
{
  "Parameters": {
    "staticValue": "hello",
    "dynamicValue.$": "$.someField"
  }
}
```

- `"staticValue": "hello"` → Static string "hello"
- `"dynamicValue.$": "$.someField"` → Value from input at `$.someField`

## Context Object (`$$`)

The double-dollar prefix (`$$`) accesses the workflow context object:

### Available Context Fields

| Path | Description |
|------|-------------|
| `$$.Execution.Id` | Unique workflow execution ID |
| `$$.Execution.Name` | Workflow name |
| `$$.Execution.StartTime` | ISO 8601 start timestamp |
| `$$.State.Name` | Current state name |
| `$$.State.EnteredTime` | When current state began |
| `$$.State.RetryCount` | Current retry attempt (0-based) |
| `$$.Map.Item.Index` | Current index in Map iteration |
| `$$.Map.Item.Value` | Current item in Map iteration |

### Example

```json
{
  "LogProgress": {
    "Type": "Pass",
    "Parameters": {
      "executionId.$": "$$.Execution.Id",
      "currentState.$": "$$.State.Name",
      "timestamp.$": "$$.State.EnteredTime",
      "itemIndex.$": "$$.Map.Item.Index"
    },
    "Next": "Continue"
  }
}
```

## Path Operators

### InputPath

Filters the input before processing:

```json
{
  "ProcessUser": {
    "Type": "Task",
    "Agent": "UserProcessor",
    "InputPath": "$.user",
    "Next": "Done"
  }
}
```

Input: `{ "user": { "name": "Alice" }, "other": "data" }`
Agent receives: `{ "name": "Alice" }`

### ResultPath

Specifies where to place the result:

```json
{
  "Analyze": {
    "Type": "Task",
    "Agent": "Analyzer",
    "ResultPath": "$.analysis"
  }
}
```

Original input is preserved, result added at `$.analysis`.

### Special ResultPath Values

- `"$"` → Replace entire state with result
- `null` → Discard result, keep original input

### OutputPath

Filters the output after processing:

```json
{
  "ExtractName": {
    "Type": "Pass",
    "OutputPath": "$.user.name",
    "Next": "Done"
  }
}
```

State: `{ "user": { "name": "Alice", "age": 30 } }`
Output: `"Alice"`

## Parameters and ResultSelector

### Parameters

Creates a new object with static and dynamic values:

```json
{
  "PrepareRequest": {
    "Type": "Pass",
    "Parameters": {
      "method": "POST",
      "url.$": "$.endpoint",
      "headers": {
        "Content-Type": "application/json",
        "Authorization.$": "$.authToken"
      },
      "body.$": "$.payload"
    },
    "Next": "SendRequest"
  }
}
```

### ResultSelector

Transforms the result before applying ResultPath:

```json
{
  "CallAPI": {
    "Type": "Task",
    "Agent": "APIAgent",
    "ResultSelector": {
      "statusCode.$": "$.response.status",
      "data.$": "$.response.body",
      "timestamp.$": "$$.State.EnteredTime"
    },
    "ResultPath": "$.apiResult",
    "Next": "Process"
  }
}
```

## Intrinsic Functions

ASL provides built-in functions for data transformation:

### States.Format

Format strings with placeholders:

```json
{
  "Parameters": {
    "message.$": "States.Format('Hello, {}!', $.name)"
  }
}
```

### States.StringToJson

Parse JSON string:

```json
{
  "Parameters": {
    "data.$": "States.StringToJson($.jsonString)"
  }
}
```

### States.JsonToString

Convert object to JSON string:

```json
{
  "Parameters": {
    "serialized.$": "States.JsonToString($.data)"
  }
}
```

### States.Array

Create an array from arguments:

```json
{
  "Parameters": {
    "items.$": "States.Array($.first, $.second, $.third)"
  }
}
```

### States.ArrayPartition

Split array into chunks:

```json
{
  "Parameters": {
    "batches.$": "States.ArrayPartition($.items, 10)"
  }
}
```

### States.ArrayContains

Check if array contains value:

```json
{
  "Choices": [
    {
      "Variable.$": "States.ArrayContains($.roles, 'admin')",
      "BooleanEquals": true,
      "Next": "AdminPath"
    }
  ]
}
```

### States.ArrayLength

Get array length:

```json
{
  "Choices": [
    {
      "Variable.$": "States.ArrayLength($.items)",
      "NumericGreaterThan": 0,
      "Next": "HasItems"
    }
  ]
}
```

### States.ArrayGetItem

Get item at index:

```json
{
  "Parameters": {
    "first.$": "States.ArrayGetItem($.items, 0)"
  }
}
```

### States.UUID

Generate a UUID:

```json
{
  "Parameters": {
    "id.$": "States.UUID()"
  }
}
```

### States.Hash

Calculate hash:

```json
{
  "Parameters": {
    "hash.$": "States.Hash($.data, 'SHA-256')"
  }
}
```

## Examples

### Complex Data Transformation

```json
{
  "TransformData": {
    "Type": "Pass",
    "Parameters": {
      "requestId.$": "States.UUID()",
      "timestamp.$": "$$.State.EnteredTime",
      "user": {
        "id.$": "$.userId",
        "fullName.$": "States.Format('{} {}', $.firstName, $.lastName)"
      },
      "items.$": "$.orderItems",
      "itemCount.$": "States.ArrayLength($.orderItems)"
    },
    "Next": "Process"
  }
}
```

### Conditional Path Selection

```json
{
  "CheckAccess": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.ArrayContains($.user.roles, 'admin')",
        "BooleanEquals": true,
        "Next": "AdminAccess"
      },
      {
        "Variable": "$.user.isVerified",
        "BooleanEquals": true,
        "Next": "VerifiedAccess"
      }
    ],
    "Default": "BasicAccess"
  }
}
```

### Map Iteration with Context

```json
{
  "ProcessItems": {
    "Type": "Map",
    "ItemsPath": "$.items",
    "ItemSelector": {
      "item.$": "$$.Map.Item.Value",
      "index.$": "$$.Map.Item.Index",
      "batchId.$": "$.batchId",
      "totalItems.$": "States.ArrayLength($.items)"
    },
    "Iterator": {
      "StartAt": "ProcessItem",
      "States": {
        "ProcessItem": {
          "Type": "Task",
          "Agent": "ItemProcessor",
          "End": true
        }
      }
    }
  }
}
```
