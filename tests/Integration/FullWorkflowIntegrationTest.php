<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Tests\TestCase;

/**
 * Full integration tests that exercise complete workflow scenarios
 * with realistic agent behavior, state transitions, and error handling.
 */
class FullWorkflowIntegrationTest extends TestCase
{
    /**
     * Test a complete document processing pipeline with multiple agents.
     */
    public function testDocumentProcessingPipeline(): void
    {
        $registry = new AgentRegistry();

        // Parser agent extracts structure
        $registry->register('DocumentParser', new class implements AgentInterface {
            public function getName(): string
            {
                return 'DocumentParser';
            }
            public function execute(array $params): array
            {
                $content = $params['content'] ?? '';
                return [
                    'title' => 'Extracted Title',
                    'sections' => ['intro', 'body', 'conclusion'],
                    'wordCount' => str_word_count($content),
                    'language' => 'en'
                ];
            }
        });

        // Analyzer agent analyzes content
        $registry->register('ContentAnalyzer', new class implements AgentInterface {
            public function getName(): string
            {
                return 'ContentAnalyzer';
            }
            public function execute(array $params): array
            {
                $wordCount = $params['wordCount'] ?? 0;
                return [
                    'sentiment' => 'positive',
                    'complexity' => $wordCount > 100 ? 'high' : 'low',
                    'topics' => ['technology', 'innovation'],
                    'readingTime' => ceil($wordCount / 200)
                ];
            }
        });

        // Summarizer generates summary
        $registry->register('Summarizer', new class implements AgentInterface {
            public function getName(): string
            {
                return 'Summarizer';
            }
            public function execute(array $params): array
            {
                return [
                    'summary' => 'This document discusses technology and innovation.',
                    'keyPoints' => ['Point 1', 'Point 2', 'Point 3'],
                    '_tokens' => 150,
                    '_cost' => 0.003
                ];
            }
        });

        $workflow = [
            'Comment' => 'Document Processing Pipeline',
            'StartAt' => 'ParseDocument',
            'States' => [
                'ParseDocument' => [
                    'Type' => 'Task',
                    'Agent' => 'DocumentParser',
                    'Parameters' => ['content.$' => '$.document'],
                    'ResultPath' => '$.parsed',
                    'Next' => 'AnalyzeContent'
                ],
                'AnalyzeContent' => [
                    'Type' => 'Task',
                    'Agent' => 'ContentAnalyzer',
                    'Parameters' => ['wordCount.$' => '$.parsed.wordCount'],
                    'ResultPath' => '$.analysis',
                    'Next' => 'CheckComplexity'
                ],
                'CheckComplexity' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.analysis.complexity',
                            'StringEquals' => 'high',
                            'Next' => 'DetailedSummary'
                        ]
                    ],
                    'Default' => 'QuickSummary'
                ],
                'DetailedSummary' => [
                    'Type' => 'Task',
                    'Agent' => 'Summarizer',
                    'Parameters' => [
                        'content.$' => '$.document',
                        'analysis.$' => '$.analysis',
                        'detailed' => true
                    ],
                    'ResultPath' => '$.summary',
                    'Next' => 'FormatOutput'
                ],
                'QuickSummary' => [
                    'Type' => 'Task',
                    'Agent' => 'Summarizer',
                    'Parameters' => [
                        'content.$' => '$.document',
                        'quick' => true
                    ],
                    'ResultPath' => '$.summary',
                    'Next' => 'FormatOutput'
                ],
                'FormatOutput' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'title.$' => '$.parsed.title',
                        'summary.$' => '$.summary.summary',
                        'keyPoints.$' => '$.summary.keyPoints',
                        'sentiment.$' => '$.analysis.sentiment',
                        'readingTime.$' => '$.analysis.readingTime'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'document' => 'This is a long document about technology and innovation. ' .
                str_repeat('More content here. ', 50)
        ]);

        $this->assertTrue($result->isSuccess());

        $output = $result->getOutput();
        $this->assertEquals('Extracted Title', $output['title']);
        $this->assertEquals('positive', $output['sentiment']);
        $this->assertIsArray($output['keyPoints']);
        $this->assertCount(3, $output['keyPoints']);

        // Verify execution trace
        $trace = $result->getTrace();
        $stateNames = array_filter(
            array_column($trace, 'stateName'),
            fn($name) => $name !== null
        );
        $this->assertContains('ParseDocument', $stateNames);
        $this->assertContains('AnalyzeContent', $stateNames);
        $this->assertContains('FormatOutput', $stateNames);
    }

    /**
     * Test parallel execution with multiple branches.
     */
    public function testParallelMultiAgentAnalysis(): void
    {
        $registry = new AgentRegistry();

        $registry->register('SecurityScanner', new class implements AgentInterface {
            public function getName(): string
            {
                return 'SecurityScanner';
            }
            public function execute(array $params): array
            {
                return [
                    'vulnerabilities' => [],
                    'score' => 95,
                    'passed' => true
                ];
            }
        });

        $registry->register('PerformanceAnalyzer', new class implements AgentInterface {
            public function getName(): string
            {
                return 'PerformanceAnalyzer';
            }
            public function execute(array $params): array
            {
                return [
                    'metrics' => ['cpu' => '23%', 'memory' => '45%'],
                    'score' => 88,
                    'passed' => true
                ];
            }
        });

        $registry->register('StyleChecker', new class implements AgentInterface {
            public function getName(): string
            {
                return 'StyleChecker';
            }
            public function execute(array $params): array
            {
                return [
                    'issues' => ['Line too long at line 42'],
                    'score' => 92,
                    'passed' => true
                ];
            }
        });

        $registry->register('ResultAggregator', new class implements AgentInterface {
            public function getName(): string
            {
                return 'ResultAggregator';
            }
            public function execute(array $params): array
            {
                $security = $params['security'] ?? [];
                $performance = $params['performance'] ?? [];
                $style = $params['style'] ?? [];

                $avgScore = ($security['score'] + $performance['score'] + $style['score']) / 3;

                return [
                    'overallScore' => round($avgScore),
                    'allPassed' => $security['passed'] && $performance['passed'] && $style['passed'],
                    'summary' => 'Code analysis complete'
                ];
            }
        });

        $workflow = [
            'Comment' => 'Parallel Code Analysis',
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
                                    'Parameters' => ['code.$' => '$.code'],
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
                                    'Parameters' => ['code.$' => '$.code'],
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
                                    'Parameters' => ['code.$' => '$.code'],
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
                        'style.$' => '$.analysisResults[2]'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['code' => 'function test() { return true; }']);

        $this->assertTrue($result->isSuccess());

        $output = $result->getOutput();
        $this->assertEquals(92, $output['overallScore']); // (95+88+92)/3 = 91.67 rounded
        $this->assertTrue($output['allPassed']);
    }

    /**
     * Test Map state with iteration over items.
     */
    public function testMapStateIteration(): void
    {
        $registry = new AgentRegistry();

        $registry->register('ItemProcessor', new class implements AgentInterface {
            public function getName(): string
            {
                return 'ItemProcessor';
            }
            public function execute(array $params): array
            {
                $item = $params['item'] ?? '';
                $index = $params['index'] ?? 0;

                return [
                    'processed' => strtoupper($item),
                    'index' => $index,
                    'length' => strlen($item)
                ];
            }
        });

        $workflow = [
            'Comment' => 'Map State Processing',
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'ItemSelector' => [
                        'item.$' => '$$.Map.Item.Value',
                        'index.$' => '$$.Map.Item.Index'
                    ],
                    'Iterator' => [
                        'StartAt' => 'Process',
                        'States' => [
                            'Process' => [
                                'Type' => 'Task',
                                'Agent' => 'ItemProcessor',
                                'End' => true
                            ]
                        ]
                    ],
                    'ResultPath' => '$.results',
                    'Next' => 'Summarize'
                ],
                'Summarize' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'processedItems.$' => '$.results',
                        'totalItems.$' => "States.ArrayLength($.items)"
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'items' => ['apple', 'banana', 'cherry']
        ]);

        $this->assertTrue($result->isSuccess());

        $output = $result->getOutput();
        $this->assertCount(3, $output['processedItems']);
        $this->assertEquals('APPLE', $output['processedItems'][0]['processed']);
        $this->assertEquals('BANANA', $output['processedItems'][1]['processed']);
        $this->assertEquals('CHERRY', $output['processedItems'][2]['processed']);
    }

    /**
     * Test error handling with retry and catch.
     */
    public function testErrorHandlingWithRetryAndCatch(): void
    {
        $attemptCount = 0;

        $registry = new AgentRegistry();

        // Agent that fails first 2 times, succeeds on 3rd
        $registry->register('FlakeyAgent', new class($attemptCount) implements AgentInterface {
            private int $attempts = 0;
            public function __construct(int &$attempts)
            {
                $this->attempts = &$attempts;
            }
            public function getName(): string
            {
                return 'FlakeyAgent';
            }
            public function execute(array $params): array
            {
                $this->attempts++;
                if ($this->attempts < 3) {
                    throw new AgentException('Temporary failure', 'FlakeyAgent', 'Agent.TemporaryError');
                }
                return ['success' => true, 'attempts' => $this->attempts];
            }
        });

        $workflow = [
            'Comment' => 'Error Handling Test',
            'StartAt' => 'TryOperation',
            'States' => [
                'TryOperation' => [
                    'Type' => 'Task',
                    'Agent' => 'FlakeyAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['Agent.TemporaryError'],
                            'MaxAttempts' => 3,
                            'IntervalSeconds' => 0 // No delay for testing
                        ]
                    ],
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'ResultPath' => '$.error',
                            'Next' => 'HandleError'
                        ]
                    ],
                    'End' => true
                ],
                'HandleError' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'failed', 'recovered' => true],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        // After 2 retries (3 total attempts), it should succeed
        $this->assertTrue($result->getOutput()['success'] ?? false);
    }

    /**
     * Test complex choice conditions with nested And/Or.
     */
    public function testComplexChoiceConditions(): void
    {
        $registry = new AgentRegistry();

        $workflow = [
            'Comment' => 'Complex Choice Logic',
            'StartAt' => 'EvaluateUser',
            'States' => [
                'EvaluateUser' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        // Premium user with high score OR admin
                        [
                            'Or' => [
                                [
                                    'And' => [
                                        ['Variable' => '$.user.type', 'StringEquals' => 'premium'],
                                        ['Variable' => '$.user.score', 'NumericGreaterThanEquals' => 80]
                                    ]
                                ],
                                ['Variable' => '$.user.role', 'StringEquals' => 'admin']
                            ],
                            'Next' => 'VIPPath'
                        ],
                        // Verified user with score >= 50
                        [
                            'And' => [
                                ['Variable' => '$.user.verified', 'BooleanEquals' => true],
                                ['Variable' => '$.user.score', 'NumericGreaterThanEquals' => 50]
                            ],
                            'Next' => 'StandardPath'
                        ]
                    ],
                    'Default' => 'BasicPath'
                ],
                'VIPPath' => [
                    'Type' => 'Pass',
                    'Result' => ['access' => 'vip', 'features' => ['all']],
                    'End' => true
                ],
                'StandardPath' => [
                    'Type' => 'Pass',
                    'Result' => ['access' => 'standard', 'features' => ['basic', 'reports']],
                    'End' => true
                ],
                'BasicPath' => [
                    'Type' => 'Pass',
                    'Result' => ['access' => 'basic', 'features' => ['basic']],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test VIP path via premium + high score
        $result1 = $engine->run([
            'user' => ['type' => 'premium', 'score' => 90, 'verified' => true, 'role' => 'user']
        ]);
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals('vip', $result1->getOutput()['access']);

        // Test VIP path via admin role
        $result2 = $engine->run([
            'user' => ['type' => 'basic', 'score' => 30, 'verified' => false, 'role' => 'admin']
        ]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('vip', $result2->getOutput()['access']);

        // Test standard path
        $result3 = $engine->run([
            'user' => ['type' => 'basic', 'score' => 60, 'verified' => true, 'role' => 'user']
        ]);
        $this->assertTrue($result3->isSuccess());
        $this->assertEquals('standard', $result3->getOutput()['access']);

        // Test basic path
        $result4 = $engine->run([
            'user' => ['type' => 'basic', 'score' => 30, 'verified' => false, 'role' => 'user']
        ]);
        $this->assertTrue($result4->isSuccess());
        $this->assertEquals('basic', $result4->getOutput()['access']);
    }

    /**
     * Test intrinsic functions in workflow.
     */
    public function testIntrinsicFunctionsInWorkflow(): void
    {
        $registry = new AgentRegistry();

        $workflow = [
            'Comment' => 'Intrinsic Functions Test',
            'StartAt' => 'Transform',
            'States' => [
                'Transform' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'greeting.$' => "States.Format('Hello, {}!', $.name)",
                        'itemCount.$' => 'States.ArrayLength($.items)',
                        'uniqueId.$' => 'States.UUID()',
                        'combined.$' => 'States.Array($.a, $.b, $.c)',
                        'total.$' => 'States.MathAdd($.x, $.y)'
                    ],
                    'Next' => 'Validate'
                ],
                'Validate' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.itemCount',
                            'NumericGreaterThan' => 0,
                            'Next' => 'Success'
                        ]
                    ],
                    'Default' => 'NoItems'
                ],
                'Success' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'status' => 'success',
                        'greeting.$' => '$.greeting',
                        'itemCount.$' => '$.itemCount',
                        'hasId.$' => '$.uniqueId',
                        'combined.$' => '$.combined',
                        'total.$' => '$.total'
                    ],
                    'End' => true
                ],
                'NoItems' => [
                    'Type' => 'Fail',
                    'Error' => 'NoItems',
                    'Cause' => 'No items to process'
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'name' => 'World',
            'items' => [1, 2, 3, 4, 5],
            'a' => 'first',
            'b' => 'second',
            'c' => 'third',
            'x' => 10,
            'y' => 20
        ]);

        $this->assertTrue($result->isSuccess());

        $output = $result->getOutput();
        $this->assertEquals('Hello, World!', $output['greeting']);
        $this->assertEquals(5, $output['itemCount']);
        $this->assertNotEmpty($output['hasId']); // UUID generated
        $this->assertEquals(['first', 'second', 'third'], $output['combined']);
        $this->assertEquals(30, $output['total']);
    }

    /**
     * Test workflow with Succeed and Fail terminal states.
     */
    public function testTerminalStates(): void
    {
        $registry = new AgentRegistry();

        $workflow = [
            'Comment' => 'Terminal States Test',
            'StartAt' => 'CheckInput',
            'States' => [
                'CheckInput' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.valid',
                            'BooleanEquals' => true,
                            'Next' => 'ProcessValid'
                        ]
                    ],
                    'Default' => 'RejectInvalid'
                ],
                'ProcessValid' => [
                    'Type' => 'Pass',
                    'Result' => ['processed' => true],
                    'ResultPath' => '$.result',
                    'Next' => 'Complete'
                ],
                'Complete' => [
                    'Type' => 'Succeed'
                ],
                'RejectInvalid' => [
                    'Type' => 'Fail',
                    'Error' => 'ValidationError',
                    'Cause' => 'Input validation failed'
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test success path
        $result1 = $engine->run(['valid' => true]);
        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result1->getOutput()['result']['processed']);

        // Test failure path
        $result2 = $engine->run(['valid' => false]);
        $this->assertFalse($result2->isSuccess());
        $this->assertEquals('ValidationError', $result2->getError());
        $this->assertEquals('Input validation failed', $result2->getErrorCause());
    }

    /**
     * Test cost and token tracking.
     */
    public function testCostAndTokenTracking(): void
    {
        $registry = new AgentRegistry();

        $registry->register('CostlyAgent', new class implements AgentInterface {
            public function getName(): string
            {
                return 'CostlyAgent';
            }
            public function execute(array $params): array
            {
                return [
                    'result' => 'done',
                    '_tokens' => 1000,
                    '_cost' => 0.05
                ];
            }
        });

        $workflow = [
            'Comment' => 'Cost Tracking Test',
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'ResultPath' => '$.step1',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'ResultPath' => '$.step2',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'ResultPath' => '$.step3',
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());

        // Should track tokens and cost from all 3 steps
        $this->assertEquals(3000, $result->getTokensUsed());
        $this->assertEqualsWithDelta(0.15, $result->getCost(), 0.001);
    }

    /**
     * Test a realistic customer support routing workflow.
     */
    public function testCustomerSupportRouting(): void
    {
        $registry = new AgentRegistry();

        $registry->register('IntentClassifier', new class implements AgentInterface {
            public function getName(): string
            {
                return 'IntentClassifier';
            }
            public function execute(array $params): array
            {
                $message = strtolower($params['message'] ?? '');

                if (str_contains($message, 'refund') || str_contains($message, 'money back')) {
                    return ['intent' => 'refund', 'confidence' => 0.95];
                }
                if (str_contains($message, 'broken') || str_contains($message, 'not working')) {
                    return ['intent' => 'technical', 'confidence' => 0.90];
                }
                if (str_contains($message, 'cancel')) {
                    return ['intent' => 'cancellation', 'confidence' => 0.88];
                }
                return ['intent' => 'general', 'confidence' => 0.70];
            }
        });

        $registry->register('RefundHandler', new class implements AgentInterface {
            public function getName(): string
            {
                return 'RefundHandler';
            }
            public function execute(array $params): array
            {
                return [
                    'response' => 'I can help you with a refund. Please provide your order number.',
                    'action' => 'collect_order_id',
                    'priority' => 'high'
                ];
            }
        });

        $registry->register('TechnicalSupport', new class implements AgentInterface {
            public function getName(): string
            {
                return 'TechnicalSupport';
            }
            public function execute(array $params): array
            {
                return [
                    'response' => 'I understand you are having technical issues. Let me help troubleshoot.',
                    'action' => 'troubleshoot',
                    'priority' => 'medium'
                ];
            }
        });

        $registry->register('GeneralSupport', new class implements AgentInterface {
            public function getName(): string
            {
                return 'GeneralSupport';
            }
            public function execute(array $params): array
            {
                return [
                    'response' => 'How can I assist you today?',
                    'action' => 'gather_info',
                    'priority' => 'normal'
                ];
            }
        });

        $workflow = [
            'Comment' => 'Customer Support Routing',
            'StartAt' => 'ClassifyIntent',
            'States' => [
                'ClassifyIntent' => [
                    'Type' => 'Task',
                    'Agent' => 'IntentClassifier',
                    'Parameters' => ['message.$' => '$.message'],
                    'ResultPath' => '$.classification',
                    'Next' => 'RouteByIntent'
                ],
                'RouteByIntent' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.classification.intent',
                            'StringEquals' => 'refund',
                            'Next' => 'HandleRefund'
                        ],
                        [
                            'Variable' => '$.classification.intent',
                            'StringEquals' => 'technical',
                            'Next' => 'HandleTechnical'
                        ]
                    ],
                    'Default' => 'HandleGeneral'
                ],
                'HandleRefund' => [
                    'Type' => 'Task',
                    'Agent' => 'RefundHandler',
                    'Parameters' => ['message.$' => '$.message'],
                    'ResultPath' => '$.response',
                    'Next' => 'FormatResponse'
                ],
                'HandleTechnical' => [
                    'Type' => 'Task',
                    'Agent' => 'TechnicalSupport',
                    'Parameters' => ['message.$' => '$.message'],
                    'ResultPath' => '$.response',
                    'Next' => 'FormatResponse'
                ],
                'HandleGeneral' => [
                    'Type' => 'Task',
                    'Agent' => 'GeneralSupport',
                    'Parameters' => ['message.$' => '$.message'],
                    'ResultPath' => '$.response',
                    'Next' => 'FormatResponse'
                ],
                'FormatResponse' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'message.$' => '$.response.response',
                        'intent.$' => '$.classification.intent',
                        'confidence.$' => '$.classification.confidence',
                        'priority.$' => '$.response.priority'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test refund request
        $result1 = $engine->run(['message' => 'I want my money back please']);
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals('refund', $result1->getOutput()['intent']);
        $this->assertEquals('high', $result1->getOutput()['priority']);

        // Test technical issue
        $result2 = $engine->run(['message' => 'My product is broken and not working']);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('technical', $result2->getOutput()['intent']);
        $this->assertEquals('medium', $result2->getOutput()['priority']);

        // Test general inquiry
        $result3 = $engine->run(['message' => 'Hello, I have a question']);
        $this->assertTrue($result3->isSuccess());
        $this->assertEquals('general', $result3->getOutput()['intent']);
        $this->assertEquals('normal', $result3->getOutput()['priority']);
    }

    /**
     * Test workflow execution duration tracking.
     */
    public function testExecutionDurationTracking(): void
    {
        $registry = new AgentRegistry();

        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'Next' => 'Middle'
                ],
                'Middle' => [
                    'Type' => 'Pass',
                    'Next' => 'End'
                ],
                'End' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThanOrEqual(0, $result->getDuration());
        $this->assertLessThan(1, $result->getDuration()); // Should complete in under 1 second
    }
}
