# Research Assistant Example

An intelligent research assistant that adapts its strategy based on query type.

## Features

- **Query Classification** - Determines optimal research strategy
- **Multiple Strategies** - Factual, comparative, exploratory, general
- **Source Verification** - Validates findings before synthesis
- **Human Review** - Low-confidence answers escalated for review
- **Parallel Research** - Comparative queries researched simultaneously

## Research Strategies

| Type | Strategy | Use Case |
|------|----------|----------|
| Factual | Quick fact check | "When was X founded?" |
| Comparative | Parallel research | "X vs Y - which is better?" |
| Exploratory | Deep research | "How does X work?" |
| General | Standard search | Everything else |

## Usage

```php
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'query' => 'What are the pros and cons of GraphQL vs REST?',
    'context' => ['audience' => 'developers']
]);

echo "Answer: " . $result->getOutput()['answer'];
echo "Confidence: " . $result->getOutput()['confidence'];
```

## Agents Required

- `QueryParser` - Classifies and structures the query
- `Researcher` - Conducts web research
- `FactChecker` - Quick factual verification
- `DeepResearcher` - In-depth topic exploration
- `Comparator` - Compares multiple options
- `Verifier` - Validates research findings
- `Synthesizer` - Creates final answer
