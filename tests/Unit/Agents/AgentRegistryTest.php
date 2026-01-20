<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Agents;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\AgentFactoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AgentRegistryTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testRegisterAndGetAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->registry->register('TestAgent', $agent);
        
        $retrieved = $this->registry->get('TestAgent');
        
        $this->assertSame($agent, $retrieved);
    }

    public function testGetUnregisteredAgentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UnknownAgent');
        
        $this->registry->get('UnknownAgent');
    }

    public function testHasAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->assertFalse($this->registry->has('TestAgent'));
        
        $this->registry->register('TestAgent', $agent);
        
        $this->assertTrue($this->registry->has('TestAgent'));
    }

    public function testRegisterWithFactory(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $factory = $this->createMock(AgentFactoryInterface::class);
        
        $factory->expects($this->once())
            ->method('create')
            ->with('LazyAgent', [])
            ->willReturn($agent);
        
        $this->registry->registerFactory($factory);
        $this->registry->registerLazy('LazyAgent');
        
        // First access creates the agent
        $retrieved = $this->registry->get('LazyAgent');
        
        $this->assertSame($agent, $retrieved);
    }

    public function testRegisterWithConfiguration(): void
    {
        $factory = $this->createMock(AgentFactoryInterface::class);
        $agent = $this->createMock(AgentInterface::class);
        
        $config = ['model' => 'claude-3-opus', 'maxTokens' => 4000];
        
        $factory->expects($this->once())
            ->method('create')
            ->with('ConfiguredAgent', $config)
            ->willReturn($agent);
        
        $this->registry->registerFactory($factory);
        $this->registry->registerLazy('ConfiguredAgent', $config);
        
        $retrieved = $this->registry->get('ConfiguredAgent');
        
        $this->assertSame($agent, $retrieved);
    }

    public function testUnregister(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->registry->register('TestAgent', $agent);
        $this->assertTrue($this->registry->has('TestAgent'));
        
        $this->registry->unregister('TestAgent');
        $this->assertFalse($this->registry->has('TestAgent'));
    }

    public function testListRegisteredAgents(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent2 = $this->createMock(AgentInterface::class);
        
        $this->registry->register('Agent1', $agent1);
        $this->registry->register('Agent2', $agent2);
        
        $agents = $this->registry->list();
        
        $this->assertCount(2, $agents);
        $this->assertContains('Agent1', $agents);
        $this->assertContains('Agent2', $agents);
    }

    public function testRegisterOverwritesPrevious(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent2 = $this->createMock(AgentInterface::class);
        
        $this->registry->register('TestAgent', $agent1);
        $this->registry->register('TestAgent', $agent2);
        
        $retrieved = $this->registry->get('TestAgent');
        
        $this->assertSame($agent2, $retrieved);
    }

    public function testRegisterCallable(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $callCount = 0;
        
        $this->registry->registerCallable('LazyAgent', function () use ($agent, &$callCount) {
            $callCount++;
            return $agent;
        });
        
        // Callable not invoked yet
        $this->assertEquals(0, $callCount);
        
        // First access invokes callable
        $retrieved = $this->registry->get('LazyAgent');
        $this->assertEquals(1, $callCount);
        $this->assertSame($agent, $retrieved);
        
        // Second access uses cached instance
        $this->registry->get('LazyAgent');
        $this->assertEquals(1, $callCount); // Still 1
    }

    public function testRegisterMultiple(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent2 = $this->createMock(AgentInterface::class);
        
        $this->registry->registerMultiple([
            'Agent1' => $agent1,
            'Agent2' => $agent2
        ]);
        
        $this->assertTrue($this->registry->has('Agent1'));
        $this->assertTrue($this->registry->has('Agent2'));
    }

    public function testClearRegistry(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->registry->register('Agent1', $agent);
        $this->registry->register('Agent2', $agent);
        
        $this->registry->clear();
        
        $this->assertEmpty($this->registry->list());
    }

    public function testGetWithDefault(): void
    {
        $defaultAgent = $this->createMock(AgentInterface::class);
        
        $retrieved = $this->registry->getOrDefault('NonExistent', $defaultAgent);
        
        $this->assertSame($defaultAgent, $retrieved);
    }

    public function testAliasAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->registry->register('OriginalName', $agent);
        $this->registry->alias('AliasName', 'OriginalName');
        
        $retrieved = $this->registry->get('AliasName');
        
        $this->assertSame($agent, $retrieved);
    }

    public function testCloneRegistry(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $this->registry->register('Agent', $agent);
        
        $cloned = $this->registry->clone();
        
        // Both should have the agent
        $this->assertTrue($cloned->has('Agent'));
        
        // Modifying clone shouldn't affect original
        $cloned->unregister('Agent');
        $this->assertTrue($this->registry->has('Agent'));
        $this->assertFalse($cloned->has('Agent'));
    }
}
