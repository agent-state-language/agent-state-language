# Tutorial 12: Building Skills

Learn how to create reusable, composable workflow templates.

## What You'll Learn

- Template creation
- Parameter substitution
- Workflow composition
- Version management

## Creating a Template

Create `templates/validation.asl.json`:

```json
{
  "Comment": "Reusable validation template",
  "Parameters": {
    "schema": {
      "Type": "string",
      "Required": true,
      "Description": "Schema to validate against"
    },
    "strict": {
      "Type": "boolean",
      "Default": true
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
      "End": true
    }
  }
}
```

## Using Templates

```json
{
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "notification": "./templates/notification.asl.json"
  },
  "StartAt": "ValidateInput",
  "States": {
    "ValidateInput": {
      "Type": "Include",
      "Template": "validation",
      "Parameters": {
        "schema": "user-input",
        "strict": true
      },
      "Next": "Process"
    },
    "Process": {
      "Type": "Task",
      "Agent": "Processor",
      "Next": "Notify"
    },
    "Notify": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email"
      },
      "End": true
    }
  }
}
```

## Pipeline Pattern

Chain templates for complex pipelines:

```json
{
  "Imports": {
    "extract": "./templates/extract.asl.json",
    "transform": "./templates/transform.asl.json",
    "load": "./templates/load.asl.json"
  },
  "StartAt": "Extract",
  "States": {
    "Extract": { "Type": "Include", "Template": "extract", "Next": "Transform" },
    "Transform": { "Type": "Include", "Template": "transform", "Next": "Load" },
    "Load": { "Type": "Include", "Template": "load", "End": true }
  }
}
```

## Version Management

```json
{
  "Imports": {
    "validation": "registry://myorg/validation@^2.0.0",
    "notification": "registry://myorg/notification@~1.5.0"
  }
}
```

## Summary

You've learned:

- ✅ Creating reusable templates
- ✅ Parameter substitution
- ✅ Template composition
- ✅ Version management

## Congratulations!

You've completed all 12 tutorials. You now have a solid foundation in Agent State Language!

### Next Steps

- Explore the [examples](../../examples/)
- Read the [full specification](../../SPECIFICATION.md)
- Check out the [best practices guide](../guides/best-practices.md)
