<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Engine\WorkflowResult;
use PHPUnit\Framework\TestCase;

class WorkflowExecutionTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testExecuteSimplePassWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'Result' => ['message' => 'Hello, World!'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertInstanceOf(WorkflowResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['message' => 'Hello, World!'], $result->getOutput());
    }

    public function testExecuteSequentialWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Pass',
                    'Result' => ['step' => 1],
                    'ResultPath' => '$.step1',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Pass',
                    'Result' => ['step' => 2],
                    'ResultPath' => '$.step2',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Pass',
                    'Result' => ['step' => 3],
                    'ResultPath' => '$.step3',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        
        $this->assertArrayHasKey('step1', $output);
        $this->assertArrayHasKey('step2', $output);
        $this->assertArrayHasKey('step3', $output);
    }

    public function testExecuteChoiceWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'CheckValue',
            'States' => [
                'CheckValue' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.value',
                            'NumericGreaterThan' => 100,
                            'Next' => 'HighValue'
                        ],
                        [
                            'Variable' => '$.value',
                            'NumericLessThan' => 50,
                            'Next' => 'LowValue'
                        ]
                    ],
                    'Default' => 'MediumValue'
                ],
                'HighValue' => [
                    'Type' => 'Pass',
                    'Result' => ['category' => 'high'],
                    'End' => true
                ],
                'MediumValue' => [
                    'Type' => 'Pass',
                    'Result' => ['category' => 'medium'],
                    'End' => true
                ],
                'LowValue' => [
                    'Type' => 'Pass',
                    'Result' => ['category' => 'low'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        // Test high value
        $result = $engine->run(['value' => 150]);
        $this->assertEquals(['category' => 'high'], $result->getOutput());
        
        // Test low value
        $result = $engine->run(['value' => 25]);
        $this->assertEquals(['category' => 'low'], $result->getOutput());
        
        // Test medium value
        $result = $engine->run(['value' => 75]);
        $this->assertEquals(['category' => 'medium'], $result->getOutput());
    }

    public function testExecuteTaskWorkflow(): void
    {
        // Register a mock agent
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturnCallback(function ($input) {
                return ['processed' => $input['data'] . ' - processed'];
            });
        
        $this->registry->register('ProcessorAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Process',
            'States' => [
                'Process' => [
                    'Type' => 'Task',
                    'Agent' => 'ProcessorAgent',
                    'Parameters' => [
                        'data.$' => '$.input'
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['input' => 'test data']);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['processed' => 'test data - processed'], $result->getOutput());
    }

    public function testExecuteParallelWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ParallelProcess',
            'States' => [
                'ParallelProcess' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Branch1',
                            'States' => [
                                'Branch1' => [
                                    'Type' => 'Pass',
                                    'Result' => ['branch' => 1],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Branch2',
                            'States' => [
                                'Branch2' => [
                                    'Type' => 'Pass',
                                    'Result' => ['branch' => 2],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Branch3',
                            'States' => [
                                'Branch3' => [
                                    'Type' => 'Pass',
                                    'Result' => ['branch' => 3],
                                    'End' => true
                                ]
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        $this->assertCount(3, $output);
        $this->assertEquals(['branch' => 1], $output[0]);
        $this->assertEquals(['branch' => 2], $output[1]);
        $this->assertEquals(['branch' => 3], $output[2]);
    }

    public function testExecuteMapWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'Iterator' => [
                        'StartAt' => 'Transform',
                        'States' => [
                            'Transform' => [
                                'Type' => 'Pass',
                                'Parameters' => [
                                    'value.$' => '$$.Map.Item.Value',
                                    'index.$' => '$$.Map.Item.Index',
                                    'doubled.$' => 'States.MathMultiply($$.Map.Item.Value, 2)'
                                ],
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['items' => [1, 2, 3]]);
        
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        $this->assertCount(3, $output);
    }

    public function testExecuteWaitWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Wait',
            'States' => [
                'Wait' => [
                    'Type' => 'Wait',
                    'Seconds' => 0, // No actual wait for testing
                    'Next' => 'Done'
                ],
                'Done' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['data' => 'preserved']);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['data' => 'preserved'], $result->getOutput());
    }

    public function testExecuteFailWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
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
                    'Cause' => 'Input was not valid'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        // Test success path
        $result = $engine->run(['valid' => true]);
        $this->assertTrue($result->isSuccess());
        
        // Test failure path
        $result = $engine->run(['valid' => false]);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ValidationError', $result->getError());
    }

    public function testExecuteWithRetry(): void
    {
        $callCount = 0;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount < 3) {
                    throw new \RuntimeException('Transient error');
                }
                return ['success' => true];
            });
        
        $this->registry->register('RetryableAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'RetryableTask',
            'States' => [
                'RetryableTask' => [
                    'Type' => 'Task',
                    'Agent' => 'RetryableAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'MaxAttempts' => 5,
                            'IntervalSeconds' => 0
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $callCount);
    }

    public function testExecuteWithCatch(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new \RuntimeException('Expected failure'));
        
        $this->registry->register('FailingAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'FailingTask',
            'States' => [
                'FailingTask' => [
                    'Type' => 'Task',
                    'Agent' => 'FailingAgent',
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'HandleError',
                            'ResultPath' => '$.error'
                        ]
                    ],
                    'Next' => 'Success'
                ],
                'HandleError' => [
                    'Type' => 'Pass',
                    'Result' => ['handled' => true],
                    'End' => true
                ],
                'Success' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals(['handled' => true], $output);
    }

    public function testExecuteWithBudget(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturn([
                'result' => 'success',
                '_metadata' => ['cost' => 0.10, 'tokens' => 100]
            ]);
        
        $this->registry->register('CostlyAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'Budget' => [
                'MaxCost' => '$1.00',
                'MaxTokens' => 1000
            ],
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertLessThanOrEqual(1.0, $result->getTotalCost());
    }

    public function testExecuteFromJsonFile(): void
    {
        $workflowJson = json_encode([
            'Version' => '1.0',
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'Result' => ['loaded' => 'from_json'],
                    'End' => true
                ]
            ]
        ]);
        
        $engine = WorkflowEngine::fromJson($workflowJson, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['loaded' => 'from_json'], $result->getOutput());
    }

    public function testExecutionMetadata(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertNotEmpty($result->getExecutionId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getStartTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getEndTime());
        $this->assertGreaterThanOrEqual(0, $result->getDurationMs());
    }

    public function testStateHistory(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Pass',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Pass',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $history = $result->getStateHistory();
        
        $this->assertCount(3, $history);
        $this->assertEquals('Step1', $history[0]['state']);
        $this->assertEquals('Step2', $history[1]['state']);
        $this->assertEquals('Step3', $history[2]['state']);
    }
}
