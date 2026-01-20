<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Exceptions\ASLException;
use AgentStateLanguage\Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    private function createTestAgent(array $result = []): AgentInterface
    {
        return new class($result) implements AgentInterface {
            private array $result;
            public function __construct(array $result) { $this->result = $result; }
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array { return $this->result; }
        };
    }

    public function testCreateFromFile(): void
    {
        $registry = new AgentRegistry();
        $registry->register('MockAgent', $this->createTestAgent(['success' => true]));
        
        $path = dirname(__DIR__, 3) . '/examples/code-review/workflow.asl.json';
        $engine = WorkflowEngine::fromFile($path, $registry);
        
        $this->assertInstanceOf(WorkflowEngine::class, $engine);
    }

    public function testCreateFromFileNotFound(): void
    {
        $registry = new AgentRegistry();
        
        $this->expectException(ASLException::class);
        WorkflowEngine::fromFile('/nonexistent/path.json', $registry);
    }

    public function testCreateFromJson(): void
    {
        $registry = new AgentRegistry();
        
        $json = json_encode([
            'StartAt' => 'Start',
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ]);
        
        $engine = WorkflowEngine::fromJson($json, $registry);
        $this->assertInstanceOf(WorkflowEngine::class, $engine);
    }

    public function testCreateFromInvalidJson(): void
    {
        $registry = new AgentRegistry();
        
        $this->expectException(ASLException::class);
        WorkflowEngine::fromJson('not valid json', $registry);
    }

    public function testRunSimpleWorkflow(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'Result' => ['message' => 'Hello'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['input' => 'data']);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['message' => 'Hello'], $result->getOutput());
    }

    public function testRunMissingStartAt(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('States.ValidationError', $result->getError());
    }

    public function testRunStateNotFound(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'NonExistent',
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('States.StateNotFound', $result->getError());
    }

    public function testRegisterAgent(): void
    {
        $registry = new AgentRegistry();
        $workflow = [
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'Agent' => 'MyAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $engine->registerAgent('MyAgent', $this->createTestAgent(['done' => true]));
        
        $result = $engine->run([]);
        $this->assertTrue($result->isSuccess());
    }

    public function testGetDefinition(): void
    {
        $registry = new AgentRegistry();
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $this->assertEquals($workflow, $engine->getDefinition());
    }

    public function testGetStateNames(): void
    {
        $registry = new AgentRegistry();
        $workflow = [
            'StartAt' => 'First',
            'States' => [
                'First' => ['Type' => 'Pass', 'Next' => 'Second'],
                'Second' => ['Type' => 'Pass', 'Next' => 'Third'],
                'Third' => ['Type' => 'Succeed']
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $stateNames = $engine->getStateNames();
        
        $this->assertContains('First', $stateNames);
        $this->assertContains('Second', $stateNames);
        $this->assertContains('Third', $stateNames);
    }

    public function testSequentialExecution(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Pass',
                    'Result' => ['step' => 1],
                    'ResultPath' => '$.result1',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Pass',
                    'Result' => ['step' => 2],
                    'ResultPath' => '$.result2',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'combined.$' => '$.result1.step'
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->getOutput()['combined']);
    }

    public function testExecutionTrace(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
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
        
        $trace = $result->getTrace();
        $this->assertNotEmpty($trace);
        
        // Should have workflow_start entry
        $types = array_column($trace, 'type');
        $this->assertContains('workflow_start', $types);
    }

    public function testTaskStateExecution(): void
    {
        $registry = new AgentRegistry();
        $registry->register('TestAgent', $this->createTestAgent([
            'processed' => true,
            'value' => 42
        ]));
        
        $workflow = [
            'StartAt' => 'Process',
            'States' => [
                'Process' => [
                    'Type' => 'Task',
                    'Agent' => 'TestAgent',
                    'Parameters' => [
                        'input.$' => '$.data'
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run(['data' => 'test']);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(42, $result->getOutput()['value']);
    }

    public function testChoiceStateExecution(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'Decide',
            'States' => [
                'Decide' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.score',
                            'NumericGreaterThan' => 50,
                            'Next' => 'High'
                        ]
                    ],
                    'Default' => 'Low'
                ],
                'High' => [
                    'Type' => 'Pass',
                    'Result' => ['level' => 'high'],
                    'End' => true
                ],
                'Low' => [
                    'Type' => 'Pass',
                    'Result' => ['level' => 'low'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        
        // Test high path
        $result = $engine->run(['score' => 75]);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('high', $result->getOutput()['level']);
        
        // Test low path
        $result2 = $engine->run(['score' => 25]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('low', $result2->getOutput()['level']);
    }

    public function testDurationTracking(): void
    {
        $registry = new AgentRegistry();
        
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $registry);
        $result = $engine->run([]);
        
        $this->assertGreaterThanOrEqual(0, $result->getDuration());
    }
}
