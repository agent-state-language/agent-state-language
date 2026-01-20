<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Tests\TestCase;

/**
 * Tests that code samples from documentation work correctly.
 * 
 * These tests validate the examples shown in tutorials and guides.
 */
class DocumentationCodeTest extends TestCase
{
    /**
     * Test the hello world workflow from Tutorial 1.
     */
    public function testTutorial01HelloWorld(): void
    {
        // Create the GreeterAgent as shown in tutorial
        $greeterAgent = new class implements AgentInterface {
            public function getName(): string
            {
                return 'GreeterAgent';
            }

            public function execute(array $parameters): array
            {
                $name = $parameters['name'] ?? 'World';
                
                return [
                    'greeting' => "Hello, {$name}!",
                    'timestamp' => date('c')
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('GreeterAgent', $greeterAgent);

        // Workflow from tutorial
        $workflow = [
            'Comment' => 'A simple hello world workflow',
            'StartAt' => 'SayHello',
            'States' => [
                'SayHello' => [
                    'Type' => 'Task',
                    'Agent' => 'GreeterAgent',
                    'Parameters' => [
                        'name.$' => '$.userName'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['userName' => 'Alice']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello, Alice!', $result->getOutput()['greeting']);
    }

    /**
     * Test the two-state workflow from Tutorial 1.
     */
    public function testTutorial01TwoStateWorkflow(): void
    {
        $greeterAgent = new class implements AgentInterface {
            public function getName(): string
            {
                return 'GreeterAgent';
            }

            public function execute(array $parameters): array
            {
                $name = $parameters['name'] ?? 'World';
                return [
                    'greeting' => "Hello, {$name}!",
                    'timestamp' => date('c')
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('GreeterAgent', $greeterAgent);

        // Two-state workflow from tutorial
        $workflow = [
            'Comment' => 'Two-state hello workflow',
            'StartAt' => 'SayHello',
            'States' => [
                'SayHello' => [
                    'Type' => 'Task',
                    'Agent' => 'GreeterAgent',
                    'Parameters' => [
                        'name.$' => '$.userName'
                    ],
                    'ResultPath' => '$.greetingResult',
                    'Next' => 'FormatOutput'
                ],
                'FormatOutput' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'message.$' => '$.greetingResult.greeting',
                        'processedAt.$' => '$.greetingResult.timestamp'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['userName' => 'Bob']);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Hello, Bob!', $result->getOutput()['message']);
    }

    /**
     * Test the document pipeline from Tutorial 2.
     */
    public function testTutorial02DocumentPipeline(): void
    {
        // Create agents as shown in tutorial
        $extractorAgent = new class implements AgentInterface {
            public function getName(): string { return 'ExtractorAgent'; }
            public function execute(array $parameters): array
            {
                $document = $parameters['document'] ?? '';
                $paragraphs = array_filter(explode("\n\n", $document));
                return [
                    'paragraphs' => array_values($paragraphs),
                    'wordCount' => str_word_count($document),
                    'extractedAt' => date('c')
                ];
            }
        };

        $analyzerAgent = new class implements AgentInterface {
            public function getName(): string { return 'AnalyzerAgent'; }
            public function execute(array $parameters): array
            {
                $paragraphs = $parameters['paragraphs'] ?? [];
                $wordCount = $parameters['wordCount'] ?? 0;
                $avgWordsPerParagraph = count($paragraphs) > 0 
                    ? round($wordCount / count($paragraphs)) 
                    : 0;
                return [
                    'paragraphCount' => count($paragraphs),
                    'wordCount' => $wordCount,
                    'avgWordsPerParagraph' => $avgWordsPerParagraph,
                    'complexity' => $wordCount > 500 ? 'high' : ($wordCount > 100 ? 'medium' : 'low')
                ];
            }
        };

        $summarizerAgent = new class implements AgentInterface {
            public function getName(): string { return 'SummarizerAgent'; }
            public function execute(array $parameters): array
            {
                $paragraphs = $parameters['paragraphs'] ?? [];
                $analysis = $parameters['analysis'] ?? [];
                $firstParagraph = $paragraphs[0] ?? 'No content';
                $summary = strlen($firstParagraph) > 100 
                    ? substr($firstParagraph, 0, 100) . '...'
                    : $firstParagraph;
                return [
                    'summary' => $summary,
                    'stats' => $analysis,
                    'generatedAt' => date('c')
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ExtractorAgent', $extractorAgent);
        $registry->register('AnalyzerAgent', $analyzerAgent);
        $registry->register('SummarizerAgent', $summarizerAgent);

        // Workflow from tutorial
        $workflow = [
            'Comment' => 'Document processing pipeline',
            'StartAt' => 'ExtractDocument',
            'States' => [
                'ExtractDocument' => [
                    'Type' => 'Task',
                    'Agent' => 'ExtractorAgent',
                    'Parameters' => [
                        'document.$' => '$.inputDocument'
                    ],
                    'ResultPath' => '$.extraction',
                    'Next' => 'AnalyzeContent'
                ],
                'AnalyzeContent' => [
                    'Type' => 'Task',
                    'Agent' => 'AnalyzerAgent',
                    'Parameters' => [
                        'paragraphs.$' => '$.extraction.paragraphs',
                        'wordCount.$' => '$.extraction.wordCount'
                    ],
                    'ResultPath' => '$.analysis',
                    'Next' => 'GenerateSummary'
                ],
                'GenerateSummary' => [
                    'Type' => 'Task',
                    'Agent' => 'SummarizerAgent',
                    'Parameters' => [
                        'paragraphs.$' => '$.extraction.paragraphs',
                        'analysis.$' => '$.analysis'
                    ],
                    'ResultPath' => '$.result',
                    'Next' => 'FormatOutput'
                ],
                'FormatOutput' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'summary.$' => '$.result.summary',
                        'statistics.$' => '$.result.stats',
                        'processedAt.$' => '$.result.generatedAt'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'inputDocument' => "Agent State Language is powerful.\n\nIt enables configurable workflows."
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('summary', $result->getOutput());
        $this->assertArrayHasKey('statistics', $result->getOutput());
    }

    /**
     * Test the conditional workflow from Tutorial 3.
     */
    public function testTutorial03ConditionalLogic(): void
    {
        $contentAnalyzer = new class implements AgentInterface {
            public function getName(): string { return 'ContentAnalyzer'; }
            public function execute(array $parameters): array
            {
                $content = $parameters['content'] ?? '';
                // Simplified risk calculation
                $riskScore = strlen($content) > 100 ? 30 : 10;
                if (stripos($content, 'spam') !== false) {
                    $riskScore += 75; // Bump to push over 80 threshold
                }
                return [
                    'riskScore' => $riskScore,
                    'flags' => [],
                    'analyzedAt' => date('c')
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ContentAnalyzer', $contentAnalyzer);

        // Simplified moderation workflow
        $workflow = [
            'Comment' => 'Content moderation workflow',
            'StartAt' => 'AnalyzeContent',
            'States' => [
                'AnalyzeContent' => [
                    'Type' => 'Task',
                    'Agent' => 'ContentAnalyzer',
                    'Parameters' => [
                        'content.$' => '$.content',
                        'contentType.$' => '$.contentType'
                    ],
                    'ResultPath' => '$.analysis',
                    'Next' => 'RouteByRisk'
                ],
                'RouteByRisk' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.analysis.riskScore',
                            'NumericGreaterThanEquals' => 80,
                            'Next' => 'HighRisk'
                        ],
                        [
                            'Variable' => '$.analysis.riskScore',
                            'NumericGreaterThanEquals' => 50,
                            'Next' => 'MediumRisk'
                        ]
                    ],
                    'Default' => 'LowRisk'
                ],
                'HighRisk' => [
                    'Type' => 'Pass',
                    'Result' => ['decision' => 'blocked', 'reason' => 'High risk content'],
                    'End' => true
                ],
                'MediumRisk' => [
                    'Type' => 'Pass',
                    'Result' => ['decision' => 'review', 'reason' => 'Medium risk content'],
                    'End' => true
                ],
                'LowRisk' => [
                    'Type' => 'Pass',
                    'Result' => ['decision' => 'approved', 'reason' => 'Low risk content'],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test low risk content
        $result = $engine->run([
            'content' => 'This is normal content',
            'contentType' => 'text'
        ]);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('approved', $result->getOutput()['decision']);

        // Test high risk content (contains 'spam')
        $result2 = $engine->run([
            'content' => 'This is spam content',
            'contentType' => 'text'
        ]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('blocked', $result2->getOutput()['decision']);
    }

    /**
     * Test getting-started sentiment analysis example.
     */
    public function testGettingStartedSentimentAnalysis(): void
    {
        $sentimentAnalyzer = new class implements AgentInterface {
            public function getName(): string { return 'SentimentAnalyzer'; }
            public function execute(array $parameters): array
            {
                $text = $parameters['text'] ?? '';
                $positiveWords = ['good', 'great', 'excellent', 'happy', 'love'];
                $negativeWords = ['bad', 'terrible', 'hate', 'sad', 'awful'];
                
                $textLower = strtolower($text);
                $positiveCount = 0;
                $negativeCount = 0;
                
                foreach ($positiveWords as $word) {
                    $positiveCount += substr_count($textLower, $word);
                }
                foreach ($negativeWords as $word) {
                    $negativeCount += substr_count($textLower, $word);
                }
                
                if ($positiveCount > $negativeCount) {
                    return ['sentiment' => 'positive', 'confidence' => 0.8];
                } elseif ($negativeCount > $positiveCount) {
                    return ['sentiment' => 'negative', 'confidence' => 0.8];
                }
                
                return ['sentiment' => 'neutral', 'confidence' => 0.6];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('SentimentAnalyzer', $sentimentAnalyzer);

        $workflow = [
            'Comment' => 'Analyze text sentiment',
            'StartAt' => 'AnalyzeSentiment',
            'States' => [
                'AnalyzeSentiment' => [
                    'Type' => 'Task',
                    'Agent' => 'SentimentAnalyzer',
                    'Parameters' => [
                        'text.$' => '$.inputText'
                    ],
                    'ResultPath' => '$.sentiment',
                    'Next' => 'FormatResponse'
                ],
                'FormatResponse' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'analysis.$' => '$.sentiment',
                        'originalText.$' => '$.inputText'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test positive sentiment
        $result = $engine->run([
            'inputText' => 'I love this product! It works great!'
        ]);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('positive', $result->getOutput()['analysis']['sentiment']);

        // Test negative sentiment
        $result2 = $engine->run([
            'inputText' => 'This is terrible and I hate it'
        ]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('negative', $result2->getOutput()['analysis']['sentiment']);
    }

    /**
     * Test parallel execution from Tutorial 4.
     */
    public function testTutorial04ParallelExecution(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAnalyzer'; }
            public function execute(array $parameters): array
            {
                return [
                    'score' => rand(70, 100),
                    'issues' => [],
                    'passed' => true
                ];
            }
        };

        $aggregator = new class implements AgentInterface {
            public function getName(): string { return 'ResultAggregator'; }
            public function execute(array $parameters): array
            {
                $security = $parameters['security'] ?? [];
                $performance = $parameters['performance'] ?? [];
                $style = $parameters['style'] ?? [];
                $documentation = $parameters['documentation'] ?? [];

                return [
                    'securityScore' => $security['score'] ?? 0,
                    'performanceScore' => $performance['score'] ?? 0,
                    'styleScore' => $style['score'] ?? 0,
                    'docScore' => $documentation['score'] ?? 0,
                    'grade' => 'A'
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('SecurityScanner', $mockAgent);
        $registry->register('PerformanceAnalyzer', $mockAgent);
        $registry->register('StyleChecker', $mockAgent);
        $registry->register('DocValidator', $mockAgent);
        $registry->register('ResultAggregator', $aggregator);

        $workflow = [
            'Comment' => 'Parallel code analysis workflow',
            'StartAt' => 'ParallelAnalysis',
            'States' => [
                'ParallelAnalysis' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'SecurityScan',
                            'States' => [
                                'SecurityScan' => [
                                    'Type' => 'Task',
                                    'Agent' => 'SecurityScanner',
                                    'Parameters' => [
                                        'codebase.$' => '$.codebase'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'PerformanceCheck',
                            'States' => [
                                'PerformanceCheck' => [
                                    'Type' => 'Task',
                                    'Agent' => 'PerformanceAnalyzer',
                                    'Parameters' => [
                                        'codebase.$' => '$.codebase'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'StyleCheck',
                            'States' => [
                                'StyleCheck' => [
                                    'Type' => 'Task',
                                    'Agent' => 'StyleChecker',
                                    'Parameters' => [
                                        'codebase.$' => '$.codebase'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'DocCheck',
                            'States' => [
                                'DocCheck' => [
                                    'Type' => 'Task',
                                    'Agent' => 'DocValidator',
                                    'Parameters' => [
                                        'codebase.$' => '$.codebase'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ]
                    ],
                    'ResultPath' => '$.analysisResults',
                    'Next' => 'AggregateResults'
                ],
                'AggregateResults' => [
                    'Type' => 'Task',
                    'Agent' => 'ResultAggregator',
                    'Parameters' => [
                        'security.$' => '$.analysisResults[0]',
                        'performance.$' => '$.analysisResults[1]',
                        'style.$' => '$.analysisResults[2]',
                        'documentation.$' => '$.analysisResults[3]'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['codebase' => '/path/to/project']);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('securityScore', $result->getOutput());
        $this->assertArrayHasKey('grade', $result->getOutput());
    }

    /**
     * Test JSONPath reference examples.
     */
    public function testJsonPathReferenceExamples(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                return ['processed' => true, 'input' => $parameters];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('UserProcessor', $mockAgent);

        // Test InputPath filtering
        $workflow = [
            'StartAt' => 'ProcessUser',
            'States' => [
                'ProcessUser' => [
                    'Type' => 'Task',
                    'Agent' => 'UserProcessor',
                    'InputPath' => '$.user',
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'user' => ['name' => 'Alice'],
            'other' => 'data'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Alice', $result->getOutput()['input']['name']);
    }

    /**
     * Test intrinsic functions from reference docs.
     */
    public function testIntrinsicFunctionsReference(): void
    {
        $workflow = [
            'StartAt' => 'TestFunctions',
            'States' => [
                'TestFunctions' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'greeting.$' => "States.Format('Hello, {}!', $.name)",
                        'hasItems.$' => '$.hasItems',
                        'itemCount' => 3
                    ],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'name' => 'World',
            'hasItems' => true
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello, World!', $result->getOutput()['greeting']);
    }

    /**
     * Test Tutorial 5 - Map state iteration.
     */
    public function testTutorial05MapState(): void
    {
        $itemProcessor = new class implements AgentInterface {
            public function getName(): string { return 'ItemProcessor'; }
            public function execute(array $parameters): array
            {
                // The item comes as the full input in Map iteration
                $item = $parameters['value'] ?? $parameters['item'] ?? '';
                if (is_array($item)) {
                    $item = $item['value'] ?? '';
                }
                return [
                    'processed' => strtoupper((string) $item),
                    'length' => strlen((string) $item)
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ItemProcessor', $itemProcessor);

        $workflow = [
            'Comment' => 'Process each item in a list',
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'Iterator' => [
                        'StartAt' => 'ProcessOne',
                        'States' => [
                            'ProcessOne' => [
                                'Type' => 'Task',
                                'Agent' => 'ItemProcessor',
                                'Parameters' => [
                                    'value.$' => '$.value'
                                ],
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['items' => [
            ['value' => 'apple'],
            ['value' => 'banana'],
            ['value' => 'cherry']
        ]]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertCount(3, $output);
        $this->assertEquals('APPLE', $output[0]['processed']);
        $this->assertEquals('BANANA', $output[1]['processed']);
        $this->assertEquals('CHERRY', $output[2]['processed']);
    }

    /**
     * Test Tutorial 6 - Memory and Context concepts.
     * This tests the workflow structure validation (Memory/Context are config blocks).
     */
    public function testTutorial06MemoryWorkflowStructure(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                return ['processed' => true, 'data' => $parameters];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ContextLoader', $mockAgent);
        $registry->register('Assistant', $mockAgent);
        $registry->register('HistoryUpdater', $mockAgent);

        // Test that a workflow with Memory block structure is valid
        $workflow = [
            'Comment' => 'Memory-enabled workflow',
            'StartAt' => 'LoadContext',
            'States' => [
                'LoadContext' => [
                    'Type' => 'Task',
                    'Agent' => 'ContextLoader',
                    'Parameters' => [
                        'userId.$' => '$.userId'
                    ],
                    'ResultPath' => '$.userContext',
                    'Next' => 'ProcessRequest'
                ],
                'ProcessRequest' => [
                    'Type' => 'Task',
                    'Agent' => 'Assistant',
                    'Parameters' => [
                        'message.$' => '$.message',
                        'context.$' => '$.userContext'
                    ],
                    'ResultPath' => '$.response',
                    'Next' => 'UpdateHistory'
                ],
                'UpdateHistory' => [
                    'Type' => 'Task',
                    'Agent' => 'HistoryUpdater',
                    'Parameters' => [
                        'response.$' => '$.response'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'userId' => 'user_123',
            'message' => 'Hello, how are you?'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('processed', $result->getOutput());
    }

    /**
     * Test Tutorial 7 - Tool orchestration patterns.
     */
    public function testTutorial07ToolOrchestration(): void
    {
        $toolAgent = new class implements AgentInterface {
            public function getName(): string { return 'ToolAgent'; }
            public function execute(array $parameters): array
            {
                $tool = $parameters['tool'] ?? 'unknown';
                return [
                    'toolUsed' => $tool,
                    'result' => 'success',
                    'output' => 'Tool output for ' . $tool
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('FileAnalyzer', $toolAgent);
        $registry->register('CodeSearcher', $toolAgent);

        $workflow = [
            'Comment' => 'Tool orchestration workflow',
            'StartAt' => 'AnalyzeFiles',
            'States' => [
                'AnalyzeFiles' => [
                    'Type' => 'Task',
                    'Agent' => 'FileAnalyzer',
                    'Parameters' => [
                        'tool' => 'file_read',
                        'path.$' => '$.filePath'
                    ],
                    'ResultPath' => '$.fileAnalysis',
                    'Next' => 'SearchCode'
                ],
                'SearchCode' => [
                    'Type' => 'Task',
                    'Agent' => 'CodeSearcher',
                    'Parameters' => [
                        'tool' => 'code_search',
                        'pattern.$' => '$.searchPattern'
                    ],
                    'ResultPath' => '$.searchResults',
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'filePath' => '/src/main.php',
            'searchPattern' => 'function.*execute'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('searchResults', $result->getOutput());
        $this->assertEquals('code_search', $result->getOutput()['searchResults']['toolUsed']);
    }

    /**
     * Test Tutorial 8 - Human approval patterns (structure validation).
     */
    public function testTutorial08ApprovalWorkflowStructure(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                // Simulate an auto-approval scenario for testing
                return [
                    'needsApproval' => false,
                    'autoApproved' => true,
                    'reason' => 'Amount under threshold'
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('RefundProcessor', $mockAgent);
        $registry->register('ApprovalChecker', $mockAgent);
        $registry->register('RefundExecutor', $mockAgent);

        $workflow = [
            'Comment' => 'Refund approval workflow',
            'StartAt' => 'CheckApproval',
            'States' => [
                'CheckApproval' => [
                    'Type' => 'Task',
                    'Agent' => 'ApprovalChecker',
                    'Parameters' => [
                        'amount.$' => '$.amount',
                        'reason.$' => '$.reason'
                    ],
                    'ResultPath' => '$.approvalCheck',
                    'Next' => 'RouteApproval'
                ],
                'RouteApproval' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.approvalCheck.autoApproved',
                            'BooleanEquals' => true,
                            'Next' => 'ProcessRefund'
                        ]
                    ],
                    'Default' => 'PendingApproval'
                ],
                'PendingApproval' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'pending_approval'],
                    'End' => true
                ],
                'ProcessRefund' => [
                    'Type' => 'Task',
                    'Agent' => 'RefundExecutor',
                    'Parameters' => [
                        'amount.$' => '$.amount'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'amount' => 25.00,
            'reason' => 'Product defective'
        ]);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test Tutorial 9 - Multi-agent debate pattern.
     */
    public function testTutorial09MultiAgentDebate(): void
    {
        $agent1 = new class implements AgentInterface {
            public function getName(): string { return 'Agent1'; }
            public function execute(array $parameters): array
            {
                return [
                    'position' => 'Option A is better because of efficiency',
                    'confidence' => 0.8
                ];
            }
        };

        $agent2 = new class implements AgentInterface {
            public function getName(): string { return 'Agent2'; }
            public function execute(array $parameters): array
            {
                return [
                    'position' => 'Option B is better because of cost',
                    'confidence' => 0.7
                ];
            }
        };

        $mediator = new class implements AgentInterface {
            public function getName(): string { return 'Mediator'; }
            public function execute(array $parameters): array
            {
                $positions = $parameters['positions'] ?? [];
                return [
                    'consensus' => 'Option A with cost considerations',
                    'summary' => 'Both perspectives considered',
                    'finalConfidence' => 0.85
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('Analyst1', $agent1);
        $registry->register('Analyst2', $agent2);
        $registry->register('Mediator', $mediator);

        // Simulated debate workflow using Parallel state
        $workflow = [
            'Comment' => 'Multi-agent analysis workflow',
            'StartAt' => 'GatherPerspectives',
            'States' => [
                'GatherPerspectives' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Analyst1Analysis',
                            'States' => [
                                'Analyst1Analysis' => [
                                    'Type' => 'Task',
                                    'Agent' => 'Analyst1',
                                    'Parameters' => [
                                        'topic.$' => '$.topic'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Analyst2Analysis',
                            'States' => [
                                'Analyst2Analysis' => [
                                    'Type' => 'Task',
                                    'Agent' => 'Analyst2',
                                    'Parameters' => [
                                        'topic.$' => '$.topic'
                                    ],
                                    'End' => true
                                ]
                            ]
                        ]
                    ],
                    'ResultPath' => '$.positions',
                    'Next' => 'SynthesizeConsensus'
                ],
                'SynthesizeConsensus' => [
                    'Type' => 'Task',
                    'Agent' => 'Mediator',
                    'Parameters' => [
                        'positions.$' => '$.positions'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['topic' => 'Cloud provider selection']);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('consensus', $result->getOutput());
    }

    /**
     * Test Tutorial 10 - Cost management patterns.
     */
    public function testTutorial10CostManagement(): void
    {
        $cheapAgent = new class implements AgentInterface {
            public function getName(): string { return 'CheapAgent'; }
            public function execute(array $parameters): array
            {
                return [
                    'result' => 'Processed with budget model',
                    'tokensUsed' => 100,
                    'cost' => 0.001
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('BudgetProcessor', $cheapAgent);
        $registry->register('StandardProcessor', $cheapAgent);

        $workflow = [
            'Comment' => 'Cost-aware workflow',
            'StartAt' => 'CheckBudget',
            'States' => [
                'CheckBudget' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'budget' => 0.10,
                        'data.$' => '$.inputData'
                    ],
                    'ResultPath' => '$.context',
                    'Next' => 'Process'
                ],
                'Process' => [
                    'Type' => 'Task',
                    'Agent' => 'BudgetProcessor',
                    'Parameters' => [
                        'data.$' => '$.context.data'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['inputData' => 'Test content to process']);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('tokensUsed', $result->getOutput());
    }

    /**
     * Test Tutorial 11 - Error handling patterns (retry simulation).
     */
    public function testTutorial11ErrorHandlingRetry(): void
    {
        $counter = 0;
        $flakyAgent = new class implements AgentInterface {
            public function getName(): string { return 'FlakyAgent'; }
            public function execute(array $parameters): array
            {
                // Simulate eventual success
                return [
                    'success' => true,
                    'message' => 'Operation completed'
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('FlakyService', $flakyAgent);
        $registry->register('FallbackService', $flakyAgent);

        $workflow = [
            'Comment' => 'Error handling workflow',
            'StartAt' => 'TryOperation',
            'States' => [
                'TryOperation' => [
                    'Type' => 'Task',
                    'Agent' => 'FlakyService',
                    'Parameters' => [
                        'action.$' => '$.action'
                    ],
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'HandleError',
                            'ResultPath' => '$.error'
                        ]
                    ],
                    'End' => true
                ],
                'HandleError' => [
                    'Type' => 'Task',
                    'Agent' => 'FallbackService',
                    'Parameters' => [
                        'originalError.$' => '$.error'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['action' => 'process_data']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(true, $result->getOutput()['success']);
    }

    /**
     * Test Tutorial 12 - Building reusable workflow patterns.
     */
    public function testTutorial12ReusablePatterns(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                return [
                    'processed' => true,
                    'input' => $parameters
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('DataValidator', $mockAgent);
        $registry->register('DataTransformer', $mockAgent);
        $registry->register('DataLoader', $mockAgent);

        // Common ETL pattern
        $workflow = [
            'Comment' => 'ETL Pipeline Template',
            'StartAt' => 'Validate',
            'States' => [
                'Validate' => [
                    'Type' => 'Task',
                    'Agent' => 'DataValidator',
                    'Parameters' => [
                        'data.$' => '$.rawData',
                        'schema.$' => '$.schema'
                    ],
                    'ResultPath' => '$.validation',
                    'Next' => 'Transform'
                ],
                'Transform' => [
                    'Type' => 'Task',
                    'Agent' => 'DataTransformer',
                    'Parameters' => [
                        'data.$' => '$.rawData',
                        'rules.$' => '$.transformRules'
                    ],
                    'ResultPath' => '$.transformed',
                    'Next' => 'Load'
                ],
                'Load' => [
                    'Type' => 'Task',
                    'Agent' => 'DataLoader',
                    'Parameters' => [
                        'data.$' => '$.transformed',
                        'destination.$' => '$.destination'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'rawData' => ['id' => 1, 'name' => 'Test'],
            'schema' => 'user_schema',
            'transformRules' => ['uppercase_name'],
            'destination' => 'users_table'
        ]);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test State Types Reference - Pass state.
     */
    public function testStateTypesReferencePassState(): void
    {
        $workflow = [
            'StartAt' => 'InjectData',
            'States' => [
                'InjectData' => [
                    'Type' => 'Pass',
                    'Result' => [
                        'status' => 'initialized',
                        'timestamp' => '2025-01-20T00:00:00Z'
                    ],
                    'ResultPath' => '$.metadata',
                    'Next' => 'TransformData'
                ],
                'TransformData' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'combined' => true,
                        'input.$' => '$.inputValue',
                        'meta.$' => '$.metadata'
                    ],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['inputValue' => 'test_data']);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getOutput()['combined']);
        $this->assertEquals('test_data', $result->getOutput()['input']);
        $this->assertEquals('initialized', $result->getOutput()['meta']['status']);
    }

    /**
     * Test State Types Reference - Choice state with multiple conditions.
     */
    public function testStateTypesReferenceChoiceState(): void
    {
        $workflow = [
            'StartAt' => 'RouteByType',
            'States' => [
                'RouteByType' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.type',
                            'StringEquals' => 'premium',
                            'Next' => 'PremiumPath'
                        ],
                        [
                            'Variable' => '$.type',
                            'StringEquals' => 'standard',
                            'Next' => 'StandardPath'
                        ],
                        [
                            'And' => [
                                ['Variable' => '$.type', 'StringEquals' => 'trial'],
                                ['Variable' => '$.daysRemaining', 'NumericGreaterThan' => 0]
                            ],
                            'Next' => 'TrialPath'
                        ]
                    ],
                    'Default' => 'DefaultPath'
                ],
                'PremiumPath' => [
                    'Type' => 'Pass',
                    'Result' => ['tier' => 'premium', 'features' => 'all'],
                    'End' => true
                ],
                'StandardPath' => [
                    'Type' => 'Pass',
                    'Result' => ['tier' => 'standard', 'features' => 'basic'],
                    'End' => true
                ],
                'TrialPath' => [
                    'Type' => 'Pass',
                    'Result' => ['tier' => 'trial', 'features' => 'limited'],
                    'End' => true
                ],
                'DefaultPath' => [
                    'Type' => 'Pass',
                    'Result' => ['tier' => 'free', 'features' => 'none'],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);

        // Test premium path
        $result = $engine->run(['type' => 'premium']);
        $this->assertEquals('premium', $result->getOutput()['tier']);

        // Test standard path
        $result = $engine->run(['type' => 'standard']);
        $this->assertEquals('standard', $result->getOutput()['tier']);

        // Test trial path with And condition
        $result = $engine->run(['type' => 'trial', 'daysRemaining' => 7]);
        $this->assertEquals('trial', $result->getOutput()['tier']);

        // Test default path
        $result = $engine->run(['type' => 'unknown']);
        $this->assertEquals('free', $result->getOutput()['tier']);
    }

    /**
     * Test State Types Reference - Succeed and Fail states.
     */
    public function testStateTypesReferenceSucceedAndFailStates(): void
    {
        $workflow = [
            'StartAt' => 'CheckCondition',
            'States' => [
                'CheckCondition' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.valid',
                            'BooleanEquals' => true,
                            'Next' => 'Success'
                        ]
                    ],
                    'Default' => 'Failure'
                ],
                'Success' => [
                    'Type' => 'Succeed'
                ],
                'Failure' => [
                    'Type' => 'Fail',
                    'Error' => 'ValidationError',
                    'Cause' => 'Input validation failed'
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);

        // Test success path
        $result = $engine->run(['valid' => true]);
        $this->assertTrue($result->isSuccess());

        // Test failure path
        $result = $engine->run(['valid' => false]);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ValidationError', $result->getError());
    }

    /**
     * Test intrinsic functions from reference - comprehensive examples.
     */
    public function testIntrinsicFunctionsReferenceComprehensive(): void
    {
        $workflow = [
            'StartAt' => 'TestAllFunctions',
            'States' => [
                'TestAllFunctions' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'formatted.$' => "States.Format('User {} has {} items', $.userName, $.itemCount)",
                        'arrayLen.$' => 'States.ArrayLength($.items)',
                        'uuid.$' => 'States.UUID()',
                        'sum.$' => 'States.MathAdd($.a, $.b)',
                        'merged.$' => 'States.Merge($.obj1, $.obj2)'
                    ],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'userName' => 'Alice',
            'itemCount' => 5,
            'items' => [1, 2, 3],
            'a' => 10,
            'b' => 20,
            'obj1' => ['name' => 'Test'],
            'obj2' => ['value' => 123]
        ]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        
        $this->assertEquals('User Alice has 5 items', $output['formatted']);
        $this->assertEquals(3, $output['arrayLen']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $output['uuid']);
        $this->assertEquals(30, $output['sum']);
        $this->assertEquals(['name' => 'Test', 'value' => 123], $output['merged']);
    }

    /**
     * Test best practices guide - modular workflow design.
     */
    public function testBestPracticesModularDesign(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'ModularAgent'; }
            public function execute(array $parameters): array
            {
                return ['step' => $parameters['stepName'] ?? 'unknown', 'completed' => true];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('Step1Agent', $mockAgent);
        $registry->register('Step2Agent', $mockAgent);
        $registry->register('Step3Agent', $mockAgent);

        // Modular workflow with clear separation
        $workflow = [
            'Comment' => 'Modular design pattern',
            'StartAt' => 'Initialize',
            'States' => [
                'Initialize' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'workflow' => 'modular_test',
                        'startTime.$' => '$.timestamp'
                    ],
                    'ResultPath' => '$.context',
                    'Next' => 'Step1'
                ],
                'Step1' => [
                    'Type' => 'Task',
                    'Agent' => 'Step1Agent',
                    'Parameters' => ['stepName' => 'step1'],
                    'ResultPath' => '$.step1Result',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Task',
                    'Agent' => 'Step2Agent',
                    'Parameters' => ['stepName' => 'step2'],
                    'ResultPath' => '$.step2Result',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Task',
                    'Agent' => 'Step3Agent',
                    'Parameters' => ['stepName' => 'step3'],
                    'ResultPath' => '$.step3Result',
                    'Next' => 'Finalize'
                ],
                'Finalize' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'allStepsComplete' => true,
                        'step1.$' => '$.step1Result',
                        'step2.$' => '$.step2Result',
                        'step3.$' => '$.step3Result'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['timestamp' => '2025-01-20T12:00:00Z']);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertTrue($output['allStepsComplete']);
        $this->assertEquals('step1', $output['step1']['step']);
        $this->assertEquals('step2', $output['step2']['step']);
        $this->assertEquals('step3', $output['step3']['step']);
    }
}
