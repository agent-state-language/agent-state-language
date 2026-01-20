# 10. Composition

This section covers how to build complex workflows from reusable components.

## Overview

ASL supports workflow composition through:

- **Imports** - Include external workflow definitions
- **Templates** - Parameterized workflow fragments
- **Nesting** - Workflows that invoke other workflows
- **Modules** - Organized collections of reusable states

## Imports

Import external workflow definitions:

```json
{
  "Comment": "Main workflow with imports",
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "notification": "./templates/notification.asl.json",
    "errorHandling": "./templates/error-handling.asl.json"
  },
  "StartAt": "Begin",
  "States": { ... }
}
```

### Import Sources

| Source | Example |
|--------|---------|
| Relative path | `"./templates/foo.asl.json"` |
| Absolute path | `"/shared/workflows/foo.asl.json"` |
| URL | `"https://example.com/workflows/foo.asl.json"` |
| Registry | `"registry://org/workflow@1.0.0"` |

## Include State

Use imported templates within your workflow:

```json
{
  "ValidateInput": {
    "Type": "Include",
    "Template": "validation",
    "Parameters": {
      "schema": "user-input",
      "strict": true
    },
    "ResultPath": "$.validationResult",
    "Next": "ProcessData"
  }
}
```

### Include Fields

| Field | Type | Description |
|-------|------|-------------|
| `Type` | `"Include"` | State type |
| `Template` | string | Name from Imports |
| `Parameters` | object | Template parameters |
| `ResultPath` | string | Where to store result |
| `Next` | string | Next state |
| `End` | boolean | Terminal state |

## Template Definition

Create reusable template files:

**templates/validation.asl.json**
```json
{
  "Comment": "Reusable validation template",
  "Parameters": {
    "schema": {
      "Type": "string",
      "Description": "Schema name to validate against",
      "Required": true
    },
    "strict": {
      "Type": "boolean",
      "Description": "Fail on any validation error",
      "Default": true
    },
    "errorHandler": {
      "Type": "string",
      "Description": "State to transition to on error",
      "Default": null
    }
  },
  "StartAt": "Validate",
  "States": {
    "Validate": {
      "Type": "Task",
      "Agent": "Validator",
      "Parameters": {
        "input.$": "$",
        "schema": "{{schema}}",
        "strict": "{{strict}}"
      },
      "Catch": [
        {
          "ErrorEquals": ["ValidationError"],
          "Next": "{{errorHandler | default: 'ValidationFailed'}}"
        }
      ],
      "End": true
    },
    "ValidationFailed": {
      "Type": "Fail",
      "Error": "ValidationError",
      "Cause": "Input failed validation"
    }
  }
}
```

### Template Parameters

| Field | Type | Description |
|-------|------|-------------|
| `Type` | string | Parameter type |
| `Description` | string | Human-readable description |
| `Required` | boolean | Whether parameter is required |
| `Default` | any | Default value if not provided |
| `Enum` | array | Allowed values |

## Parameter Substitution

Use `{{paramName}}` syntax for substitution:

```json
{
  "Parameters": {
    "maxRetries": {
      "Type": "integer",
      "Default": 3
    }
  },
  "States": {
    "CallAPI": {
      "Type": "Task",
      "Agent": "{{agentName}}",
      "Retry": [
        {
          "ErrorEquals": ["States.ALL"],
          "MaxAttempts": "{{maxRetries}}"
        }
      ]
    }
  }
}
```

### Parameter Expressions

| Expression | Description |
|------------|-------------|
| `{{param}}` | Simple substitution |
| `{{param \| default: 'value'}}` | With default |
| `{{param \| upper}}` | Transform to uppercase |
| `{{param \| json}}` | JSON encode |

## Nested Workflows

Invoke a complete workflow as a single state:

```json
{
  "ProcessSubWorkflow": {
    "Type": "Workflow",
    "WorkflowId": "sub-workflow-definition",
    "Input": {
      "data.$": "$.inputData"
    },
    "ResultPath": "$.subResult",
    "Next": "Continue"
  }
}
```

### Workflow State Fields

| Field | Type | Description |
|-------|------|-------------|
| `Type` | `"Workflow"` | State type |
| `WorkflowId` | string | Workflow identifier |
| `Input` | object | Input to pass |
| `ResultPath` | string | Where to store result |
| `Timeout` | string | Maximum execution time |
| `Retry` | array | Retry configuration |
| `Catch` | array | Error handlers |

## State Modules

Group related states into modules:

**modules/code-review.asl.json**
```json
{
  "Comment": "Code review module",
  "Module": true,
  "Exports": ["ReviewCode", "SummarizeReview"],
  "States": {
    "ReviewCode": {
      "Type": "Task",
      "Agent": "CodeReviewer",
      "Parameters": {
        "code.$": "$.code",
        "language.$": "$.language"
      },
      "ResultPath": "$.review"
    },
    "SummarizeReview": {
      "Type": "Task",
      "Agent": "Summarizer",
      "Parameters": {
        "review.$": "$.review"
      },
      "ResultPath": "$.summary"
    }
  }
}
```

### Using Modules

```json
{
  "Imports": {
    "codeReview": "./modules/code-review.asl.json"
  },
  "States": {
    "PerformReview": {
      "Type": "Include",
      "Template": "codeReview",
      "EntryPoint": "ReviewCode",
      "ExitPoint": "SummarizeReview",
      "Next": "Continue"
    }
  }
}
```

## Composition Patterns

### Pipeline Pattern

Chain multiple templates:

```json
{
  "Imports": {
    "extract": "./templates/extract.asl.json",
    "transform": "./templates/transform.asl.json",
    "load": "./templates/load.asl.json"
  },
  "StartAt": "Extract",
  "States": {
    "Extract": {
      "Type": "Include",
      "Template": "extract",
      "Next": "Transform"
    },
    "Transform": {
      "Type": "Include",
      "Template": "transform",
      "Next": "Load"
    },
    "Load": {
      "Type": "Include",
      "Template": "load",
      "End": true
    }
  }
}
```

### Decorator Pattern

Wrap a core workflow with additional behavior:

**templates/with-retry.asl.json**
```json
{
  "Parameters": {
    "workflow": { "Type": "string", "Required": true },
    "maxRetries": { "Type": "integer", "Default": 3 }
  },
  "StartAt": "ExecuteWithRetry",
  "States": {
    "ExecuteWithRetry": {
      "Type": "Workflow",
      "WorkflowId": "{{workflow}}",
      "Retry": [
        {
          "ErrorEquals": ["States.ALL"],
          "MaxAttempts": "{{maxRetries}}",
          "BackoffRate": 2.0
        }
      ],
      "End": true
    }
  }
}
```

### Fan-Out Pattern

```json
{
  "FanOut": {
    "Type": "Parallel",
    "Branches": [
      {
        "StartAt": "ProcessA",
        "States": {
          "ProcessA": {
            "Type": "Include",
            "Template": "processor",
            "Parameters": { "type": "A" },
            "End": true
          }
        }
      },
      {
        "StartAt": "ProcessB",
        "States": {
          "ProcessB": {
            "Type": "Include",
            "Template": "processor",
            "Parameters": { "type": "B" },
            "End": true
          }
        }
      }
    ],
    "Next": "Aggregate"
  }
}
```

## Version Management

### Semantic Versioning

```json
{
  "Imports": {
    "validation": "registry://myorg/validation@^2.0.0",
    "notification": "registry://myorg/notification@~1.5.0"
  }
}
```

### Version Constraints

| Constraint | Description |
|------------|-------------|
| `1.0.0` | Exact version |
| `^1.0.0` | Compatible with 1.x.x |
| `~1.0.0` | Patch updates only |
| `>=1.0.0` | Minimum version |
| `latest` | Most recent version |

### Lock Files

Generate and use lock files for reproducibility:

```json
{
  "_lock": {
    "validation": "registry://myorg/validation@2.1.3",
    "notification": "registry://myorg/notification@1.5.7"
  }
}
```

## Overrides

Override template behavior:

```json
{
  "ValidateInput": {
    "Type": "Include",
    "Template": "validation",
    "Parameters": {
      "schema": "custom"
    },
    "Override": {
      "States": {
        "Validate": {
          "TimeoutSeconds": 60,
          "Retry": [
            {
              "ErrorEquals": ["States.Timeout"],
              "MaxAttempts": 2
            }
          ]
        }
      }
    },
    "Next": "Continue"
  }
}
```

## Complete Example

**main.asl.json**
```json
{
  "Comment": "Document processing pipeline",
  "Version": "1.0",
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "extraction": "./templates/extraction.asl.json",
    "analysis": "./templates/analysis.asl.json",
    "notification": "./templates/notification.asl.json"
  },
  "StartAt": "ValidateInput",
  "States": {
    "ValidateInput": {
      "Type": "Include",
      "Template": "validation",
      "Parameters": {
        "schema": "document-input",
        "strict": true
      },
      "Next": "ExtractContent"
    },
    "ExtractContent": {
      "Type": "Include",
      "Template": "extraction",
      "Parameters": {
        "extractImages": true,
        "extractTables": true
      },
      "ResultPath": "$.extracted",
      "Next": "AnalyzeInParallel"
    },
    "AnalyzeInParallel": {
      "Type": "Parallel",
      "Branches": [
        {
          "StartAt": "SentimentAnalysis",
          "States": {
            "SentimentAnalysis": {
              "Type": "Include",
              "Template": "analysis",
              "Parameters": { "type": "sentiment" },
              "End": true
            }
          }
        },
        {
          "StartAt": "EntityExtraction",
          "States": {
            "EntityExtraction": {
              "Type": "Include",
              "Template": "analysis",
              "Parameters": { "type": "entities" },
              "End": true
            }
          }
        }
      ],
      "ResultPath": "$.analyses",
      "Next": "Notify"
    },
    "Notify": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email",
        "recipients.$": "$.config.notifyEmails"
      },
      "End": true
    }
  }
}
```

**templates/analysis.asl.json**
```json
{
  "Comment": "Generic analysis template",
  "Parameters": {
    "type": {
      "Type": "string",
      "Required": true,
      "Enum": ["sentiment", "entities", "topics", "summary"]
    },
    "depth": {
      "Type": "string",
      "Default": "standard",
      "Enum": ["quick", "standard", "deep"]
    }
  },
  "StartAt": "RunAnalysis",
  "States": {
    "RunAnalysis": {
      "Type": "Task",
      "Agent": "Analyzer",
      "Parameters": {
        "content.$": "$.extracted.text",
        "analysisType": "{{type}}",
        "depth": "{{depth}}"
      },
      "End": true
    }
  }
}
```

## Best Practices

### 1. Keep Templates Focused

Each template should do one thing well:

```json
{
  "Comment": "Single-purpose validation template"
}
```

### 2. Use Semantic Versioning

```json
{
  "Version": "2.1.0"
}
```

### 3. Document Parameters

```json
{
  "Parameters": {
    "timeout": {
      "Type": "integer",
      "Description": "Maximum execution time in seconds",
      "Default": 60
    }
  }
}
```

### 4. Provide Sensible Defaults

```json
{
  "Parameters": {
    "retries": { "Default": 3 },
    "strict": { "Default": true }
  }
}
```

### 5. Use Explicit Exports

```json
{
  "Module": true,
  "Exports": ["MainEntry", "AlternateEntry"]
}
```

### 6. Test Templates Independently

Create test workflows for each template to verify behavior in isolation.
