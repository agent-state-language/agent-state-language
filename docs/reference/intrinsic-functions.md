# Intrinsic Functions Reference

Agent State Language provides built-in intrinsic functions for data transformation and manipulation within workflows.

## Overview

Intrinsic functions are called within JSONPath expressions using the `States.` prefix:

```json
{
  "Parameters": {
    "result.$": "States.FunctionName(arguments)"
  }
}
```

## String Functions

### States.Format

Formats a string using placeholders.

**Syntax:** `States.Format(template, arg1, arg2, ...)`

**Arguments:**
- `template`: String with `{}` placeholders
- `arg1, arg2, ...`: Values to insert

**Example:**

```json
{
  "Parameters": {
    "greeting.$": "States.Format('Hello, {}!', $.name)",
    "fullMessage.$": "States.Format('{} scored {} points', $.user, $.score)"
  }
}
```

### States.StringToJson

Parses a JSON string into an object.

**Syntax:** `States.StringToJson(jsonString)`

**Example:**

```json
{
  "Parameters": {
    "parsed.$": "States.StringToJson($.jsonPayload)"
  }
}
```

### States.JsonToString

Converts an object to a JSON string.

**Syntax:** `States.JsonToString(object)`

**Example:**

```json
{
  "Parameters": {
    "serialized.$": "States.JsonToString($.data)"
  }
}
```

### States.Base64Encode

Encodes a string to Base64.

**Syntax:** `States.Base64Encode(string)`

**Example:**

```json
{
  "Parameters": {
    "encoded.$": "States.Base64Encode($.credentials)"
  }
}
```

### States.Base64Decode

Decodes a Base64 string.

**Syntax:** `States.Base64Decode(base64String)`

**Example:**

```json
{
  "Parameters": {
    "decoded.$": "States.Base64Decode($.encodedData)"
  }
}
```

---

## Array Functions

### States.Array

Creates an array from the given arguments.

**Syntax:** `States.Array(item1, item2, ...)`

**Example:**

```json
{
  "Parameters": {
    "items.$": "States.Array($.first, $.second, $.third)"
  }
}
```

### States.ArrayPartition

Splits an array into chunks of specified size.

**Syntax:** `States.ArrayPartition(array, chunkSize)`

**Example:**

```json
{
  "Parameters": {
    "batches.$": "States.ArrayPartition($.allItems, 10)"
  }
}
```

Result: `[[item1...item10], [item11...item20], ...]`

### States.ArrayContains

Checks if an array contains a value.

**Syntax:** `States.ArrayContains(array, value)`

**Returns:** `true` or `false`

**Example:**

```json
{
  "CheckAdmin": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.ArrayContains($.roles, 'admin')",
        "BooleanEquals": true,
        "Next": "AdminPath"
      }
    ]
  }
}
```

### States.ArrayGetItem

Gets an item at a specific index.

**Syntax:** `States.ArrayGetItem(array, index)`

**Example:**

```json
{
  "Parameters": {
    "firstItem.$": "States.ArrayGetItem($.items, 0)",
    "lastItem.$": "States.ArrayGetItem($.items, -1)"
  }
}
```

### States.ArrayLength

Returns the length of an array.

**Syntax:** `States.ArrayLength(array)`

**Example:**

```json
{
  "CheckEmpty": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.ArrayLength($.items)",
        "NumericEquals": 0,
        "Next": "NoItems"
      }
    ]
  }
}
```

### States.ArrayRange

Creates an array of numbers in a range.

**Syntax:** `States.ArrayRange(start, end, step)`

**Example:**

```json
{
  "Parameters": {
    "indices.$": "States.ArrayRange(0, 10, 1)"
  }
}
```

Result: `[0, 1, 2, 3, 4, 5, 6, 7, 8, 9]`

### States.ArrayUnique

Returns an array with duplicate values removed.

**Syntax:** `States.ArrayUnique(array)`

**Example:**

```json
{
  "Parameters": {
    "uniqueTags.$": "States.ArrayUnique($.allTags)"
  }
}
```

### States.ArrayConcat

Concatenates multiple arrays into one.

**Syntax:** `States.ArrayConcat(array1, array2, ...)`

**Example:**

```json
{
  "Parameters": {
    "allItems.$": "States.ArrayConcat($.existingItems, $.newItems)"
  }
}
```

---

## Math Functions

### States.MathRandom

Generates a random number between 0 and 1.

**Syntax:** `States.MathRandom()`

**Example:**

```json
{
  "Parameters": {
    "random.$": "States.MathRandom()"
  }
}
```

### States.MathAdd

Adds two numbers.

**Syntax:** `States.MathAdd(num1, num2)`

**Example:**

```json
{
  "Parameters": {
    "total.$": "States.MathAdd($.subtotal, $.tax)"
  }
}
```

### States.MathSubtract

Subtracts the second number from the first.

**Syntax:** `States.MathSubtract(num1, num2)`

**Example:**

```json
{
  "Parameters": {
    "difference.$": "States.MathSubtract($.total, $.discount)"
  }
}
```

### States.MathMultiply

Multiplies two numbers.

**Syntax:** `States.MathMultiply(num1, num2)`

**Example:**

```json
{
  "Parameters": {
    "total.$": "States.MathMultiply($.quantity, $.price)"
  }
}
```

---

## Hash Functions

### States.Hash

Calculates a cryptographic hash.

**Syntax:** `States.Hash(data, algorithm)`

**Algorithms:** `MD5`, `SHA-1`, `SHA-256`, `SHA-384`, `SHA-512`

**Example:**

```json
{
  "Parameters": {
    "checksum.$": "States.Hash($.content, 'SHA-256')"
  }
}
```

---

## Utility Functions

### States.UUID

Generates a unique identifier.

**Syntax:** `States.UUID()`

**Example:**

```json
{
  "Parameters": {
    "requestId.$": "States.UUID()"
  }
}
```

---

## Agent-Native Functions (ASL Extensions)

These functions are specific to Agent State Language and not part of AWS ASL.

### States.TokenCount

Estimates token count for a string.

**Syntax:** `States.TokenCount(text)`

**Example:**

```json
{
  "CheckTokens": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.TokenCount($.prompt)",
        "NumericGreaterThan": 4000,
        "Next": "SummarizeFirst"
      }
    ],
    "Default": "DirectProcess"
  }
}
```

### States.Truncate

Truncates text to a maximum token count.

**Syntax:** `States.Truncate(text, maxTokens)`

**Example:**

```json
{
  "Parameters": {
    "limitedContext.$": "States.Truncate($.fullContext, 2000)"
  }
}
```

### States.CurrentCost

Returns the current workflow execution cost.

**Syntax:** `States.CurrentCost()`

**Example:**

```json
{
  "CheckBudget": {
    "Type": "Choice",
    "Choices": [
      {
        "Variable.$": "States.CurrentCost()",
        "NumericGreaterThan": 5.0,
        "Next": "BudgetExceeded"
      }
    ],
    "Default": "Continue"
  }
}
```

### States.CurrentTokens

Returns total tokens used in execution.

**Syntax:** `States.CurrentTokens()`

**Example:**

```json
{
  "Parameters": {
    "usedTokens.$": "States.CurrentTokens()"
  }
}
```

### States.Merge

Deep merges multiple objects.

**Syntax:** `States.Merge(obj1, obj2, ...)`

**Example:**

```json
{
  "Parameters": {
    "combined.$": "States.Merge($.defaults, $.userConfig, $.overrides)"
  }
}
```

### States.Pick

Picks specific fields from an object.

**Syntax:** `States.Pick(object, field1, field2, ...)`

**Example:**

```json
{
  "Parameters": {
    "subset.$": "States.Pick($.user, 'id', 'name', 'email')"
  }
}
```

### States.Omit

Omits specific fields from an object.

**Syntax:** `States.Omit(object, field1, field2, ...)`

**Example:**

```json
{
  "Parameters": {
    "sanitized.$": "States.Omit($.data, 'password', 'apiKey')"
  }
}
```

---

## Complex Examples

### Building a Dynamic Request

```json
{
  "PrepareAPICall": {
    "Type": "Pass",
    "Parameters": {
      "request": {
        "id.$": "States.UUID()",
        "timestamp.$": "$$.State.EnteredTime",
        "endpoint.$": "States.Format('{}/api/v1/{}', $.baseUrl, $.resource)",
        "headers": {
          "Authorization.$": "States.Format('Bearer {}', $.token)",
          "X-Request-ID.$": "States.UUID()"
        },
        "body.$": "States.JsonToString($.payload)"
      }
    },
    "Next": "ExecuteCall"
  }
}
```

### Conditional Processing Based on Array Contents

```json
{
  "RouteByRoles": {
    "Type": "Choice",
    "Choices": [
      {
        "And": [
          {
            "Variable.$": "States.ArrayContains($.user.roles, 'admin')",
            "BooleanEquals": true
          },
          {
            "Variable.$": "States.ArrayLength($.pendingApprovals)",
            "NumericGreaterThan": 0
          }
        ],
        "Next": "AdminWithApprovals"
      },
      {
        "Variable.$": "States.ArrayContains($.user.roles, 'moderator')",
        "BooleanEquals": true,
        "Next": "ModeratorPath"
      }
    ],
    "Default": "StandardUser"
  }
}
```

### Batching for Parallel Processing

```json
{
  "BatchProcess": {
    "Type": "Map",
    "ItemsPath.$": "States.ArrayPartition($.allItems, 100)",
    "MaxConcurrency": 5,
    "Iterator": {
      "StartAt": "ProcessBatch",
      "States": {
        "ProcessBatch": {
          "Type": "Task",
          "Agent": "BatchProcessor",
          "Parameters": {
            "batchId.$": "States.UUID()",
            "items.$": "$$.Map.Item.Value",
            "batchIndex.$": "$$.Map.Item.Index"
          },
          "End": true
        }
      }
    },
    "Next": "Aggregate"
  }
}
```
