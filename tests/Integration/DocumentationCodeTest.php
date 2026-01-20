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
}
