# Tutorial 3: Conditional Logic

Learn how to add branching logic to your workflows using Choice states.

## What You'll Learn

- Choice states and comparison operators
- Routing based on data values
- Default paths and fallbacks
- Compound conditions (And, Or, Not)

## The Scenario

We'll build a content moderation workflow that routes content based on:

- Content type (text, image, video)
- Risk score (high, medium, low)
- User verification status

## Step 1: The Workflow

Create `moderation.asl.json`:

```json
{
  "Comment": "Content moderation workflow",
  "StartAt": "AnalyzeContent",
  "States": {
    "AnalyzeContent": {
      "Type": "Task",
      "Agent": "ContentAnalyzer",
      "Parameters": {
        "content.$": "$.content",
        "contentType.$": "$.contentType"
      },
      "ResultPath": "$.analysis",
      "Next": "RouteByRisk"
    },
    "RouteByRisk": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.analysis.riskScore",
          "NumericGreaterThanEquals": 80,
          "Next": "HighRiskReview"
        },
        {
          "And": [
            {
              "Variable": "$.analysis.riskScore",
              "NumericGreaterThanEquals": 50
            },
            {
              "Variable": "$.analysis.riskScore",
              "NumericLessThan": 80
            }
          ],
          "Next": "MediumRiskReview"
        },
        {
          "Variable": "$.userVerified",
          "BooleanEquals": true,
          "Next": "AutoApprove"
        }
      ],
      "Default": "ManualReview"
    },
    "HighRiskReview": {
      "Type": "Task",
      "Agent": "HighRiskHandler",
      "ResultPath": "$.decision",
      "Next": "FinalizeDecision"
    },
    "MediumRiskReview": {
      "Type": "Task",
      "Agent": "MediumRiskHandler",
      "ResultPath": "$.decision",
      "Next": "FinalizeDecision"
    },
    "AutoApprove": {
      "Type": "Pass",
      "Result": {
        "decision": "approved",
        "reason": "Verified user with low risk content"
      },
      "ResultPath": "$.decision",
      "Next": "FinalizeDecision"
    },
    "ManualReview": {
      "Type": "Pass",
      "Result": {
        "decision": "pending",
        "reason": "Requires manual review"
      },
      "ResultPath": "$.decision",
      "Next": "FinalizeDecision"
    },
    "FinalizeDecision": {
      "Type": "Pass",
      "Parameters": {
        "contentId.$": "$.contentId",
        "decision.$": "$.decision.decision",
        "reason.$": "$.decision.reason",
        "analysisScore.$": "$.analysis.riskScore"
      },
      "End": true
    }
  }
}
```

## Understanding Choice States

### Basic Structure

```json
{
  "Type": "Choice",
  "Choices": [
    { "Variable": "$.field", "Operator": value, "Next": "StateName" }
  ],
  "Default": "FallbackState"
}
```

### Comparison Operators

#### String Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `StringEquals` | Exact match | `"StringEquals": "approved"` |
| `StringEqualsPath` | Match against another field | `"StringEqualsPath": "$.expected"` |
| `StringGreaterThan` | Lexicographic comparison | `"StringGreaterThan": "a"` |
| `StringLessThan` | Lexicographic comparison | `"StringLessThan": "z"` |
| `StringMatches` | Glob pattern | `"StringMatches": "*.pdf"` |

```json
{
  "Variable": "$.status",
  "StringEquals": "approved",
  "Next": "ProcessApproved"
}
```

#### Numeric Operators

| Operator | Description |
|----------|-------------|
| `NumericEquals` | Equal to |
| `NumericGreaterThan` | Greater than |
| `NumericGreaterThanEquals` | Greater than or equal |
| `NumericLessThan` | Less than |
| `NumericLessThanEquals` | Less than or equal |

```json
{
  "Variable": "$.score",
  "NumericGreaterThanEquals": 80,
  "Next": "HighScore"
}
```

#### Boolean Operator

```json
{
  "Variable": "$.isVerified",
  "BooleanEquals": true,
  "Next": "VerifiedPath"
}
```

#### Type Check Operators

| Operator | Description |
|----------|-------------|
| `IsNull` | Check if null |
| `IsPresent` | Check if field exists |
| `IsNumeric` | Check if number |
| `IsString` | Check if string |
| `IsBoolean` | Check if boolean |

```json
{
  "Variable": "$.optionalField",
  "IsPresent": true,
  "Next": "HasOptionalField"
}
```

## Compound Conditions

### And - All Must Be True

```json
{
  "And": [
    { "Variable": "$.score", "NumericGreaterThanEquals": 50 },
    { "Variable": "$.score", "NumericLessThan": 80 },
    { "Variable": "$.verified", "BooleanEquals": true }
  ],
  "Next": "MediumVerified"
}
```

### Or - Any Must Be True

```json
{
  "Or": [
    { "Variable": "$.status", "StringEquals": "approved" },
    { "Variable": "$.status", "StringEquals": "auto-approved" }
  ],
  "Next": "ProcessApproved"
}
```

### Not - Negate Condition

```json
{
  "Not": {
    "Variable": "$.banned",
    "BooleanEquals": true
  },
  "Next": "NotBanned"
}
```

### Nested Compound Conditions

```json
{
  "And": [
    { "Variable": "$.type", "StringEquals": "premium" },
    {
      "Or": [
        { "Variable": "$.score", "NumericGreaterThan": 90 },
        {
          "And": [
            { "Variable": "$.verified", "BooleanEquals": true },
            { "Variable": "$.score", "NumericGreaterThan": 70 }
          ]
        }
      ]
    }
  ],
  "Next": "PremiumHighQuality"
}
```

## Step 2: The Agents

### ContentAnalyzer

```php
<?php

namespace MyOrg\Moderation;

use AgentStateLanguage\Agents\AgentInterface;

class ContentAnalyzer implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? '';
        $type = $parameters['contentType'] ?? 'text';
        
        // Simulate risk analysis
        $riskScore = $this->calculateRisk($content, $type);
        
        return [
            'riskScore' => $riskScore,
            'flags' => $this->detectFlags($content),
            'contentType' => $type,
            'analyzedAt' => date('c')
        ];
    }

    private function calculateRisk(string $content, string $type): int
    {
        // Simplified risk calculation
        $baseScore = match($type) {
            'image' => 30,
            'video' => 50,
            default => 10
        };
        
        // Check for concerning patterns
        $keywords = ['spam', 'scam', 'offensive'];
        foreach ($keywords as $word) {
            if (stripos($content, $word) !== false) {
                $baseScore += 30;
            }
        }
        
        return min(100, $baseScore);
    }

    private function detectFlags(string $content): array
    {
        $flags = [];
        if (stripos($content, 'spam') !== false) $flags[] = 'potential_spam';
        if (strlen($content) < 10) $flags[] = 'too_short';
        return $flags;
    }

    public function getName(): string
    {
        return 'ContentAnalyzer';
    }
}
```

## Step 3: Run the Workflow

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

$registry = new AgentRegistry();
$registry->register('ContentAnalyzer', new ContentAnalyzer());
$registry->register('HighRiskHandler', new HighRiskHandler());
$registry->register('MediumRiskHandler', new MediumRiskHandler());

$engine = WorkflowEngine::fromFile('moderation.asl.json', $registry);

// Test high risk content
$result = $engine->run([
    'contentId' => '12345',
    'content' => 'This is spam and offensive content!',
    'contentType' => 'text',
    'userVerified' => false
]);

echo "Decision: " . $result->getOutput()['decision'] . "\n";
echo "Reason: " . $result->getOutput()['reason'] . "\n";
echo "Risk Score: " . $result->getOutput()['analysisScore'] . "\n";
```

## Evaluation Order

Choices are evaluated **in order**. The first matching choice wins:

```json
{
  "Choices": [
    { "Variable": "$.score", "NumericGreaterThanEquals": 90, "Next": "Excellent" },
    { "Variable": "$.score", "NumericGreaterThanEquals": 80, "Next": "Great" },
    { "Variable": "$.score", "NumericGreaterThanEquals": 70, "Next": "Good" }
  ],
  "Default": "NeedsWork"
}
```

If score is 95:
1. ✅ First choice matches (95 >= 90) → Goes to "Excellent"
2. Remaining choices not evaluated

## Default Path

Always include a `Default` to handle unexpected values:

```json
{
  "Type": "Choice",
  "Choices": [...],
  "Default": "HandleUnknown"
}
```

Without `Default`, unmatched input causes an error.

## Pattern: Multi-Stage Routing

Route through multiple Choice states for complex decisions:

```json
{
  "RouteByType": {
    "Type": "Choice",
    "Choices": [
      { "Variable": "$.type", "StringEquals": "text", "Next": "TextProcessing" },
      { "Variable": "$.type", "StringEquals": "image", "Next": "ImageProcessing" }
    ],
    "Default": "UnknownType"
  },
  "TextProcessing": {
    "Type": "Choice",
    "Choices": [
      { "Variable": "$.language", "StringEquals": "en", "Next": "EnglishText" }
    ],
    "Default": "ForeignText"
  }
}
```

## Summary

You've learned:

- ✅ Choice states for conditional branching
- ✅ Comparison operators (string, numeric, boolean, type checks)
- ✅ Compound conditions (And, Or, Not)
- ✅ Default paths for fallback handling
- ✅ Evaluation order and first-match semantics

## Next Steps

- [Tutorial 4: Parallel Execution](04-parallel-execution.md) - Run branches concurrently
- [Tutorial 5: Recursive Workflows](05-recursive-workflows.md) - Map iterations
