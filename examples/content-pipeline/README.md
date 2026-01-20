# Content Pipeline Example

A complete content creation workflow from generation to publishing with moderation and optimization.

## Features

- **AI Generation** - Creates content based on topic, tone, and target audience
- **Content Moderation** - Automatic harmful content detection and PII redaction
- **Human Review** - Flagged content reviewed by editors
- **Parallel Optimization** - SEO, metadata, and images generated simultaneously
- **Publishing Options** - Publish immediately, schedule, or save as draft

## Pipeline Diagram

```
ParseRequest → GenerateContent → ModerateContent → CheckResult
                     ↓                                ↓
               (with reasoning)          ┌────────────┼────────────┐
                                      Blocked      Flagged      Clean
                                         ↓            ↓            ↓
                                   Regenerate   HumanReview   OptimizeContent
                                                                    ↓
                                                          ParallelOptimization
                                                          ┌────┼────┐
                                                        SEO  Metadata  Images
                                                                    ↓
                                                          AssembleContent
                                                                    ↓
                                                          FinalApproval
                                                     ┌──────┼──────┐
                                                  Publish Schedule  Draft
```

## Quick Start

```bash
# From the examples/content-pipeline directory
php run.php
```

## Expected Output

```
=== Content Pipeline Workflow Example ===

Test 1: Standard Blog Post Generation
---------------------------------------------
Status: Published
Title: In this comprehensive guide we explore best
URL: https://example.com/content/pub_abc123
SEO Score: 72

Test 2: Casual Short Article
---------------------------------------------
Status: Published
Title: Let's talk about coffee brewing techniques

Test 3: Professional Long-Form Content
---------------------------------------------
Status: Published
Title: This article provides an in depth analysis
Word Count: Approximately 180 words

=== Pipeline Execution Complete ===
```

## Using in Your Project

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register your agents
$registry = new AgentRegistry();
$registry->register('ContentGenerator', new YourContentGeneratorAgent());
$registry->register('ContentModerator', new YourContentModeratorAgent());
$registry->register('SEOOptimizer', new YourSEOOptimizerAgent());
$registry->register('MetadataGenerator', new YourMetadataGeneratorAgent());
$registry->register('ImageGenerator', new YourImageGeneratorAgent());
$registry->register('ContentAssembler', new YourContentAssemblerAgent());
$registry->register('Publisher', new YourPublisherAgent());
$registry->register('Scheduler', new YourSchedulerAgent());
$registry->register('DraftSaver', new YourDraftSaverAgent());
$registry->register('Notifier', new YourNotifierAgent());

// Load and run the workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'topic' => 'Best practices for remote work',
    'contentType' => 'blog_post',
    'targetAudience' => 'professionals',
    'tone' => 'informative',
    'length' => 'medium'
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "Published: " . $output['url'] . "\n";
}
```

## Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `topic` | string | Yes | Main subject of the content |
| `contentType` | string | Yes | Type: `blog_post`, `article`, `whitepaper` |
| `targetAudience` | string | Yes | Who the content is for |
| `tone` | string | Yes | Writing style: `informative`, `casual`, `professional` |
| `length` | string | Yes | Content length: `short`, `medium`, `long` |

## Content Moderation

The workflow includes automatic content moderation with three types of rules:

| Rule Type | Action | Description |
|-----------|--------|-------------|
| `semantic` | Block | Blocks harmful or inappropriate content |
| `pii` | Redact | Automatically redacts personal information |
| `regex` | Flag | Flags content for human review |

### Moderation Flow

1. **Blocked Content** → Content is regenerated with feedback
2. **Flagged Content** → Sent to human review queue
3. **Clean Content** → Proceeds to optimization

## Guardrails Configuration

```json
{
  "Guardrails": {
    "Output": {
      "Rules": [
        { "Type": "semantic", "Check": "harmful_content", "Action": "block" },
        { "Type": "pii", "Detect": true, "Action": "redact" },
        { "Type": "regex", "Pattern": "competitor_names", "Action": "flag" }
      ]
    }
  }
}
```

## Agents Required

| Agent | Purpose | Output |
|-------|---------|--------|
| ContentGenerator | Creates initial content | `{ content, wordCount }` |
| ContentModerator | Checks for violations | `{ blocked, flagged, issues }` |
| SEOOptimizer | Search optimization | `{ score, suggestions }` |
| MetadataGenerator | Titles, descriptions, tags | `{ title, description, tags }` |
| ImageGenerator | Hero and thumbnail images | `{ images[] }` |
| ContentAssembler | Combines all elements | `{ preview, metadata }` |
| Publisher | Immediate publishing | `{ url, publishId }` |
| Scheduler | Future scheduling | `{ scheduledFor }` |
| DraftSaver | Save for later | `{ draftId }` |
| Notifier | Send notifications | `{ message }` |

## Budget Configuration

The workflow includes a $1.50 budget limit with alerts:

```json
{
  "Budget": {
    "MaxCost": "$1.50",
    "Alerts": [
      { "At": "75%", "Notify": ["content-team@example.com"] }
    ]
  }
}
```

## Publishing Options

After final approval, content can be:

| Option | Description |
|--------|-------------|
| `publish` | Publish immediately |
| `schedule` | Schedule for future date |
| `save_draft` | Save without publishing |

## Files

- `workflow.asl.json` - The ASL workflow definition
- `run.php` - Example runner with mock agents
- `README.md` - This documentation

## Related

- [Tutorial 3: Conditional Logic](../../docs/tutorials/03-conditional-logic.md)
- [Tutorial 4: Parallel Execution](../../docs/tutorials/04-parallel-execution.md)
- [Tutorial 8: Human Approval](../../docs/tutorials/08-human-approval.md)
