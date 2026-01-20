<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\MapState;
use AgentStateLanguage\States\StateResult;
use AgentStateLanguage\Exceptions\StateException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MapStateTest extends TestCase
{
    private AgentRegistry|MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistry::class);
    }

    public function testExecuteWithSimpleIterator(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'ProcessItem',
                'States' => [
                    'ProcessItem' => [
                        'Type' => 'Pass',
                        'Parameters' => [
                            'processed.$' => '$$.Map.Item.Value',
                            'index.$' => '$$.Map.Item.Index'
                        ],
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext([
            'items' => ['a', 'b', 'c']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->getOutput());
    }

    public function testExecuteWithMaxConcurrency(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'MaxConcurrency' => 2,
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        
        $this->assertEquals(2, $state->getMaxConcurrency());
    }

    public function testExecuteWithItemSelector(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'ItemSelector' => [
                'item.$' => '$$.Map.Item.Value',
                'index.$' => '$$.Map.Item.Index',
                'context.$' => '$.sharedContext'
            ],
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext([
            'items' => [1, 2, 3],
            'sharedContext' => 'shared'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteWithResultPath(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'ResultPath' => '$.processed',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'Result' => ['done' => true],
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext([
            'items' => [1, 2],
            'original' => 'data'
        ]);
        
        $result = $state->execute($context);
        
        // Result contains the mapped output
        $this->assertIsArray($result->getOutput());
    }

    public function testExecuteWithEmptyArray(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext(['items' => []]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([], $result->getOutput());
    }

    public function testExecuteWithObjectItems(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.users',
            'Iterator' => [
                'StartAt' => 'ProcessUser',
                'States' => [
                    'ProcessUser' => [
                        'Type' => 'Pass',
                        'Parameters' => [
                            'userId.$' => '$$.Map.Item.Value.id',
                            'name.$' => '$$.Map.Item.Value.name'
                        ],
                        'End' => true
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext([
            'users' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane']
            ]
        ]);
        
        $result = $state->execute($context);
        
        $this->assertCount(2, $result->getOutput());
    }

    public function testRetryOnIteratorError(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'End' => true
                    ]
                ]
            ],
            'Retry' => [
                [
                    'ErrorEquals' => ['States.ALL'],
                    'MaxAttempts' => 3,
                    'IntervalSeconds' => 0
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        
        $this->assertNotEmpty($state->getRetryConfig());
    }

    public function testCatchOnError(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'End' => true
                    ]
                ]
            ],
            'Catch' => [
                [
                    'ErrorEquals' => ['States.ALL'],
                    'Next' => 'ErrorHandler',
                    'ResultPath' => '$.error'
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        
        $this->assertNotEmpty($state->getCatchConfig());
    }

    public function testGetType(): void
    {
        $state = new MapState('TestMap', [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ], $this->registry);
        
        $this->assertEquals('Map', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new MapState('MyMapState', [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ], $this->registry);
        
        $this->assertEquals('MyMapState', $state->getName());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'End' => true
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext(['items' => [1]]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
    }

    public function testToleratedFailureCount(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'ToleratedFailureCount' => 2,
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        
        $this->assertEquals(2, $state->getToleratedFailureCount());
    }

    public function testToleratedFailurePercentage(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'ToleratedFailurePercentage' => 10.0,
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        
        $this->assertEquals(10.0, $state->getToleratedFailurePercentage());
    }

    public function testInvalidItemsPathThrowsException(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.nonexistent',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext(['items' => [1, 2]]);
        
        $this->expectException(StateException::class);
        $state->execute($context);
    }

    public function testNonArrayItemsThrowsException(): void
    {
        $definition = [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => ['Type' => 'Pass', 'End' => true]
                ]
            ],
            'Next' => 'Next'
        ];
        
        $state = new MapState('TestMap', $definition, $this->registry);
        $context = new ExecutionContext(['items' => 'not an array']);
        
        $this->expectException(StateException::class);
        $state->execute($context);
    }
}
