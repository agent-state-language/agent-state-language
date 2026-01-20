<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Exceptions\BudgetExceededException;
use PHPUnit\Framework\TestCase;

class CostTrackingTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testBasicCostTracking(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => [
                'cost' => 0.10,
                'tokens' => 500,
                'inputTokens' => 200,
                'outputTokens' => 300
            ]
        ]);
        
        $this->registry->register('CostlyAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task1',
            'States' => [
                'Task1' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'Next' => 'Task2'
                ],
                'Task2' => [
                    'Type' => 'Task',
                    'Agent' => 'CostlyAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0.20, $result->getTotalCost());
        $this->assertEquals(1000, $result->getTotalTokens());
    }

    public function testBudgetEnforcement(): void
    {
        $callCount = 0;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return [
                'result' => 'success',
                '_metadata' => ['cost' => 0.50, 'tokens' => 1000]
            ];
        });
        
        $this->registry->register('ExpensiveAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task1',
            'Budget' => [
                'MaxCost' => '$1.00',
                'OnExceed' => 'Fail'
            ],
            'States' => [
                'Task1' => [
                    'Type' => 'Task',
                    'Agent' => 'ExpensiveAgent',
                    'Next' => 'Task2'
                ],
                'Task2' => [
                    'Type' => 'Task',
                    'Agent' => 'ExpensiveAgent',
                    'Next' => 'Task3'
                ],
                'Task3' => [
                    'Type' => 'Task',
                    'Agent' => 'ExpensiveAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        // Should fail because budget exceeded after 2 tasks
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(2, $callCount);
    }

    public function testBudgetWithTokenLimit(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => ['cost' => 0.01, 'tokens' => 5000]
        ]);
        
        $this->registry->register('TokenHeavyAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task1',
            'Budget' => [
                'MaxTokens' => 8000,
                'OnExceed' => 'Fail'
            ],
            'States' => [
                'Task1' => [
                    'Type' => 'Task',
                    'Agent' => 'TokenHeavyAgent',
                    'Next' => 'Task2'
                ],
                'Task2' => [
                    'Type' => 'Task',
                    'Agent' => 'TokenHeavyAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertFalse($result->isSuccess());
    }

    public function testBudgetFallbackModel(): void
    {
        $modelUsed = null;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturnCallback(function ($input) use (&$modelUsed) {
            $modelUsed = $input['_model'] ?? 'default';
            $cost = $modelUsed === 'claude-3-sonnet' ? 0.02 : 0.10;
            return [
                'result' => 'success',
                '_metadata' => ['cost' => $cost, 'tokens' => 500]
            ];
        });
        
        $this->registry->register('FallbackAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task',
            'Budget' => [
                'MaxCost' => '$0.50',
                'Fallback' => [
                    'When' => 'BudgetAt80Percent',
                    'UseModel' => 'claude-3-sonnet'
                ]
            ],
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'FallbackAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
    }

    public function testBudgetAlerts(): void
    {
        $alerts = [];
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => ['cost' => 0.30, 'tokens' => 500]
        ]);
        
        $this->registry->register('AlertAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task1',
            'Budget' => [
                'MaxCost' => '$1.00',
                'Alerts' => [
                    ['At' => '50%', 'Notify' => ['budget@example.com']],
                    ['At' => '75%', 'Notify' => ['budget@example.com', 'manager@example.com']]
                ]
            ],
            'States' => [
                'Task1' => [
                    'Type' => 'Task',
                    'Agent' => 'AlertAgent',
                    'Next' => 'Task2'
                ],
                'Task2' => [
                    'Type' => 'Task',
                    'Agent' => 'AlertAgent',
                    'Next' => 'Task3'
                ],
                'Task3' => [
                    'Type' => 'Task',
                    'Agent' => 'AlertAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $engine->setAlertHandler(function ($level, $message) use (&$alerts) {
            $alerts[] = ['level' => $level, 'message' => $message];
        });
        
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($alerts);
    }

    public function testCostTrackingInParallel(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => ['cost' => 0.10, 'tokens' => 200]
        ]);
        
        $this->registry->register('ParallelAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ParallelTasks',
            'States' => [
                'ParallelTasks' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Branch1',
                            'States' => [
                                'Branch1' => [
                                    'Type' => 'Task',
                                    'Agent' => 'ParallelAgent',
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Branch2',
                            'States' => [
                                'Branch2' => [
                                    'Type' => 'Task',
                                    'Agent' => 'ParallelAgent',
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'Branch3',
                            'States' => [
                                'Branch3' => [
                                    'Type' => 'Task',
                                    'Agent' => 'ParallelAgent',
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
        $this->assertEquals(0.30, $result->getTotalCost());
        $this->assertEquals(600, $result->getTotalTokens());
    }

    public function testCostTrackingInMap(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'processed',
            '_metadata' => ['cost' => 0.05, 'tokens' => 100]
        ]);
        
        $this->registry->register('MapAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ProcessItems',
            'States' => [
                'ProcessItems' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'Iterator' => [
                        'StartAt' => 'ProcessItem',
                        'States' => [
                            'ProcessItem' => [
                                'Type' => 'Task',
                                'Agent' => 'MapAgent',
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['items' => [1, 2, 3, 4, 5]]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0.25, $result->getTotalCost());
        $this->assertEquals(500, $result->getTotalTokens());
    }

    public function testCostBreakdownByState(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => ['cost' => 0.15, 'tokens' => 300]
        ]);
        
        $this->registry->register('TrackedAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'Task',
                    'Agent' => 'TrackedAgent',
                    'Next' => 'State2'
                ],
                'State2' => [
                    'Type' => 'Task',
                    'Agent' => 'TrackedAgent',
                    'Next' => 'State3'
                ],
                'State3' => [
                    'Type' => 'Task',
                    'Agent' => 'TrackedAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        
        $costBreakdown = $result->getCostBreakdown();
        
        $this->assertArrayHasKey('State1', $costBreakdown);
        $this->assertArrayHasKey('State2', $costBreakdown);
        $this->assertArrayHasKey('State3', $costBreakdown);
        
        $this->assertEquals(0.15, $costBreakdown['State1']['cost']);
        $this->assertEquals(300, $costBreakdown['State1']['tokens']);
    }

    public function testBudgetPauseAndResume(): void
    {
        $callCount = 0;
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return [
                'result' => "call-$callCount",
                '_metadata' => ['cost' => 0.40, 'tokens' => 500]
            ];
        });
        
        $this->registry->register('PausableAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Task1',
            'Budget' => [
                'MaxCost' => '$1.00',
                'OnExceed' => 'PauseAndNotify'
            ],
            'States' => [
                'Task1' => [
                    'Type' => 'Task',
                    'Agent' => 'PausableAgent',
                    'Next' => 'Task2'
                ],
                'Task2' => [
                    'Type' => 'Task',
                    'Agent' => 'PausableAgent',
                    'Next' => 'Task3'
                ],
                'Task3' => [
                    'Type' => 'Task',
                    'Agent' => 'PausableAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        // Should pause, not fail
        $this->assertTrue($result->isPaused());
        $this->assertEquals(2, $callCount);
        
        // Get checkpoint to resume
        $checkpoint = $result->getCheckpoint();
        
        // Increase budget and resume
        $engine->setBudget(['MaxCost' => '$2.00']);
        $resumedResult = $engine->resume($checkpoint);
        
        $this->assertTrue($resumedResult->isSuccess());
        $this->assertEquals(3, $callCount);
    }
}
