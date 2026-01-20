<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Exceptions\ASLException;
use AgentStateLanguage\States\ApprovalState;
use AgentStateLanguage\States\CheckpointState;
use AgentStateLanguage\States\ChoiceState;
use AgentStateLanguage\States\DebateState;
use AgentStateLanguage\States\FailState;
use AgentStateLanguage\States\MapState;
use AgentStateLanguage\States\ParallelState;
use AgentStateLanguage\States\PassState;
use AgentStateLanguage\States\StateFactory;
use AgentStateLanguage\States\SucceedState;
use AgentStateLanguage\States\TaskState;
use AgentStateLanguage\States\WaitState;
use AgentStateLanguage\Tests\TestCase;

class StateFactoryTest extends TestCase
{
    private StateFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new StateFactory(new AgentRegistry());
    }

    public function testCreatePassState(): void
    {
        $state = $this->factory->create('Test', ['Type' => 'Pass', 'End' => true]);
        $this->assertInstanceOf(PassState::class, $state);
        $this->assertEquals('Test', $state->getName());
    }

    public function testCreateTaskState(): void
    {
        $state = $this->factory->create('Test', ['Type' => 'Task', 'Agent' => 'TestAgent', 'End' => true]);
        $this->assertInstanceOf(TaskState::class, $state);
    }

    public function testCreateChoiceState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Choice',
            'Choices' => [],
            'Default' => 'DefaultState'
        ]);
        $this->assertInstanceOf(ChoiceState::class, $state);
    }

    public function testCreateWaitState(): void
    {
        $state = $this->factory->create('Test', ['Type' => 'Wait', 'Seconds' => 10, 'Next' => 'Next']);
        $this->assertInstanceOf(WaitState::class, $state);
    }

    public function testCreateSucceedState(): void
    {
        $state = $this->factory->create('Test', ['Type' => 'Succeed']);
        $this->assertInstanceOf(SucceedState::class, $state);
    }

    public function testCreateFailState(): void
    {
        $state = $this->factory->create('Test', ['Type' => 'Fail', 'Error' => 'TestError']);
        $this->assertInstanceOf(FailState::class, $state);
    }

    public function testCreateMapState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => ['StartAt' => 'Process', 'States' => []],
            'End' => true
        ]);
        $this->assertInstanceOf(MapState::class, $state);
    }

    public function testCreateParallelState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Parallel',
            'Branches' => [],
            'End' => true
        ]);
        $this->assertInstanceOf(ParallelState::class, $state);
    }

    public function testCreateApprovalState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Next' => 'Next'
        ]);
        $this->assertInstanceOf(ApprovalState::class, $state);
    }

    public function testCreateDebateState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Debate',
            'Agents' => ['Agent1', 'Agent2'],
            'End' => true
        ]);
        $this->assertInstanceOf(DebateState::class, $state);
    }

    public function testCreateCheckpointState(): void
    {
        $state = $this->factory->create('Test', [
            'Type' => 'Checkpoint',
            'Next' => 'Next'
        ]);
        $this->assertInstanceOf(CheckpointState::class, $state);
    }

    public function testCreateUnknownTypeThrows(): void
    {
        $this->expectException(ASLException::class);
        $this->factory->create('Test', ['Type' => 'Unknown']);
    }

    public function testCreateMissingTypeThrows(): void
    {
        $this->expectException(ASLException::class);
        $this->factory->create('Test', ['End' => true]);
    }

    public function testCreateAllStates(): void
    {
        $states = [
            'State1' => ['Type' => 'Pass', 'Next' => 'State2'],
            'State2' => ['Type' => 'Succeed']
        ];
        
        $result = $this->factory->createAll($states);
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('State1', $result);
        $this->assertArrayHasKey('State2', $result);
        $this->assertInstanceOf(PassState::class, $result['State1']);
        $this->assertInstanceOf(SucceedState::class, $result['State2']);
    }
}
