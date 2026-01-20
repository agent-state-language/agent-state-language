# Research Assistant Example

An intelligent research assistant that adapts its strategy based on query type, with source verification and human review for low-confidence answers.

## Features

- **Query Classification** - Automatically determines optimal research strategy
- **Multiple Strategies** - Factual, comparative, exploratory, and general research
- **Source Verification** - Validates findings before synthesis
- **Human Review** - Low-confidence answers escalated for review
- **Parallel Research** - Comparative queries researched simultaneously

## Workflow Diagram

```
ParseQuery → DetermineStrategy
                   ↓
    ┌──────────────┼──────────────┐──────────────┐
 Factual      Comparative     Exploratory      General
    ↓              ↓              ↓              ↓
QuickFactCheck  Parallel      DeepResearch   Researcher
    ↓         ┌────┴────┐         ↓              ↓
    │     ResearchA  ResearchB    │              │
    │         └────┬────┘         │              │
    │          CompareOptions     │              │
    └──────────────┴──────────────┴──────────────┘
                         ↓
                   VerifyFindings
                         ↓
                   CheckConfidence
                    ↓         ↓
               <0.7        ≥0.7
                 ↓           ↓
           HumanReview  SynthesizeAnswer
                 ↓           ↓
           FormatResponse ←──┘
```

## Quick Start

```bash
# From the examples/research-assistant directory
php run.php
```

## Expected Output

```
=== Research Assistant Workflow Example ===

Test 1: Factual Query
-------------------------------------------------------
Methodology: factual
Answer: Based on my research: Based on my research, here is what I found: The...
Confidence: 0.7

Test 2: Comparative Query
-------------------------------------------------------
Methodology: comparative
Answer: Based on my research: Comparative analysis complete. Both options have...
Sources: 6

Test 3: Exploratory Query
-------------------------------------------------------
Methodology: exploratory
Answer: Based on my research: Comprehensive research on 'How does machine lear...
Confidence: 0.9

Test 4: General Query
-------------------------------------------------------
Methodology: general
Answer: Based on my research: Research findings on 'Tell me about sustainable ...

=== Research Assistant Workflow Complete ===
```

## Using in Your Project

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register your agents
$registry = new AgentRegistry();
$registry->register('QueryParser', new YourQueryParserAgent());
$registry->register('FactChecker', new YourFactCheckerAgent());
$registry->register('Researcher', new YourResearcherAgent());
$registry->register('DeepResearcher', new YourDeepResearcherAgent());
$registry->register('Comparator', new YourComparatorAgent());
$registry->register('Verifier', new YourVerifierAgent());
$registry->register('Synthesizer', new YourSynthesizerAgent());

// Load and run the workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'query' => 'What are the pros and cons of GraphQL vs REST?',
    'context' => ['audience' => 'developers']
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    echo "Answer: " . $output['answer'] . "\n";
    echo "Confidence: " . $output['confidence'] . "\n";
    echo "Sources: " . count($output['sources']) . "\n";
}
```

## Research Strategies

| Type | Strategy | Use Case | Example Query |
|------|----------|----------|---------------|
| Factual | Quick fact check | Simple questions | "When was X founded?" |
| Comparative | Parallel research | A vs B comparisons | "X vs Y - which is better?" |
| Exploratory | Deep research | Understanding concepts | "How does X work?" |
| General | Standard search | Everything else | "Tell me about X" |

## Query Classification

The `QueryParser` agent determines the research strategy:

```php
// Comparative: "X vs Y" or "compare X and Y"
preg_match('/(.+)\s+vs\.?\s+(.+)/i', $query, $matches)

// Factual: starts with when, what is, who, where, how many
preg_match('/^(when|what is|who|where|how many)/i', $query)

// Exploratory: starts with how does, why, explain
preg_match('/^(how does|why|explain|what are the)/i', $query)
```

## Verification and Confidence

The `Verifier` agent calculates confidence scores:

| Confidence | Action |
|------------|--------|
| ≥ 0.7 | Proceed to synthesis |
| < 0.7 | Escalate to human review |

Factors affecting confidence:
- Number of sources
- Source quality
- Cross-reference success
- Consistency of findings

## Tool Configuration

Research agents have rate-limited tool access:

```json
{
  "Tools": {
    "Allowed": ["web_search", "fetch_webpage"],
    "RateLimits": {
      "web_search": { "MaxPerMinute": 10 },
      "fetch_webpage": { "MaxConcurrent": 3 }
    }
  }
}
```

## Budget Configuration

The workflow includes cost limits:

```json
{
  "Budget": {
    "MaxCost": "$2.00",
    "MaxTokens": 40000
  }
}
```

## Agents Required

| Agent | Purpose | Output |
|-------|---------|--------|
| QueryParser | Classify and structure query | `{ type, mainQuestion, options }` |
| FactChecker | Quick factual verification | `{ summary, sources }` |
| Researcher | General web research | `{ summary, sources, keyPoints }` |
| DeepResearcher | In-depth topic exploration | `{ summary, findings }` |
| Comparator | Compare multiple options | `{ comparison, recommendation }` |
| Verifier | Validate research findings | `{ verified, confidence, concerns }` |
| Synthesizer | Create final answer | `{ response, sources }` |

## Output Format

```php
[
    'question' => 'Original query',
    'answer' => 'Synthesized response',
    'sources' => [
        ['url' => '...', 'title' => '...'],
        // ...
    ],
    'confidence' => 0.85,
    'methodology' => 'comparative'
]
```

## Files

- `workflow.asl.json` - The ASL workflow definition
- `run.php` - Example runner with mock agents
- `README.md` - This documentation

## Related

- [Tutorial 3: Conditional Logic](../../docs/tutorials/03-conditional-logic.md)
- [Tutorial 4: Parallel Execution](../../docs/tutorials/04-parallel-execution.md)
- [Tutorial 7: Tool Orchestration](../../docs/tutorials/07-tool-orchestration.md)
