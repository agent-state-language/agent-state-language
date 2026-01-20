<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\CheckpointState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;

class CheckpointStateTest extends TestCase
{
    public function testExecuteCreatesCheckpoint(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'step1-complete',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([
            'progress' => ['step1' => 'done']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('NextState', $result->getNextState());
    }

    public function testCheckpointDataPreserved(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'mid-workflow',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([
            'data' => ['key' => 'value'],
            'state' => 'in_progress'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([
            'data' => ['key' => 'value'],
            'state' => 'in_progress'
        ], $result->getOutput());
    }

    public function testDynamicCheckpointId(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointIdPath' => '$.checkpointName',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([
            'checkpointName' => 'dynamic-checkpoint-123'
        ]);
        
        $checkpointId = $state->resolveCheckpointId($context);
        
        $this->assertEquals('dynamic-checkpoint-123', $checkpointId);
    }

    public function testExpirationConfiguration(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'recoverable',
            'Expiration' => '7d',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        
        $this->assertEquals('7d', $state->getExpiration());
    }

    public function testMetadataStorage(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'with-metadata',
            'Metadata' => [
                'version' => '1.0',
                'createdBy' => 'workflow-engine'
            ],
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        
        $metadata = $state->getMetadata();
        
        $this->assertEquals('1.0', $metadata['version']);
        $this->assertEquals('workflow-engine', $metadata['createdBy']);
    }

    public function testGetType(): void
    {
        $state = new CheckpointState('TestCheckpoint', [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'test',
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('Checkpoint', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new CheckpointState('MyCheckpoint', [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'test',
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('MyCheckpoint', $state->getName());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'final-checkpoint',
            'End' => true
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
    }

    public function testGetComment(): void
    {
        $state = new CheckpointState('TestCheckpoint', [
            'Type' => 'Checkpoint',
            'Comment' => 'Save progress for recovery',
            'CheckpointId' => 'test',
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('Save progress for recovery', $state->getComment());
    }

    public function testSelectiveDataCheckpoint(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'selective',
            'DataPath' => '$.important',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([
            'important' => ['critical' => 'data'],
            'temporary' => 'excluded'
        ]);
        
        $checkpointData = $state->getCheckpointData($context);
        
        $this->assertEquals(['critical' => 'data'], $checkpointData);
    }

    public function testRestorePointConfiguration(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'restore-point',
            'RestoreOnFailure' => true,
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        
        $this->assertTrue($state->getRestoreOnFailure());
    }

    public function testCheckpointResultMetadata(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'timestamped',
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext(['data' => 'value']);
        
        $result = $state->execute($context);
        
        $metadata = $result->getMetadata();
        
        $this->assertArrayHasKey('checkpointId', $metadata);
        $this->assertArrayHasKey('timestamp', $metadata);
    }

    public function testAutoGeneratedCheckpointId(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'Next' => 'NextState'
            // No CheckpointId specified
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        $context = new ExecutionContext([]);
        
        $checkpointId = $state->resolveCheckpointId($context);
        
        // Should auto-generate an ID
        $this->assertNotEmpty($checkpointId);
    }

    public function testCompressionConfiguration(): void
    {
        $definition = [
            'Type' => 'Checkpoint',
            'CheckpointId' => 'compressed',
            'Compress' => true,
            'Next' => 'NextState'
        ];
        
        $state = new CheckpointState('TestCheckpoint', $definition);
        
        $this->assertTrue($state->shouldCompress());
    }
}
