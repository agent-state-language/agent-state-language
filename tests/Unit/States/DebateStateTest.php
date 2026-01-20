<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\DebateState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DebateStateTest extends TestCase
{
    private AgentRegistry|MockObject $registry;
    private AgentInterface|MockObject $agent1;
    private AgentInterface|MockObject $agent2;
    private AgentInterface|MockObject $judge;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistry::class);
        $this->agent1 = $this->createMock(AgentInterface::class);
        $this->agent2 = $this->createMock(AgentInterface::class);
        $this->judge = $this->createMock(AgentInterface::class);
    }

    public function testGetParticipants(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'OptimistAgent', 'Role' => 'advocate'],
                ['Agent' => 'PessimistAgent', 'Role' => 'critic']
            ],
            'Topic.$' => '$.question',
            'Rounds' => 3,
            'Judge' => 'JudgeAgent',
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $participants = $state->getParticipants();
        
        $this->assertCount(2, $participants);
        $this->assertEquals('OptimistAgent', $participants[0]['Agent']);
        $this->assertEquals('advocate', $participants[0]['Role']);
    }

    public function testGetRounds(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Rounds' => 5,
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals(5, $state->getRounds());
    }

    public function testGetJudge(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Judge' => 'JudgeAgent',
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('JudgeAgent', $state->getJudge());
    }

    public function testExecuteDebate(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1', 'Role' => 'pro'],
                ['Agent' => 'Agent2', 'Role' => 'con']
            ],
            'Topic.$' => '$.question',
            'Rounds' => 2,
            'Judge' => 'JudgeAgent',
            'ResultPath' => '$.debate',
            'Next' => 'NextState'
        ];
        
        // Setup mocks
        $this->registry->method('get')
            ->willReturnMap([
                ['Agent1', $this->agent1],
                ['Agent2', $this->agent2],
                ['JudgeAgent', $this->judge]
            ]);
        
        $this->agent1->method('run')->willReturn(['response' => 'Pro argument']);
        $this->agent2->method('run')->willReturn(['response' => 'Con argument']);
        $this->judge->method('run')->willReturn([
            'winner' => 'Agent1',
            'reasoning' => 'Pro arguments were more compelling',
            'consensus' => 'The topic has merit with some concerns'
        ]);
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        $context = new ExecutionContext(['question' => 'Is AI beneficial?']);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        
        $output = $result->getOutput();
        $this->assertArrayHasKey('winner', $output);
        $this->assertArrayHasKey('consensus', $output);
    }

    public function testDebateWithoutJudge(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Rounds' => 2,
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')
            ->willReturnMap([
                ['Agent1', $this->agent1],
                ['Agent2', $this->agent2]
            ]);
        
        $this->agent1->method('run')->willReturn(['argument' => 'Position 1']);
        $this->agent2->method('run')->willReturn(['argument' => 'Position 2']);
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        
        // Without a judge, should have rounds but no winner
        $this->assertArrayHasKey('rounds', $output);
    }

    public function testConsensusMode(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Rounds' => 3,
            'Mode' => 'consensus',
            'ConsensusThreshold' => 0.8,
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('consensus', $state->getMode());
        $this->assertEquals(0.8, $state->getConsensusThreshold());
    }

    public function testAdversarialMode(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Mode' => 'adversarial',
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('adversarial', $state->getMode());
    }

    public function testCollaborativeMode(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Build a solution',
            'Mode' => 'collaborative',
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('collaborative', $state->getMode());
    }

    public function testRulesConfiguration(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Rules' => [
                'MaxTokensPerTurn' => 500,
                'RequireCitations' => true,
                'AllowRebuttals' => true
            ],
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $rules = $state->getRules();
        
        $this->assertEquals(500, $rules['MaxTokensPerTurn']);
        $this->assertTrue($rules['RequireCitations']);
        $this->assertTrue($rules['AllowRebuttals']);
    }

    public function testGetType(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [],
            'Topic' => 'Test',
            'Next' => 'Next'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('Debate', $state->getType());
    }

    public function testGetName(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [],
            'Topic' => 'Test',
            'Next' => 'Next'
        ];
        
        $state = new DebateState('MyDebate', $definition, $this->registry);
        
        $this->assertEquals('MyDebate', $state->getName());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Final topic',
            'Rounds' => 1,
            'End' => true
        ];
        
        $this->registry->method('get')->willReturn($this->agent1);
        $this->agent1->method('run')->willReturn(['argument' => 'Position']);
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
    }

    public function testTopicResolutionFromPath(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic.$' => '$.debate.topic',
            'Rounds' => 1,
            'Next' => 'NextState'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        $context = new ExecutionContext([
            'debate' => ['topic' => 'Should we adopt this approach?']
        ]);
        
        $resolvedTopic = $state->resolveTopic($context);
        
        $this->assertEquals('Should we adopt this approach?', $resolvedTopic);
    }

    public function testDebateHistory(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [
                ['Agent' => 'Agent1'],
                ['Agent' => 'Agent2']
            ],
            'Topic' => 'Test topic',
            'Rounds' => 2,
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')
            ->willReturnCallback(function ($name) {
                $agent = $this->createMock(AgentInterface::class);
                $agent->method('run')->willReturn([
                    'argument' => "Argument from $name"
                ]);
                return $agent;
            });
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $output = $result->getOutput();
        
        // Should have debate rounds captured
        $this->assertArrayHasKey('rounds', $output);
        $this->assertCount(2, $output['rounds']);
    }

    public function testGetComment(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Comment' => 'Multi-agent deliberation',
            'Participants' => [],
            'Topic' => 'Test',
            'Next' => 'Next'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('Multi-agent deliberation', $state->getComment());
    }

    public function testTimeLimit(): void
    {
        $definition = [
            'Type' => 'Debate',
            'Participants' => [],
            'Topic' => 'Test',
            'TimeLimit' => '10m',
            'Next' => 'Next'
        ];
        
        $state = new DebateState('TestDebate', $definition, $this->registry);
        
        $this->assertEquals('10m', $state->getTimeLimit());
    }
}
