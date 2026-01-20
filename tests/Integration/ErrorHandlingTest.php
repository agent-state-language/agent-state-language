<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Exceptions\BudgetExceededException;
use AgentStateLanguage\Exceptions\TimeoutException;
use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testRetryWithExponentialBackoff(): void
    {
        $attempts = [];
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturnCallback(function () use (&$attempts) {
                $attempts[] = microtime(true);
                if (count($attempts) < 3) {
                    throw new AgentException('TransientError', 'Temporary failure');
                }
                return ['success' => true];
            });
        
        $this->registry->register('RetryAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'RetryableTask',
            'States' => [
                'RetryableTask' => [
                    'Type' => 'Task',
                    'Agent' => 'RetryAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['TransientError'],
                            'MaxAttempts' => 5,
                            'IntervalSeconds' => 0, // For fast testing
                            'BackoffRate' => 2.0
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $attempts);
    }

    public function testRetryExhaustedFallsToCatch(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('PersistentError', 'Always fails'));
        
        $this->registry->register('FailingAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'FailingTask',
            'States' => [
                'FailingTask' => [
                    'Type' => 'Task',
                    'Agent' => 'FailingAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['PersistentError'],
                            'MaxAttempts' => 2,
                            'IntervalSeconds' => 0
                        ]
                    ],
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
                    'Result' => ['recovered' => true],
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
        $this->assertEquals(['recovered' => true], $result->getOutput());
    }

    public function testSpecificErrorMatching(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('SpecificError', 'Specific failure'));
        
        $this->registry->register('SpecificFailAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'SpecificFailAgent',
                    'Catch' => [
                        [
                            'ErrorEquals' => ['SpecificError'],
                            'Next' => 'HandleSpecific'
                        ],
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'HandleGeneric'
                        ]
                    ],
                    'End' => true
                ],
                'HandleSpecific' => [
                    'Type' => 'Pass',
                    'Result' => ['handler' => 'specific'],
                    'End' => true
                ],
                'HandleGeneric' => [
                    'Type' => 'Pass',
                    'Result' => ['handler' => 'generic'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertEquals(['handler' => 'specific'], $result->getOutput());
    }

    public function testMultipleRetryPolicies(): void
    {
        $callCount = 0;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount < 5) {
                    throw new AgentException('TransientError', 'Retry me');
                }
                return ['success' => true];
            });
        
        $this->registry->register('MultiRetryAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'MultiRetryAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['NetworkError'],
                            'MaxAttempts' => 3,
                            'IntervalSeconds' => 0
                        ],
                        [
                            'ErrorEquals' => ['TransientError'],
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
        $this->assertEquals(5, $callCount);
    }

    public function testErrorResultPathIncludesErrorInfo(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('CustomError', 'Detailed error message'));
        
        $this->registry->register('ErrorInfoAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'ErrorInfoAgent',
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'HandleError',
                            'ResultPath' => '$.errorInfo'
                        ]
                    ],
                    'End' => true
                ],
                'HandleError' => [
                    'Type' => 'Pass',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['original' => 'data']);
        
        $output = $result->getOutput();
        
        $this->assertArrayHasKey('errorInfo', $output);
        $this->assertEquals('CustomError', $output['errorInfo']['Error']);
        $this->assertEquals('Detailed error message', $output['errorInfo']['Cause']);
    }

    public function testCatchInNestedWorkflow(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('NestedError', 'Failure in nested state'));
        
        $this->registry->register('NestedFailAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Parallel',
            'States' => [
                'Parallel' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Branch1Task',
                            'States' => [
                                'Branch1Task' => [
                                    'Type' => 'Pass',
                                    'Result' => ['branch' => 1],
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Branch2Task',
                            'States' => [
                                'Branch2Task' => [
                                    'Type' => 'Task',
                                    'Agent' => 'NestedFailAgent',
                                    'End' => true
                                ]
                            ]
                        ]
                    ],
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'HandleParallelError'
                        ]
                    ],
                    'Next' => 'Done'
                ],
                'HandleParallelError' => [
                    'Type' => 'Pass',
                    'Result' => ['parallelRecovered' => true],
                    'End' => true
                ],
                'Done' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['parallelRecovered' => true], $result->getOutput());
    }

    public function testMapStateWithToleratedFailures(): void
    {
        $callIndex = 0;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willReturnCallback(function () use (&$callIndex) {
                $callIndex++;
                if ($callIndex === 2) {
                    throw new AgentException('ItemError', 'Second item failed');
                }
                return ['processed' => true, 'index' => $callIndex];
            });
        
        $this->registry->register('MapItemAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'ToleratedFailureCount' => 1,
                    'Iterator' => [
                        'StartAt' => 'ProcessItem',
                        'States' => [
                            'ProcessItem' => [
                                'Type' => 'Task',
                                'Agent' => 'MapItemAgent',
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['items' => ['a', 'b', 'c']]);
        
        // Should succeed because we tolerate 1 failure
        $this->assertTrue($result->isSuccess());
    }

    public function testUncaughtErrorPropagates(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('UncaughtError', 'No handler for this'));
        
        $this->registry->register('UncaughtAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'UncaughtAgent',
                    'End' => true
                    // No Catch defined
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('UncaughtError', $result->getError());
    }

    public function testStatesAllMatchesAnyError(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')
            ->willThrowException(new AgentException('RandomError', 'Some error'));
        
        $this->registry->register('RandomErrorAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'RandomErrorAgent',
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'CatchAll'
                        ]
                    ],
                    'End' => true
                ],
                'CatchAll' => [
                    'Type' => 'Pass',
                    'Result' => ['caught' => 'all'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['caught' => 'all'], $result->getOutput());
    }

    public function testRetryJitterConfiguration(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'TestAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'MaxAttempts' => 3,
                            'IntervalSeconds' => 1,
                            'BackoffRate' => 2.0,
                            'JitterStrategy' => 'FULL'
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        // Just verify the workflow parses correctly
        $this->registry->register('TestAgent', $this->createSuccessAgent());
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
    }

    private function createSuccessAgent(): AgentInterface
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn(['success' => true]);
        return $agent;
    }
}
