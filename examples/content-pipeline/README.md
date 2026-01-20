# Content Pipeline Example

A complete content creation workflow from generation to publishing.

## Features

- **AI Generation** - Creates content based on topic and parameters
- **Content Moderation** - Automatic harmful content detection
- **Human Review** - Flagged content reviewed by editors
- **Parallel Optimization** - SEO, metadata, and images generated in parallel
- **Publishing Options** - Publish now, schedule, or save draft

## Pipeline Stages

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

## Usage

```php
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'topic' => 'Best practices for remote work',
    'contentType' => 'blog_post',
    'targetAudience' => 'professionals',
    'tone' => 'informative',
    'length' => 'medium'
]);
```

## Guardrails

Content moderation includes:

- **Harmful Content Detection** - Blocks problematic content
- **PII Detection** - Redacts personal information
- **Competitor Mentions** - Flags for review

## Agents Required

| Agent | Purpose |
|-------|---------|
| ContentGenerator | Creates initial content |
| ContentModerator | Checks for policy violations |
| SEOOptimizer | Optimizes for search |
| MetadataGenerator | Creates titles, descriptions |
| ImageGenerator | Creates accompanying images |
| ContentAssembler | Combines all elements |
| Publisher | Publishes to platforms |
| Scheduler | Schedules future publishing |
