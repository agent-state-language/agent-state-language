<?php

/**
 * Research Assistant Example Runner
 * 
 * This script demonstrates how to run the research assistant workflow.
 * It includes mock agents for testing without external dependencies.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\AgentInterface;

// =============================================================================
// Mock Agents for Testing
// =============================================================================

/**
 * Parses and classifies research queries
 */
class QueryParserAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $query = strtolower($parameters['query'] ?? '');
        $context = $parameters['context'] ?? [];
        
        // Determine query type
        $type = 'general';
        $options = [];
        $criteria = [];
        $subtopics = [];
        
        // Check for comparative queries
        if (preg_match('/(.+)\s+vs\.?\s+(.+)/i', $query, $matches) ||
            preg_match('/compare\s+(.+)\s+(?:and|with)\s+(.+)/i', $query, $matches)) {
            $type = 'comparative';
            $options = [trim($matches[1]), trim($matches[2])];
            $criteria = ['features', 'performance', 'cost', 'ease of use'];
        }
        // Check for factual queries
        elseif (preg_match('/^(when|what is|who|where|how many)/i', $query)) {
            $type = 'factual';
        }
        // Check for exploratory queries
        elseif (preg_match('/^(how does|why|explain|what are the)/i', $query)) {
            $type = 'exploratory';
            $subtopics = $this->extractSubtopics($query);
        }
        
        return [
            'type' => $type,
            'mainQuestion' => $parameters['query'] ?? '',
            'options' => $options,
            'criteria' => $criteria,
            'subtopics' => $subtopics,
            'context' => $context,
            'parsedAt' => date('c')
        ];
    }
    
    private function extractSubtopics(string $query): array
    {
        // Simple subtopic extraction
        $subtopics = [];
        if (stripos($query, 'work') !== false) {
            $subtopics[] = 'mechanism';
        }
        if (stripos($query, 'benefit') !== false || stripos($query, 'advantage') !== false) {
            $subtopics[] = 'benefits';
        }
        if (stripos($query, 'example') !== false) {
            $subtopics[] = 'examples';
        }
        return $subtopics ?: ['overview', 'details', 'examples'];
    }

    public function getName(): string
    {
        return 'QueryParser';
    }
}

/**
 * Performs quick fact checking
 */
class FactCheckerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $question = $parameters['question'] ?? '';
        
        // Simulate fact lookup
        $facts = [
            'founded' => 'The company was founded in 2010.',
            'ceo' => 'The current CEO is John Smith.',
            'population' => 'The population is approximately 1.4 billion.',
        ];
        
        $answer = 'Based on my research, here is what I found: ';
        foreach ($facts as $key => $fact) {
            if (stripos($question, $key) !== false) {
                $answer .= $fact;
                break;
            }
        }
        
        return [
            'summary' => $answer,
            'sources' => [
                ['url' => 'https://example.com/source1', 'title' => 'Primary Source'],
                ['url' => 'https://example.com/source2', 'title' => 'Secondary Source']
            ],
            'factCount' => 1,
            'type' => 'factual'
        ];
    }

    public function getName(): string
    {
        return 'FactChecker';
    }
}

/**
 * Performs general research
 */
class ResearcherAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? $parameters['query'] ?? '';
        
        return [
            'summary' => "Research findings on '{$topic}': This topic has been extensively studied. Key findings include multiple perspectives from industry experts and academic sources.",
            'sources' => [
                ['url' => 'https://example.com/research1', 'title' => 'Industry Report'],
                ['url' => 'https://example.com/research2', 'title' => 'Academic Study'],
                ['url' => 'https://example.com/research3', 'title' => 'Expert Analysis']
            ],
            'keyPoints' => [
                'Point 1: Important finding about the topic',
                'Point 2: Another key insight',
                'Point 3: Practical implications'
            ],
            'type' => 'research'
        ];
    }

    public function getName(): string
    {
        return 'Researcher';
    }
}

/**
 * Performs deep exploratory research
 */
class DeepResearcherAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? '';
        $subtopics = $parameters['subtopics'] ?? [];
        
        $findings = [];
        foreach ($subtopics as $subtopic) {
            $findings[$subtopic] = "Detailed analysis of {$subtopic} related to {$topic}.";
        }
        
        return [
            'summary' => "Comprehensive research on '{$topic}' covering " . count($subtopics) . " subtopics.",
            'findings' => $findings,
            'sources' => [
                ['url' => 'https://example.com/deep1', 'title' => 'Comprehensive Guide'],
                ['url' => 'https://example.com/deep2', 'title' => 'Technical Documentation'],
                ['url' => 'https://example.com/deep3', 'title' => 'Case Studies'],
                ['url' => 'https://example.com/deep4', 'title' => 'Research Paper']
            ],
            'depth' => 'extensive',
            'type' => 'exploratory'
        ];
    }

    public function getName(): string
    {
        return 'DeepResearcher';
    }
}

/**
 * Compares research on multiple options
 */
class ComparatorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $optionA = $parameters['optionA'] ?? [];
        $optionB = $parameters['optionB'] ?? [];
        $criteria = $parameters['criteria'] ?? [];
        
        $comparison = [];
        foreach ($criteria as $criterion) {
            $comparison[$criterion] = [
                'optionA' => 'Performs well in this area',
                'optionB' => 'Also performs well with some differences',
                'winner' => rand(0, 1) ? 'A' : 'B'
            ];
        }
        
        return [
            'summary' => 'Comparative analysis complete. Both options have their strengths.',
            'comparison' => $comparison,
            'recommendation' => 'Option A is better for performance, Option B is better for ease of use.',
            'sources' => array_merge(
                $optionA['sources'] ?? [],
                $optionB['sources'] ?? []
            ),
            'type' => 'comparative'
        ];
    }

    public function getName(): string
    {
        return 'Comparator';
    }
}

/**
 * Verifies research findings
 */
class VerifierAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $findings = $parameters['findings'] ?? [];
        $originalQuery = $parameters['originalQuery'] ?? '';
        
        // Simulate verification
        $sourceCount = count($findings['sources'] ?? []);
        $confidence = min(0.95, 0.5 + ($sourceCount * 0.1));
        
        $concerns = [];
        if ($sourceCount < 3) {
            $concerns[] = 'Limited number of sources';
            $confidence -= 0.1;
        }
        
        return [
            'verified' => $confidence > 0.7,
            'confidence' => round($confidence, 2),
            'sourceCount' => $sourceCount,
            'concerns' => $concerns,
            'reasoning' => "Analyzed {$sourceCount} sources. Cross-referenced key claims. " .
                          ($confidence > 0.7 ? "Findings appear reliable." : "Some claims need additional verification."),
            'verifiedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'Verifier';
    }
}

/**
 * Synthesizes final answer from research
 */
class SynthesizerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $query = $parameters['query'] ?? '';
        $research = $parameters['research'] ?? [];
        $verification = $parameters['verification'] ?? [];
        
        $summary = $research['summary'] ?? 'Research complete.';
        $confidence = $verification['confidence'] ?? 0.8;
        
        $response = "Based on my research: {$summary}";
        
        if ($confidence < 0.8) {
            $response .= " Note: This answer has moderate confidence and may benefit from additional verification.";
        }
        
        return [
            'response' => $response,
            'sources' => $research['sources'] ?? [],
            'confidence' => $confidence,
            'synthesizedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'Synthesizer';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

echo "=== Research Assistant Workflow Example ===\n\n";

// Create and configure the agent registry
$registry = new AgentRegistry();
$registry->register('QueryParser', new QueryParserAgent());
$registry->register('FactChecker', new FactCheckerAgent());
$registry->register('Researcher', new ResearcherAgent());
$registry->register('DeepResearcher', new DeepResearcherAgent());
$registry->register('Comparator', new ComparatorAgent());
$registry->register('Verifier', new VerifierAgent());
$registry->register('Synthesizer', new SynthesizerAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

// Test Case 1: Factual query
echo "Test 1: Factual Query\n";
echo str_repeat('-', 55) . "\n";

$result1 = $engine->run([
    'query' => 'When was OpenAI founded?',
    'context' => ['audience' => 'general']
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Methodology: " . ($output['methodology'] ?? 'N/A') . "\n";
    echo "Answer: " . substr($output['answer'] ?? 'N/A', 0, 80) . "...\n";
    echo "Confidence: " . ($output['confidence'] ?? 'N/A') . "\n";
}

// Test Case 2: Comparative query
echo "\n\nTest 2: Comparative Query\n";
echo str_repeat('-', 55) . "\n";

$result2 = $engine->run([
    'query' => 'GraphQL vs REST - which is better for APIs?',
    'context' => ['audience' => 'developers']
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Methodology: " . ($output['methodology'] ?? 'N/A') . "\n";
    echo "Answer: " . substr($output['answer'] ?? 'N/A', 0, 80) . "...\n";
    echo "Sources: " . count($output['sources'] ?? []) . "\n";
}

// Test Case 3: Exploratory query
echo "\n\nTest 3: Exploratory Query\n";
echo str_repeat('-', 55) . "\n";

$result3 = $engine->run([
    'query' => 'How does machine learning work and what are the benefits?',
    'context' => ['audience' => 'beginners']
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Methodology: " . ($output['methodology'] ?? 'N/A') . "\n";
    echo "Answer: " . substr($output['answer'] ?? 'N/A', 0, 80) . "...\n";
    echo "Confidence: " . ($output['confidence'] ?? 'N/A') . "\n";
}

// Test Case 4: General query
echo "\n\nTest 4: General Query\n";
echo str_repeat('-', 55) . "\n";

$result4 = $engine->run([
    'query' => 'Tell me about sustainable energy solutions',
    'context' => []
]);

if ($result4->isSuccess()) {
    $output = $result4->getOutput();
    echo "Methodology: " . ($output['methodology'] ?? 'N/A') . "\n";
    echo "Answer: " . substr($output['answer'] ?? 'N/A', 0, 80) . "...\n";
}

echo "\n\n=== Research Assistant Workflow Complete ===\n";
