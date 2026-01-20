<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use PHPUnit\Framework\TestCase;

class MultiAgentDebateTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testBasicDebate(): void
    {
        $optimist = $this->createMock(AgentInterface::class);
        $optimist->method('run')->willReturnCallback(function ($input) {
            $round = $input['round'] ?? 1;
            return [
                'argument' => "Optimist argument for round $round",
                'position' => 'pro'
            ];
        });
        
        $pessimist = $this->createMock(AgentInterface::class);
        $pessimist->method('run')->willReturnCallback(function ($input) {
            $round = $input['round'] ?? 1;
            return [
                'argument' => "Pessimist counter-argument for round $round",
                'position' => 'con'
            ];
        });
        
        $judge = $this->createMock(AgentInterface::class);
        $judge->method('run')->willReturn([
            'winner' => 'OptimistAgent',
            'reasoning' => 'Pro arguments were more convincing',
            'consensus' => 'The topic has merit but requires careful consideration'
        ]);
        
        $this->registry->register('OptimistAgent', $optimist);
        $this->registry->register('PessimistAgent', $pessimist);
        $this->registry->register('JudgeAgent', $judge);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Debate',
            'States' => [
                'Debate' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'OptimistAgent', 'Role' => 'advocate'],
                        ['Agent' => 'PessimistAgent', 'Role' => 'critic']
                    ],
                    'Topic.$' => '$.question',
                    'Rounds' => 3,
                    'Judge' => 'JudgeAgent',
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run(['question' => 'Is AI beneficial for society?']);
        
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        $this->assertEquals('OptimistAgent', $output['winner']);
        $this->assertArrayHasKey('rounds', $output);
        $this->assertCount(3, $output['rounds']);
    }

    public function testConsensusMode(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')->willReturn([
            'position' => 'We should implement solution A',
            'confidence' => 0.8
        ]);
        
        $agent2 = $this->createMock(AgentInterface::class);
        $agent2->method('run')->willReturn([
            'position' => 'Solution A is reasonable with modifications',
            'confidence' => 0.9
        ]);
        
        $this->registry->register('Agent1', $agent1);
        $this->registry->register('Agent2', $agent2);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ConsensusBuilding',
            'States' => [
                'ConsensusBuilding' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'Agent1'],
                        ['Agent' => 'Agent2']
                    ],
                    'Topic' => 'Find best solution',
                    'Mode' => 'consensus',
                    'ConsensusThreshold' => 0.7,
                    'Rounds' => 3,
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        $this->assertArrayHasKey('consensusReached', $output);
    }

    public function testDebateWithContextSharing(): void
    {
        $conversationHistory = [];
        
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')->willReturnCallback(function ($input) use (&$conversationHistory) {
            $conversationHistory[] = ['agent' => 'Agent1', 'input' => $input];
            return [
                'argument' => 'First agent position',
                'references' => ['previous_rounds' => count($conversationHistory)]
            ];
        });
        
        $agent2 = $this->createMock(AgentInterface::class);
        $agent2->method('run')->willReturnCallback(function ($input) use (&$conversationHistory) {
            $conversationHistory[] = ['agent' => 'Agent2', 'input' => $input];
            return [
                'argument' => 'Second agent response',
                'references' => ['previous_rounds' => count($conversationHistory)]
            ];
        });
        
        $this->registry->register('Agent1', $agent1);
        $this->registry->register('Agent2', $agent2);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'SharedContextDebate',
            'States' => [
                'SharedContextDebate' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'Agent1'],
                        ['Agent' => 'Agent2']
                    ],
                    'Topic' => 'Shared context topic',
                    'Rounds' => 2,
                    'ShareHistory' => true,
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        // Both agents should have been called multiple times
        $this->assertGreaterThanOrEqual(2, count($conversationHistory));
    }

    public function testDebateWithEarlyConsensus(): void
    {
        $round = 0;
        
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')->willReturnCallback(function () use (&$round) {
            $round++;
            return [
                'position' => 'We agree on this point',
                'agreement' => $round >= 2 ? 1.0 : 0.5
            ];
        });
        
        $agent2 = $this->createMock(AgentInterface::class);
        $agent2->method('run')->willReturn([
            'position' => 'I concur',
            'agreement' => 1.0
        ]);
        
        $this->registry->register('Agent1', $agent1);
        $this->registry->register('Agent2', $agent2);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'EarlyConsensus',
            'States' => [
                'EarlyConsensus' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'Agent1'],
                        ['Agent' => 'Agent2']
                    ],
                    'Topic' => 'Find common ground',
                    'Mode' => 'consensus',
                    'ConsensusThreshold' => 0.9,
                    'Rounds' => 10,
                    'StopOnConsensus' => true,
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        // Should have stopped early, not completed all 10 rounds
        $this->assertLessThan(10, count($output['rounds'] ?? []));
    }

    public function testDebateFollowedByDecision(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')->willReturn(['argument' => 'Pro position']);
        
        $agent2 = $this->createMock(AgentInterface::class);
        $agent2->method('run')->willReturn(['argument' => 'Con position']);
        
        $judge = $this->createMock(AgentInterface::class);
        $judge->method('run')->willReturn([
            'winner' => 'Agent1',
            'confidence' => 0.85,
            'recommendation' => 'proceed'
        ]);
        
        $this->registry->register('Agent1', $agent1);
        $this->registry->register('Agent2', $agent2);
        $this->registry->register('JudgeAgent', $judge);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Deliberate',
            'States' => [
                'Deliberate' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'Agent1'],
                        ['Agent' => 'Agent2']
                    ],
                    'Topic' => 'Should we proceed?',
                    'Rounds' => 2,
                    'Judge' => 'JudgeAgent',
                    'ResultPath' => '$.debate',
                    'Next' => 'DecideBasedOnDebate'
                ],
                'DecideBasedOnDebate' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.debate.recommendation',
                            'StringEquals' => 'proceed',
                            'Next' => 'Proceed'
                        ]
                    ],
                    'Default' => 'Halt'
                ],
                'Proceed' => [
                    'Type' => 'Pass',
                    'Result' => ['decision' => 'proceeding'],
                    'End' => true
                ],
                'Halt' => [
                    'Type' => 'Pass',
                    'Result' => ['decision' => 'halted'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['decision' => 'proceeding'], $result->getOutput());
    }

    public function testDebateWithRules(): void
    {
        $capturedInputs = [];
        
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturnCallback(function ($input) use (&$capturedInputs) {
            $capturedInputs[] = $input;
            return ['argument' => 'Position stated'];
        });
        
        $this->registry->register('RulesAgent', $agent);
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'RuledDebate',
            'States' => [
                'RuledDebate' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'RulesAgent'],
                        ['Agent' => 'RulesAgent']
                    ],
                    'Topic' => 'Debate with rules',
                    'Rounds' => 1,
                    'Rules' => [
                        'MaxTokensPerTurn' => 500,
                        'RequireCitations' => true,
                        'AllowRebuttals' => true,
                        'Format' => 'structured'
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        
        // Verify rules were passed to agents
        foreach ($capturedInputs as $input) {
            $this->assertArrayHasKey('rules', $input);
        }
    }

    public function testParallelDebates(): void
    {
        $createDebateAgent = function (string $name) {
            $agent = $this->createMock(AgentInterface::class);
            $agent->method('run')->willReturn([
                'argument' => "Argument from $name",
                'position' => 'stated'
            ]);
            return $agent;
        };
        
        $this->registry->register('TopicAAgent1', $createDebateAgent('TopicA-1'));
        $this->registry->register('TopicAAgent2', $createDebateAgent('TopicA-2'));
        $this->registry->register('TopicBAgent1', $createDebateAgent('TopicB-1'));
        $this->registry->register('TopicBAgent2', $createDebateAgent('TopicB-2'));
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ParallelDebates',
            'States' => [
                'ParallelDebates' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'DebateTopicA',
                            'States' => [
                                'DebateTopicA' => [
                                    'Type' => 'Debate',
                                    'Participants' => [
                                        ['Agent' => 'TopicAAgent1'],
                                        ['Agent' => 'TopicAAgent2']
                                    ],
                                    'Topic' => 'Topic A',
                                    'Rounds' => 1,
                                    'End' => true
                                ]
                            ]
                        ],
                        [
                            'StartAt' => 'DebateTopicB',
                            'States' => [
                                'DebateTopicB' => [
                                    'Type' => 'Debate',
                                    'Participants' => [
                                        ['Agent' => 'TopicBAgent1'],
                                        ['Agent' => 'TopicBAgent2']
                                    ],
                                    'Topic' => 'Topic B',
                                    'Rounds' => 1,
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
        $this->assertCount(2, $output); // Two parallel debate results
    }
}
