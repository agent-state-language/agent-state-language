<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Agents;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Exceptions\ASLException;
use PHPUnit\Framework\TestCase;

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
        $this->expectException(ASLException::class);
        
        $this->registry->get('UnknownAgent');
    }

    public function testHasAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        
        $this->assertFalse($this->registry->has('TestAgent'));
        
        $this->registry->register('TestAgent', $agent);
        
        $this->assertTrue($this->registry->has('TestAgent'));
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
}
