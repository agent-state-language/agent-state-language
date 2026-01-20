<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Tests\TestCase;

/**
 * Integration tests for example workflows.
 * 
 * These tests validate that all example workflows can be:
 * 1. Loaded and parsed correctly
 * 2. Have all states instantiated
 * 3. Execute with mock agents
 */
class ExampleWorkflowsTest extends TestCase
{
    private string $examplesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->examplesPath = dirname(__DIR__, 2) . '/examples';
    }

    /**
     * @dataProvider exampleWorkflowProvider
     */
    public function testExampleCanBeLoaded(string $workflowName, string $path): void
    {
        $this->assertFileExists($path, "Workflow file for {$workflowName} should exist");
        
        $content = file_get_contents($path);
        $this->assertNotFalse($content);
        
        $definition = json_decode($content, true);
        $this->assertNotNull($definition, "Workflow {$workflowName} should be valid JSON");
        $this->assertIsArray($definition);
        
        // Check required fields
        $this->assertArrayHasKey('StartAt', $definition, "Workflow {$workflowName} should have StartAt");
        $this->assertArrayHasKey('States', $definition, "Workflow {$workflowName} should have States");
    }

    /**
     * @dataProvider exampleWorkflowProvider
     */
    public function testExampleStatesCanBeInstantiated(string $workflowName, string $path): void
    {
        $registry = $this->createMockRegistry();
        
        // This should not throw any exceptions
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $this->assertInstanceOf(WorkflowEngine::class, $engine);
        
        $stateNames = $engine->getStateNames();
        $this->assertNotEmpty($stateNames, "Workflow {$workflowName} should have states");
    }

    public function testCodeReviewWorkflowExecution(): void
    {
        $registry = $this->createMockRegistry();
        $path = $this->examplesPath . '/code-review/workflow.asl.json';
        
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $result = $engine->run([
            'files' => ['src/Example.php'],
            'diff' => 'example diff content'
        ]);
        
        // The workflow should execute (may succeed or fail depending on mock behavior)
        $this->assertNotNull($result);
        $this->assertIsArray($result->getTrace());
    }

    public function testContentPipelineWorkflowExecution(): void
    {
        $registry = $this->createMockRegistry();
        $path = $this->examplesPath . '/content-pipeline/workflow.asl.json';
        
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $result = $engine->run([
            'topic' => 'AI in Healthcare',
            'contentType' => 'blog_post',
            'targetAudience' => 'developers',
            'tone' => 'professional',
            'length' => 1000
        ]);
        
        $this->assertNotNull($result);
        $this->assertIsArray($result->getTrace());
    }

    public function testCustomerSupportWorkflowExecution(): void
    {
        $registry = $this->createMockRegistry();
        $path = $this->examplesPath . '/customer-support/workflow.asl.json';
        
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $result = $engine->run([
            'customerId' => 'cust_123',
            'message' => 'I need help with my billing'
        ]);
        
        $this->assertNotNull($result);
        $this->assertIsArray($result->getTrace());
    }

    public function testResearchAssistantWorkflowExecution(): void
    {
        $registry = $this->createMockRegistry();
        $path = $this->examplesPath . '/research-assistant/workflow.asl.json';
        
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $result = $engine->run([
            'query' => 'What are the benefits of AI?',
            'context' => ['domain' => 'technology']
        ]);
        
        $this->assertNotNull($result);
        $this->assertIsArray($result->getTrace());
    }

    public function testTaskBreakdownWorkflowExecution(): void
    {
        $registry = $this->createMockRegistry();
        $path = $this->examplesPath . '/task-breakdown/workflow.asl.json';
        
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $result = $engine->run([
            'goal' => 'Build a REST API',
            'projectContext' => ['language' => 'PHP', 'framework' => 'Laravel']
        ]);
        
        $this->assertNotNull($result);
        $this->assertIsArray($result->getTrace());
    }

    /**
     * Provides example workflow paths for data-driven tests.
     *
     * @return array<string, array{string, string}>
     */
    public static function exampleWorkflowProvider(): array
    {
        $basePath = dirname(__DIR__, 2) . '/examples';
        
        return [
            'code-review' => ['code-review', $basePath . '/code-review/workflow.asl.json'],
            'content-pipeline' => ['content-pipeline', $basePath . '/content-pipeline/workflow.asl.json'],
            'customer-support' => ['customer-support', $basePath . '/customer-support/workflow.asl.json'],
            'research-assistant' => ['research-assistant', $basePath . '/research-assistant/workflow.asl.json'],
            'task-breakdown' => ['task-breakdown', $basePath . '/task-breakdown/workflow.asl.json'],
        ];
    }

    /**
     * Create a mock registry with agents that return predictable results.
     */
    private function createMockRegistry(): AgentRegistry
    {
        $registry = new AgentRegistry();
        
        // Create a generic mock agent that returns appropriate results
        $mockAgent = new class implements AgentInterface {
            public function getName(): string
            {
                return 'MockAgent';
            }

            public function execute(array $input): array
            {
                // Return reasonable mock results based on common patterns
                return [
                    'success' => true,
                    'message' => 'Mock response',
                    'content' => 'Generated content',
                    'severity' => 'low',
                    'passesAllChecks' => true,
                    'summary' => 'Summary of findings',
                    'issues' => [],
                    'criticalIssues' => [],
                    'blocked' => false,
                    'flagged' => false,
                    'flags' => [],
                    'autoApprove' => true,
                    'amount' => 50,
                    'reason' => 'Test reason',
                    'needsHuman' => false,
                    'ticketId' => 'TKT-001',
                    'category' => 'general',
                    'type' => 'factual',
                    'mainQuestion' => $input['question'] ?? $input['query'] ?? 'test',
                    'options' => ['Option A', 'Option B'],
                    'criteria' => ['speed', 'cost'],
                    'subtopics' => [],
                    'response' => 'Mock answer',
                    'sources' => [],
                    'confidence' => 0.9,
                    'hasQuestions' => false,
                    'questions' => [],
                    'isAtomic' => true,
                    'hasPendingTasks' => false,
                    'pendingTasks' => [],
                    'completedTasks' => [],
                    'organizedTasks' => [],
                    'totalTasks' => 0,
                    'maxDepthReached' => 1,
                ];
            }
        };

        // Register the mock agent under all agent names used in examples
        $agentNames = [
            // Code review agents
            'CodeLoader', 'SecurityReviewer', 'PerformanceReviewer', 'StyleReviewer',
            'TestReviewer', 'ReviewAggregator', 'ReviewFinalizer',
            // Content pipeline agents
            'ContentGenerator', 'ContentModerator', 'SEOOptimizer', 'MetadataGenerator',
            'ImageGenerator', 'ContentAssembler', 'Publisher', 'Scheduler', 'DraftSaver', 'Notifier',
            // Customer support agents
            'ContextLoader', 'IntentClassifier', 'BillingAgent', 'TechnicalAgent',
            'RefundAgent', 'RefundProcessor', 'ResponseGenerator', 'ComplaintAgent',
            'SalesAgent', 'GeneralAgent', 'EscalationAgent', 'InteractionLogger',
            // Research assistant agents
            'QueryParser', 'FactChecker', 'Researcher', 'Comparator', 'DeepResearcher',
            'Verifier', 'Synthesizer',
            // Task breakdown agents
            'ClarifierAgent', 'BreakdownAgent', 'ValidatorAgent', 'ResultCollector', 'FinalizerAgent',
        ];

        foreach ($agentNames as $name) {
            $registry->register($name, $mockAgent);
        }

        return $registry;
    }
}
