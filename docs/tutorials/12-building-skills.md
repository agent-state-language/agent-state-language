# Tutorial 12: Building Skills

Learn how to create reusable, composable workflow templates that you can share across projects.

## What You'll Learn

- Creating workflow templates with parameters
- Using template composition for complex pipelines
- Building a library of reusable skills
- Version management for templates
- Creating a complete template-based application

## Prerequisites

- Completed [Tutorial 11: Error Handling](11-error-handling.md)
- Understanding of all state types

## The Scenario

We'll build a library of reusable workflow "skills" for:

1. **Validation** - Reusable input validation
2. **Notification** - Multi-channel notifications
3. **Document Processing** - Extract, transform, load pipeline
4. **Approval** - Configurable approval workflows

Then we'll compose these into a complete document processing application.

## Step 1: Understanding Templates

Templates are parameterized workflow fragments that can be reused:

```
Template + Parameters → Resolved Workflow
```

### Template Benefits

| Benefit | Description |
|---------|-------------|
| **DRY** | Don't repeat workflow patterns |
| **Consistency** | Same logic across projects |
| **Testing** | Test once, use everywhere |
| **Versioning** | Track and upgrade dependencies |

## Step 2: Create Template Files

### Validation Template

Create `templates/validation.asl.json`:

```json
{
  "Comment": "Reusable validation skill",
  "Version": "1.0.0",
  "Parameters": {
    "schema": {
      "Type": "string",
      "Required": true,
      "Description": "Schema name to validate against"
    },
    "strict": {
      "Type": "boolean",
      "Default": true,
      "Description": "Whether to fail on extra fields"
    },
    "errorPath": {
      "Type": "string",
      "Default": "$.validationError",
      "Description": "Where to store validation errors"
    }
  },
  "StartAt": "Validate",
  "States": {
    "Validate": {
      "Type": "Task",
      "Agent": "ValidationAgent",
      "Parameters": {
        "input.$": "$",
        "schema": "{{schema}}",
        "strict": "{{strict}}"
      },
      "Catch": [
        {
          "ErrorEquals": ["ValidationError"],
          "ResultPath": "{{errorPath}}",
          "Next": "ValidationFailed"
        }
      ],
      "Next": "ValidationSuccess"
    },
    "ValidationSuccess": {
      "Type": "Pass",
      "Parameters": {
        "valid": true,
        "data.$": "$.validatedData"
      },
      "Output": "success"
    },
    "ValidationFailed": {
      "Type": "Pass",
      "Parameters": {
        "valid": false,
        "errors.$": "{{errorPath}}.errors"
      },
      "Output": "failure"
    }
  }
}
```

### Notification Template

Create `templates/notification.asl.json`:

```json
{
  "Comment": "Multi-channel notification skill",
  "Version": "1.0.0",
  "Parameters": {
    "channel": {
      "Type": "string",
      "Required": true,
      "Enum": ["email", "slack", "sms", "webhook"],
      "Description": "Notification channel"
    },
    "recipient": {
      "Type": "string",
      "Required": true,
      "Description": "Recipient address/ID"
    },
    "template": {
      "Type": "string",
      "Default": "default",
      "Description": "Message template name"
    },
    "priority": {
      "Type": "string",
      "Default": "normal",
      "Enum": ["low", "normal", "high", "urgent"]
    }
  },
  "StartAt": "PrepareMessage",
  "States": {
    "PrepareMessage": {
      "Type": "Task",
      "Agent": "TemplateAgent",
      "Parameters": {
        "template": "{{template}}",
        "data.$": "$",
        "channel": "{{channel}}"
      },
      "ResultPath": "$.message",
      "Next": "RouteChannel"
    },
    "RouteChannel": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "{{channel}}",
          "StringEquals": "email",
          "Next": "SendEmail"
        },
        {
          "Variable": "{{channel}}",
          "StringEquals": "slack",
          "Next": "SendSlack"
        },
        {
          "Variable": "{{channel}}",
          "StringEquals": "sms",
          "Next": "SendSMS"
        }
      ],
      "Default": "SendWebhook"
    },
    "SendEmail": {
      "Type": "Task",
      "Agent": "EmailAgent",
      "Parameters": {
        "to": "{{recipient}}",
        "subject.$": "$.message.subject",
        "body.$": "$.message.body",
        "priority": "{{priority}}"
      },
      "Next": "NotificationSent"
    },
    "SendSlack": {
      "Type": "Task",
      "Agent": "SlackAgent",
      "Parameters": {
        "channel": "{{recipient}}",
        "message.$": "$.message.body",
        "blocks.$": "$.message.blocks"
      },
      "Next": "NotificationSent"
    },
    "SendSMS": {
      "Type": "Task",
      "Agent": "SMSAgent",
      "Parameters": {
        "phone": "{{recipient}}",
        "message.$": "$.message.body"
      },
      "Next": "NotificationSent"
    },
    "SendWebhook": {
      "Type": "Task",
      "Agent": "WebhookAgent",
      "Parameters": {
        "url": "{{recipient}}",
        "payload.$": "$.message"
      },
      "Next": "NotificationSent"
    },
    "NotificationSent": {
      "Type": "Pass",
      "Parameters": {
        "sent": true,
        "channel": "{{channel}}",
        "recipient": "{{recipient}}",
        "sentAt.$": "$$.State.EnteredTime"
      },
      "Output": "complete"
    }
  }
}
```

### Document Processing Template

Create `templates/document-processing.asl.json`:

```json
{
  "Comment": "ETL pipeline for documents",
  "Version": "1.0.0",
  "Parameters": {
    "extractorAgent": {
      "Type": "string",
      "Default": "DefaultExtractor",
      "Description": "Agent for extraction"
    },
    "transformerAgent": {
      "Type": "string",
      "Default": "DefaultTransformer",
      "Description": "Agent for transformation"
    },
    "outputFormat": {
      "Type": "string",
      "Default": "json",
      "Enum": ["json", "csv", "xml"]
    },
    "parallel": {
      "Type": "boolean",
      "Default": false,
      "Description": "Process in parallel"
    }
  },
  "StartAt": "Extract",
  "States": {
    "Extract": {
      "Type": "Task",
      "Agent": "{{extractorAgent}}",
      "Parameters": {
        "document.$": "$.document",
        "options.$": "$.extractOptions"
      },
      "ResultPath": "$.extracted",
      "Next": "Transform"
    },
    "Transform": {
      "Type": "Task",
      "Agent": "{{transformerAgent}}",
      "Parameters": {
        "data.$": "$.extracted",
        "outputFormat": "{{outputFormat}}"
      },
      "ResultPath": "$.transformed",
      "Next": "FormatOutput"
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "result.$": "$.transformed.data",
        "format": "{{outputFormat}}",
        "metadata": {
          "extractedAt.$": "$.extracted.timestamp",
          "transformedAt.$": "$.transformed.timestamp"
        }
      },
      "Output": "complete"
    }
  }
}
```

## Step 3: Create Supporting Agents

### ValidationAgent

```php
<?php

namespace MyOrg\Skills;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;

class ValidationAgent implements AgentInterface
{
    private array $schemas = [
        'document' => [
            'required' => ['title', 'content'],
            'optional' => ['author', 'tags']
        ],
        'user' => [
            'required' => ['email', 'name'],
            'optional' => ['phone', 'address']
        ],
        'order' => [
            'required' => ['orderId', 'items', 'total'],
            'optional' => ['discount', 'notes']
        ]
    ];

    public function execute(array $parameters): array
    {
        $input = $parameters['input'] ?? [];
        $schemaName = $parameters['schema'] ?? 'default';
        $strict = $parameters['strict'] ?? true;
        
        $schema = $this->schemas[$schemaName] ?? ['required' => [], 'optional' => []];
        $errors = $this->validate($input, $schema, $strict);
        
        if (!empty($errors)) {
            throw new WorkflowException(
                'ValidationError',
                'Validation failed',
                ['errors' => $errors]
            );
        }
        
        return [
            'validatedData' => $input,
            'schema' => $schemaName,
            'strict' => $strict,
            'validatedAt' => date('c')
        ];
    }
    
    private function validate(array $input, array $schema, bool $strict): array
    {
        $errors = [];
        
        // Check required fields
        foreach ($schema['required'] as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // Check for extra fields in strict mode
        if ($strict) {
            $allowed = array_merge($schema['required'], $schema['optional']);
            foreach (array_keys($input) as $field) {
                if (!in_array($field, $allowed)) {
                    $errors[] = "Unexpected field: {$field}";
                }
            }
        }
        
        return $errors;
    }

    public function getName(): string
    {
        return 'ValidationAgent';
    }
}
```

### TemplateAgent

```php
<?php

namespace MyOrg\Skills;

use AgentStateLanguage\Agents\AgentInterface;

class TemplateAgent implements AgentInterface
{
    private array $templates = [
        'default' => [
            'subject' => 'Notification',
            'body' => 'You have a new notification.'
        ],
        'document_ready' => [
            'subject' => 'Document Processing Complete',
            'body' => 'Your document "{{title}}" has been processed successfully.'
        ],
        'approval_needed' => [
            'subject' => 'Approval Required',
            'body' => 'A document requires your approval: {{title}}'
        ],
        'error' => [
            'subject' => 'Processing Error',
            'body' => 'An error occurred: {{error}}'
        ]
    ];

    public function execute(array $parameters): array
    {
        $templateName = $parameters['template'] ?? 'default';
        $data = $parameters['data'] ?? [];
        $channel = $parameters['channel'] ?? 'email';
        
        $template = $this->templates[$templateName] ?? $this->templates['default'];
        
        // Replace placeholders
        $subject = $this->interpolate($template['subject'], $data);
        $body = $this->interpolate($template['body'], $data);
        
        return [
            'subject' => $subject,
            'body' => $body,
            'channel' => $channel,
            'template' => $templateName,
            'blocks' => $this->buildSlackBlocks($subject, $body)
        ];
    }
    
    private function interpolate(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            return $data[$matches[1]] ?? $matches[0];
        }, $text);
    }
    
    private function buildSlackBlocks(string $title, string $body): array
    {
        return [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $title]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $body]]
        ];
    }

    public function getName(): string
    {
        return 'TemplateAgent';
    }
}
```

### EmailAgent (Mock)

```php
<?php

namespace MyOrg\Skills;

use AgentStateLanguage\Agents\AgentInterface;

class EmailAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $to = $parameters['to'] ?? '';
        $subject = $parameters['subject'] ?? '';
        $body = $parameters['body'] ?? '';
        $priority = $parameters['priority'] ?? 'normal';
        
        // Simulate sending email
        $messageId = 'msg_' . uniqid();
        
        return [
            'sent' => true,
            'messageId' => $messageId,
            'to' => $to,
            'subject' => $subject,
            'priority' => $priority,
            'sentAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'EmailAgent';
    }
}
```

## Step 4: Compose into Complete Application

Create `document-workflow.asl.json`:

```json
{
  "Comment": "Complete document processing application using templates",
  "Version": "1.0.0",
  "Imports": {
    "validation": "./templates/validation.asl.json",
    "notification": "./templates/notification.asl.json",
    "docProcess": "./templates/document-processing.asl.json"
  },
  "StartAt": "ValidateInput",
  "States": {
    "ValidateInput": {
      "Type": "Include",
      "Template": "validation",
      "Parameters": {
        "schema": "document",
        "strict": true
      },
      "ResultPath": "$.validation",
      "Next": "CheckValidation"
    },
    "CheckValidation": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.validation.valid",
          "BooleanEquals": true,
          "Next": "ProcessDocument"
        }
      ],
      "Default": "NotifyValidationError"
    },
    "NotifyValidationError": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email",
        "recipient.$": "$.submittedBy",
        "template": "error",
        "priority": "high"
      },
      "End": true
    },
    "ProcessDocument": {
      "Type": "Include",
      "Template": "docProcess",
      "Parameters": {
        "extractorAgent": "PDFExtractor",
        "transformerAgent": "ContentTransformer",
        "outputFormat": "json"
      },
      "ResultPath": "$.processing",
      "Next": "RequireApproval"
    },
    "RequireApproval": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.requireApproval",
          "BooleanEquals": true,
          "Next": "WaitForApproval"
        }
      ],
      "Default": "NotifyComplete"
    },
    "WaitForApproval": {
      "Type": "Approval",
      "Prompt": {
        "Title": "Document Review Required",
        "Content.$": "$.processing.result"
      },
      "Options": ["approve", "reject", "revise"],
      "Timeout": "24h",
      "ResultPath": "$.approval",
      "Next": "HandleApproval"
    },
    "HandleApproval": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.approval.decision",
          "StringEquals": "approve",
          "Next": "NotifyApproved"
        },
        {
          "Variable": "$.approval.decision",
          "StringEquals": "revise",
          "Next": "NotifyRevision"
        }
      ],
      "Default": "NotifyRejected"
    },
    "NotifyComplete": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email",
        "recipient.$": "$.submittedBy",
        "template": "document_ready"
      },
      "ResultPath": "$.notification",
      "Next": "Success"
    },
    "NotifyApproved": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "slack",
        "recipient": "#documents",
        "template": "document_ready"
      },
      "Next": "Success"
    },
    "NotifyRevision": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email",
        "recipient.$": "$.submittedBy",
        "template": "revision_needed",
        "priority": "high"
      },
      "End": true
    },
    "NotifyRejected": {
      "Type": "Include",
      "Template": "notification",
      "Parameters": {
        "channel": "email",
        "recipient.$": "$.submittedBy",
        "template": "error",
        "priority": "normal"
      },
      "End": true
    },
    "Success": {
      "Type": "Pass",
      "Parameters": {
        "success": true,
        "documentId.$": "$.document.id",
        "result.$": "$.processing.result",
        "completedAt.$": "$$.State.EnteredTime"
      },
      "End": true
    }
  }
}
```

## Step 5: Run the Application

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\Skills\ValidationAgent;
use MyOrg\Skills\TemplateAgent;
use MyOrg\Skills\EmailAgent;
use MyOrg\Skills\PDFExtractorAgent;
use MyOrg\Skills\ContentTransformerAgent;

// Create registry with all required agents
$registry = new AgentRegistry();
$registry->register('ValidationAgent', new ValidationAgent());
$registry->register('TemplateAgent', new TemplateAgent());
$registry->register('EmailAgent', new EmailAgent());
$registry->register('PDFExtractor', new PDFExtractorAgent());
$registry->register('ContentTransformer', new ContentTransformerAgent());
$registry->register('SlackAgent', new SlackAgent());

// Load the composed workflow
$engine = WorkflowEngine::fromFile('document-workflow.asl.json', $registry);

// Process a document
$result = $engine->run([
    'document' => [
        'id' => 'doc_123',
        'title' => 'Quarterly Report Q4 2025',
        'content' => 'Full document content here...',
        'author' => 'John Smith'
    ],
    'submittedBy' => 'john@example.com',
    'requireApproval' => false
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "=== Document Processing Complete ===\n";
    echo "Success: " . ($output['success'] ? 'Yes' : 'No') . "\n";
    echo "Document ID: " . $output['documentId'] . "\n";
    echo "Completed At: " . $output['completedAt'] . "\n";
    
    echo "\n=== Template Usage ===\n";
    echo "Templates used:\n";
    echo "- validation (v1.0.0)\n";
    echo "- notification (v1.0.0)\n";
    echo "- document-processing (v1.0.0)\n";
} else {
    echo "Processing failed: " . $result->getError() . "\n";
}

// Show the resolved workflow (debugging)
echo "\n=== Resolved States ===\n";
foreach ($result->getTrace() as $entry) {
    if (isset($entry['stateName'])) {
        echo "- {$entry['stateName']}\n";
    }
}
```

## Expected Output

```
=== Document Processing Complete ===
Success: Yes
Document ID: doc_123
Completed At: 2025-01-20T10:30:00+00:00

=== Template Usage ===
Templates used:
- validation (v1.0.0)
- notification (v1.0.0)
- document-processing (v1.0.0)

=== Resolved States ===
- ValidateInput
- CheckValidation
- ProcessDocument
- RequireApproval
- NotifyComplete
- Success
```

## Template Configuration Reference

### Parameter Types

| Type | Description | Example |
|------|-------------|---------|
| `string` | Text value | `"email"` |
| `boolean` | True/false | `true` |
| `number` | Numeric value | `100` |
| `array` | List of values | `["a", "b"]` |
| `object` | Key-value pairs | `{"key": "value"}` |

### Parameter Options

```json
{
  "Parameters": {
    "channel": {
      "Type": "string",
      "Required": true,
      "Enum": ["email", "slack", "sms"],
      "Description": "Notification channel to use"
    },
    "retryCount": {
      "Type": "number",
      "Default": 3,
      "Min": 1,
      "Max": 10
    }
  }
}
```

### Version Management

```json
{
  "Imports": {
    "validation": "registry://myorg/validation@^2.0.0",
    "notification": "registry://myorg/notification@~1.5.0"
  }
}
```

| Version | Meaning |
|---------|---------|
| `@1.0.0` | Exact version |
| `@^1.0.0` | Compatible with 1.x |
| `@~1.5.0` | Compatible with 1.5.x |
| `@latest` | Latest version (risky) |

## Experiment

Try these modifications:

### Create a Retry Template

```json
{
  "Comment": "Reusable retry wrapper",
  "Parameters": {
    "maxAttempts": { "Type": "number", "Default": 3 },
    "backoffRate": { "Type": "number", "Default": 2.0 }
  },
  "States": {
    "Execute": {
      "Type": "Task",
      "Agent": "{{wrappedAgent}}",
      "Retry": [
        {
          "ErrorEquals": ["States.TaskFailed"],
          "MaxAttempts": "{{maxAttempts}}",
          "BackoffRate": "{{backoffRate}}"
        }
      ]
    }
  }
}
```

### Create a Caching Template

```json
{
  "Comment": "Cache-aware execution",
  "Parameters": {
    "ttl": { "Type": "string", "Default": "1h" },
    "cacheAgent": { "Type": "string", "Required": true }
  }
}
```

## Common Mistakes

### Missing Required Parameter

```json
{
  "Type": "Include",
  "Template": "validation",
  "Parameters": {
    "strict": true
  }
}
```

**Problem**: `schema` is required but not provided.

**Fix**: Always check template's required parameters.

### Circular Template Dependencies

```
template-a.asl.json imports template-b.asl.json
template-b.asl.json imports template-a.asl.json
```

**Problem**: Infinite loop during resolution.

**Fix**: Design templates as a DAG (directed acyclic graph).

### Hardcoded Paths in Templates

```json
{
  "ResultPath": "$.myResult"
}
```

**Problem**: May conflict with parent workflow paths.

**Fix**: Use parameterized paths or unique prefixes.

## Summary

You've learned:

- ✅ Creating reusable workflow templates
- ✅ Defining parameters with types and defaults
- ✅ Composing templates into applications
- ✅ Version management for templates
- ✅ Building a complete template library

## Congratulations!

You've completed all 12 tutorials! You now have a solid foundation in Agent State Language.

### What You've Mastered

| Tutorial | Skill |
|----------|-------|
| 1-2 | Basic workflows and data flow |
| 3-5 | Control flow, parallelism, recursion |
| 6-7 | Memory, context, and tools |
| 8-9 | Human approval and multi-agent debate |
| 10-11 | Cost management and error handling |
| 12 | Templates and composition |

### Next Steps

- Explore the [complete examples](../../examples/)
- Read the [full specification](../../SPECIFICATION.md)
- Check the [best practices guide](../guides/best-practices.md)
- Review [production deployment](../guides/production-deployment.md)
