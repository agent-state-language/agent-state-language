<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\IntrinsicFunctions;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Validation\WorkflowValidator;
use AgentStateLanguage\Tests\TestCase;

/**
 * Tests for code samples in guides and reference documentation.
 * 
 * Validates that all examples from:
 * - docs/guides/best-practices.md
 * - docs/guides/testing-workflows.md
 * - docs/reference/intrinsic-functions.md
 * - docs/reference/state-types.md
 * - docs/reference/json-path.md
 */
class GuideReferenceTest extends TestCase
{
    /**
     * Test best-practices.md - Error handling with retry pattern.
     */
    public function testBestPracticesErrorHandling(): void
    {
        $reliableAgent = new class implements AgentInterface {
            public function getName(): string { return 'ReliableAgent'; }
            public function execute(array $parameters): array
            {
                return ['success' => true, 'data' => 'processed'];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('DataProcessor', $reliableAgent);
        $registry->register('ErrorHandler', $reliableAgent);

        $workflow = [
            'Comment' => 'Best practices - Error handling',
            'StartAt' => 'ProcessData',
            'States' => [
                'ProcessData' => [
                    'Type' => 'Task',
                    'Agent' => 'DataProcessor',
                    'Parameters' => [
                        'input.$' => '$.data'
                    ],
                    'Retry' => [
                        [
                            'ErrorEquals' => ['States.Timeout'],
                            'IntervalSeconds' => 1,
                            'MaxAttempts' => 3,
                            'BackoffRate' => 2.0
                        ]
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
                    'Agent' => 'ErrorHandler',
                    'Parameters' => [
                        'error.$' => '$.error'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['data' => 'test_input']);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test best-practices.md - Parallel branch pattern.
     */
    public function testBestPracticesParallelBranches(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                return ['result' => 'processed', 'type' => $parameters['type'] ?? 'unknown'];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ValidationAgent', $mockAgent);
        $registry->register('EnrichmentAgent', $mockAgent);
        $registry->register('ClassificationAgent', $mockAgent);

        $workflow = [
            'Comment' => 'Parallel processing pattern',
            'StartAt' => 'ParallelProcessing',
            'States' => [
                'ParallelProcessing' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Validate',
                            'States' => [
                                'Validate' => [
                                    'Type' => 'Task',
                                    'Agent' => 'ValidationAgent',
                                    'Parameters' => ['type' => 'validation'],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Enrich',
                            'States' => [
                                'Enrich' => [
                                    'Type' => 'Task',
                                    'Agent' => 'EnrichmentAgent',
                                    'Parameters' => ['type' => 'enrichment'],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Classify',
                            'States' => [
                                'Classify' => [
                                    'Type' => 'Task',
                                    'Agent' => 'ClassificationAgent',
                                    'Parameters' => ['type' => 'classification'],
                                    'End' => true
                                ]
                            ]
                        ]
                    ],
                    'ResultPath' => '$.parallelResults',
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['input' => 'data']);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertArrayHasKey('parallelResults', $output);
        $this->assertCount(3, $output['parallelResults']);
    }

    /**
     * Test testing-workflows.md - Unit testing with mock agents.
     */
    public function testTestingWorkflowsMockAgents(): void
    {
        // Create a mock agent that returns predictable results
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'TestableAgent'; }
            public function execute(array $parameters): array
            {
                // Predictable behavior for testing
                if (isset($parameters['shouldFail']) && $parameters['shouldFail']) {
                    throw new \RuntimeException('Simulated failure');
                }
                return [
                    'processed' => true,
                    'receivedParams' => $parameters
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('TestableAgent', $mockAgent);

        $workflow = [
            'StartAt' => 'TestState',
            'States' => [
                'TestState' => [
                    'Type' => 'Task',
                    'Agent' => 'TestableAgent',
                    'Parameters' => [
                        'value.$' => '$.testValue',
                        'shouldFail' => false
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['testValue' => 'hello']);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getOutput()['processed']);
        $this->assertEquals('hello', $result->getOutput()['receivedParams']['value']);
    }

    /**
     * Test testing-workflows.md - Workflow validation.
     */
    public function testTestingWorkflowsValidation(): void
    {
        $validWorkflow = [
            'StartAt' => 'FirstState',
            'States' => [
                'FirstState' => [
                    'Type' => 'Pass',
                    'Next' => 'SecondState'
                ],
                'SecondState' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];

        // Workflow should be valid - validate returns true on success
        $validator = new WorkflowValidator();
        $isValid = $validator->validate($validWorkflow);
        $this->assertTrue($isValid, 'Valid workflow should pass validation');
    }

    /**
     * Test testing-workflows.md - Invalid workflow detection.
     */
    public function testTestingWorkflowsInvalidDetection(): void
    {
        $invalidWorkflow = [
            'StartAt' => 'NonExistentState',
            'States' => [
                'OnlyState' => [
                    'Type' => 'Pass',
                    'End' => true
                ]
            ]
        ];

        $validator = new WorkflowValidator();
        
        // Should throw ValidationException for invalid workflow
        $this->expectException(\AgentStateLanguage\Exceptions\ValidationException::class);
        $validator->validate($invalidWorkflow);
    }

    /**
     * Test state-types.md - Map state with ItemsPath.
     */
    public function testStateTypesMapState(): void
    {
        $itemProcessor = new class implements AgentInterface {
            public function getName(): string { return 'ItemProcessor'; }
            public function execute(array $parameters): array
            {
                return ['doubled' => ($parameters['value'] ?? 0) * 2];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ItemProcessor', $itemProcessor);

        $workflow = [
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.numbers',
                    'Parameters' => [
                        'value.$' => '$'
                    ],
                    'Iterator' => [
                        'StartAt' => 'Double',
                        'States' => [
                            'Double' => [
                                'Type' => 'Task',
                                'Agent' => 'ItemProcessor',
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['numbers' => [1, 2, 3, 4, 5]]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertCount(5, $output);
        $this->assertEquals(2, $output[0]['doubled']);
        $this->assertEquals(10, $output[4]['doubled']);
    }

    /**
     * Test state-types.md - Wait state (simulated).
     */
    public function testStateTypesWaitState(): void
    {
        $workflow = [
            'StartAt' => 'StartProcess',
            'States' => [
                'StartProcess' => [
                    'Type' => 'Pass',
                    'Result' => ['started' => true],
                    'ResultPath' => '$.processInfo',
                    'Next' => 'WaitForProcessing'
                ],
                'WaitForProcessing' => [
                    'Type' => 'Wait',
                    'Seconds' => 0, // Use 0 for testing to avoid actual delays
                    'Next' => 'Complete'
                ],
                'Complete' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'completed' => true,
                        'processInfo.$' => '$.processInfo'
                    ],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getOutput()['completed']);
    }

    /**
     * Test json-path.md - Basic path expressions.
     */
    public function testJsonPathBasicExpressions(): void
    {
        $workflow = [
            'StartAt' => 'ExtractData',
            'States' => [
                'ExtractData' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'userName.$' => '$.user.name',
                        'firstItem.$' => '$.items[0]',
                        'allItems.$' => '$.items'
                    ],
                    'End' => true
                ]
            ]
        ];

        $registry = new AgentRegistry();
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'user' => ['name' => 'Alice', 'id' => 123],
            'items' => ['apple', 'banana', 'cherry']
        ]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('Alice', $output['userName']);
        $this->assertEquals('apple', $output['firstItem']);
        $this->assertEquals(['apple', 'banana', 'cherry'], $output['allItems']);
    }

    /**
     * Test json-path.md - InputPath and OutputPath.
     */
    public function testJsonPathInputOutputPath(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array
            {
                return [
                    'processed' => $parameters,
                    'extra' => 'metadata'
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('Processor', $mockAgent);

        $workflow = [
            'StartAt' => 'ProcessUser',
            'States' => [
                'ProcessUser' => [
                    'Type' => 'Task',
                    'Agent' => 'Processor',
                    'InputPath' => '$.userData',
                    'OutputPath' => '$.processed',
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'userData' => ['name' => 'Bob', 'email' => 'bob@example.com'],
            'otherData' => 'ignored'
        ]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        // OutputPath selects $.processed from the result
        $this->assertArrayHasKey('name', $output);
        $this->assertEquals('Bob', $output['name']);
    }

    /**
     * Test intrinsic-functions.md - String functions.
     */
    public function testIntrinsicFunctionsString(): void
    {
        // Test States.Format
        $result = IntrinsicFunctions::evaluate(
            "States.Format('Hello, {}! You have {} messages.', 'Alice', 5)",
            []
        );
        $this->assertEquals('Hello, Alice! You have 5 messages.', $result);

        // Test States.StringToJson
        $result = IntrinsicFunctions::evaluate(
            'States.StringToJson(\'{"key": "value"}\')',
            []
        );
        $this->assertEquals(['key' => 'value'], $result);

        // Test States.JsonToString
        $result = IntrinsicFunctions::evaluate(
            'States.JsonToString($.data)',
            ['data' => ['name' => 'Test']]
        );
        $this->assertEquals('{"name":"Test"}', $result);
    }

    /**
     * Test intrinsic-functions.md - Array functions.
     */
    public function testIntrinsicFunctionsArray(): void
    {
        // Test States.Array
        $result = IntrinsicFunctions::evaluate("States.Array('a', 'b', 'c')", []);
        $this->assertEquals(['a', 'b', 'c'], $result);

        // Test States.ArrayLength
        $result = IntrinsicFunctions::evaluate('States.ArrayLength($.items)', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals(4, $result);

        // Test States.ArrayPartition
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayPartition($.items, 2)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertEquals([[1, 2], [3, 4], [5]], $result);

        // Test States.ArrayContains
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayContains($.items, 3)',
            ['items' => [1, 2, 3, 4]]
        );
        $this->assertTrue($result);

        // Test States.ArrayUnique
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayUnique($.items)',
            ['items' => [1, 2, 2, 3, 3, 3]]
        );
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test intrinsic-functions.md - Math functions.
     */
    public function testIntrinsicFunctionsMath(): void
    {
        // Test States.MathAdd
        $result = IntrinsicFunctions::evaluate('States.MathAdd(10, 5)', []);
        $this->assertEquals(15, $result);

        // Test States.MathSubtract
        $result = IntrinsicFunctions::evaluate('States.MathSubtract(10, 3)', []);
        $this->assertEquals(7, $result);

        // Test States.MathMultiply
        $result = IntrinsicFunctions::evaluate('States.MathMultiply(4, 3)', []);
        $this->assertEquals(12, $result);

        // Test States.MathRandom
        $result = IntrinsicFunctions::evaluate('States.MathRandom()', []);
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(1, $result);
    }

    /**
     * Test intrinsic-functions.md - Hash and encoding functions.
     */
    public function testIntrinsicFunctionsHashEncoding(): void
    {
        // Test States.Hash with SHA256
        $result = IntrinsicFunctions::evaluate("States.Hash('test', 'sha256')", []);
        $this->assertEquals(hash('sha256', 'test'), $result);

        // Test States.Hash with MD5
        $result = IntrinsicFunctions::evaluate("States.Hash('test', 'md5')", []);
        $this->assertEquals(md5('test'), $result);

        // Test States.Base64Encode
        $result = IntrinsicFunctions::evaluate("States.Base64Encode('Hello World')", []);
        $this->assertEquals(base64_encode('Hello World'), $result);

        // Test States.Base64Decode
        $result = IntrinsicFunctions::evaluate("States.Base64Decode('SGVsbG8gV29ybGQ=')", []);
        $this->assertEquals('Hello World', $result);

        // Test States.UUID
        $result = IntrinsicFunctions::evaluate('States.UUID()', []);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    /**
     * Test intrinsic-functions.md - Object manipulation functions.
     */
    public function testIntrinsicFunctionsObjectManipulation(): void
    {
        // Test States.Merge
        $result = IntrinsicFunctions::evaluate(
            'States.Merge($.a, $.b)',
            ['a' => ['x' => 1], 'b' => ['y' => 2]]
        );
        $this->assertEquals(['x' => 1, 'y' => 2], $result);

        // Test States.Pick
        $result = IntrinsicFunctions::evaluate(
            "States.Pick($.obj, 'name', 'age')",
            ['obj' => ['name' => 'John', 'age' => 30, 'secret' => 'hidden']]
        );
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
        $this->assertArrayNotHasKey('secret', $result);

        // Test States.Omit
        $result = IntrinsicFunctions::evaluate(
            "States.Omit($.obj, 'password')",
            ['obj' => ['name' => 'John', 'password' => 'secret123']]
        );
        $this->assertEquals(['name' => 'John'], $result);
        $this->assertArrayNotHasKey('password', $result);
    }

    /**
     * Test intrinsic-functions.md - Token functions.
     */
    public function testIntrinsicFunctionsTokens(): void
    {
        // Test States.TokenCount
        $result = IntrinsicFunctions::evaluate("States.TokenCount('Hello world')", []);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        // Test States.Truncate
        $longText = str_repeat('word ', 100);
        $result = IntrinsicFunctions::evaluate('States.Truncate($.text, 10)', ['text' => $longText]);
        $this->assertLessThanOrEqual(43, strlen($result)); // 10*4 + 3 for "..."
    }

    /**
     * Test intrinsic-functions.md - Cost tracking functions.
     */
    public function testIntrinsicFunctionsCostTracking(): void
    {
        // Test States.CurrentCost with context
        $context = ['Execution' => ['Cost' => 0.025]];
        $result = IntrinsicFunctions::evaluate('States.CurrentCost()', [], $context);
        $this->assertEquals(0.025, $result);

        // Test States.CurrentTokens with context
        $context = ['Execution' => ['TokensUsed' => 2500]];
        $result = IntrinsicFunctions::evaluate('States.CurrentTokens()', [], $context);
        $this->assertEquals(2500, $result);
    }

    /**
     * Test agent-adapters.md - Custom agent implementation.
     */
    public function testAgentAdaptersCustomAgent(): void
    {
        // Custom agent with specific behavior
        $customAgent = new class implements AgentInterface {
            private int $callCount = 0;
            
            public function getName(): string { return 'CustomAgent'; }
            
            public function execute(array $parameters): array
            {
                $this->callCount++;
                return [
                    'callNumber' => $this->callCount,
                    'receivedInput' => $parameters,
                    'timestamp' => date('c')
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('CustomAgent', $customAgent);

        $workflow = [
            'StartAt' => 'UseCustom',
            'States' => [
                'UseCustom' => [
                    'Type' => 'Task',
                    'Agent' => 'CustomAgent',
                    'Parameters' => [
                        'data.$' => '$.input'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['input' => 'test_data']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->getOutput()['callNumber']);
        $this->assertEquals('test_data', $result->getOutput()['receivedInput']['data']);
    }

    /**
     * Test production-deployment.md - Configuration patterns.
     */
    public function testProductionDeploymentConfiguration(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'ConfigurableAgent'; }
            public function execute(array $parameters): array
            {
                return [
                    'environment' => $parameters['env'] ?? 'unknown',
                    'processed' => true
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ConfigurableAgent', $mockAgent);

        // Workflow with environment-specific configuration
        $workflow = [
            'Comment' => 'Production-ready workflow',
            'StartAt' => 'ConfiguredTask',
            'States' => [
                'ConfiguredTask' => [
                    'Type' => 'Task',
                    'Agent' => 'ConfigurableAgent',
                    'Parameters' => [
                        'env.$' => '$.environment',
                        'settings.$' => '$.settings'
                    ],
                    'TimeoutSeconds' => 30,
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([
            'environment' => 'production',
            'settings' => ['maxRetries' => 3, 'timeout' => 30]
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('production', $result->getOutput()['environment']);
    }

    /**
     * Test migrating-from-hardcoded.md - Before/after comparison.
     */
    public function testMigratingFromHardcoded(): void
    {
        // The "after" - a configurable workflow
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'ConfigurableProcessor'; }
            public function execute(array $parameters): array
            {
                $strategy = $parameters['strategy'] ?? 'default';
                return [
                    'processedWith' => $strategy,
                    'success' => true
                ];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('ConfigurableProcessor', $mockAgent);

        // Workflow that replaces hardcoded if/else logic
        $workflow = [
            'Comment' => 'Configurable workflow replacing hardcoded logic',
            'StartAt' => 'SelectStrategy',
            'States' => [
                'SelectStrategy' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.priority',
                            'StringEquals' => 'high',
                            'Next' => 'FastProcess'
                        ],
                        [
                            'Variable' => '$.priority',
                            'StringEquals' => 'low',
                            'Next' => 'SlowProcess'
                        ]
                    ],
                    'Default' => 'StandardProcess'
                ],
                'FastProcess' => [
                    'Type' => 'Task',
                    'Agent' => 'ConfigurableProcessor',
                    'Parameters' => [
                        'strategy' => 'fast',
                        'data.$' => '$.data'
                    ],
                    'End' => true
                ],
                'SlowProcess' => [
                    'Type' => 'Task',
                    'Agent' => 'ConfigurableProcessor',
                    'Parameters' => [
                        'strategy' => 'thorough',
                        'data.$' => '$.data'
                    ],
                    'End' => true
                ],
                'StandardProcess' => [
                    'Type' => 'Task',
                    'Agent' => 'ConfigurableProcessor',
                    'Parameters' => [
                        'strategy' => 'balanced',
                        'data.$' => '$.data'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);

        // Test high priority path
        $result = $engine->run(['priority' => 'high', 'data' => 'test']);
        $this->assertEquals('fast', $result->getOutput()['processedWith']);

        // Test low priority path
        $result = $engine->run(['priority' => 'low', 'data' => 'test']);
        $this->assertEquals('thorough', $result->getOutput()['processedWith']);

        // Test default path
        $result = $engine->run(['priority' => 'medium', 'data' => 'test']);
        $this->assertEquals('balanced', $result->getOutput()['processedWith']);
    }

    /**
     * Test workflow execution tracing.
     */
    public function testWorkflowTracing(): void
    {
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'TracingAgent'; }
            public function execute(array $parameters): array
            {
                return ['step' => $parameters['step'] ?? 'unknown'];
            }
        };

        $registry = new AgentRegistry();
        $registry->register('TracingAgent', $mockAgent);

        $workflow = [
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Task',
                    'Agent' => 'TracingAgent',
                    'Parameters' => ['step' => 'first'],
                    'ResultPath' => '$.step1',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Task',
                    'Agent' => 'TracingAgent',
                    'Parameters' => ['step' => 'second'],
                    'ResultPath' => '$.step2',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'step1.$' => '$.step1',
                        'step2.$' => '$.step2',
                        'finalStep' => 'third'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        
        // Verify trace includes all states
        $trace = $result->getTrace();
        $this->assertNotEmpty($trace);
        
        // Verify output has results from all steps
        $output = $result->getOutput();
        $this->assertEquals('first', $output['step1']['step']);
        $this->assertEquals('second', $output['step2']['step']);
        $this->assertEquals('third', $output['finalStep']);
    }
}
