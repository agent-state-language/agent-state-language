<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\ApprovalState;
use AgentStateLanguage\States\StateResult;
use AgentStateLanguage\Exceptions\TimeoutException;
use PHPUnit\Framework\TestCase;

class ApprovalStateTest extends TestCase
{
    public function testGetPromptConfiguration(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => [
                'Title' => 'Review Required',
                'Description' => 'Please review this content',
                'Data.$' => '$.content'
            ],
            'Options' => ['approve', 'reject'],
            'Timeout' => '24h',
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $prompt = $state->getPrompt();
        
        $this->assertEquals('Review Required', $prompt['Title']);
        $this->assertEquals('Please review this content', $prompt['Description']);
    }

    public function testGetOptions(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve', 'reject', 'defer'],
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $this->assertEquals(['approve', 'reject', 'defer'], $state->getOptions());
    }

    public function testGetTimeout(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Timeout' => '48h',
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $this->assertEquals('48h', $state->getTimeout());
    }

    public function testExecuteCreatesApprovalRequest(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => [
                'Title' => 'Review Content',
                'Content.$' => '$.document'
            ],
            'Options' => ['approve', 'reject'],
            'Timeout' => '24h',
            'ResultPath' => '$.approval',
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $context = new ExecutionContext([
            'document' => 'Sample document content'
        ]);
        
        // Simulate providing approval response
        $state->setApprovalResponse([
            'approval' => 'approve',
            'approver' => 'admin@example.com',
            'comment' => 'Looks good',
            'timestamp' => '2024-01-01T00:00:00Z'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
    }

    public function testChoiceBasedRouting(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve', 'reject'],
            'Choices' => [
                [
                    'Variable' => '$.approval.approval',
                    'StringEquals' => 'approve',
                    'Next' => 'ApprovedState'
                ],
                [
                    'Variable' => '$.approval.approval',
                    'StringEquals' => 'reject',
                    'Next' => 'RejectedState'
                ]
            ],
            'Default' => 'DefaultState',
            'ResultPath' => '$.approval'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $context = new ExecutionContext([]);
        
        $state->setApprovalResponse(['approval' => 'approve']);
        $result = $state->execute($context);
        
        $this->assertEquals('ApprovedState', $result->getNextState());
    }

    public function testEscalationConfiguration(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Timeout' => '24h',
            'Escalation' => [
                'After' => '12h',
                'Notify' => ['manager@example.com', 'director@example.com']
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $escalation = $state->getEscalation();
        
        $this->assertEquals('12h', $escalation['After']);
        $this->assertContains('manager@example.com', $escalation['Notify']);
    }

    public function testEditableFields(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Edit Content'],
            'Options' => ['approve', 'edit'],
            'Editable' => [
                'Fields' => ['$.content.title', '$.content.body'],
                'ResultPath' => '$.editedContent'
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $editable = $state->getEditableConfig();
        
        $this->assertContains('$.content.title', $editable['Fields']);
        $this->assertEquals('$.editedContent', $editable['ResultPath']);
    }

    public function testGetType(): void
    {
        $state = new ApprovalState('Test', [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('Approval', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new ApprovalState('MyApproval', [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('MyApproval', $state->getName());
    }

    public function testResolvedPromptWithJsonPath(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => [
                'Title' => 'Review Request',
                'Amount.$' => '$.amount',
                'Requester.$' => '$.user.name'
            ],
            'Options' => ['approve', 'reject'],
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $context = new ExecutionContext([
            'amount' => 500,
            'user' => ['name' => 'John']
        ]);
        
        $resolvedPrompt = $state->resolvePrompt($context);
        
        $this->assertEquals('Review Request', $resolvedPrompt['Title']);
        $this->assertEquals(500, $resolvedPrompt['Amount']);
        $this->assertEquals('John', $resolvedPrompt['Requester']);
    }

    public function testPendingStatus(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Next' => 'NextState'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        // Without a response, state should be pending
        $this->assertTrue($state->isPending());
        
        $state->setApprovalResponse(['approval' => 'approve']);
        
        $this->assertFalse($state->isPending());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Final Approval'],
            'Options' => ['approve'],
            'End' => true
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $state->setApprovalResponse(['approval' => 'approve']);
        
        $context = new ExecutionContext([]);
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
    }

    public function testGetComment(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Comment' => 'Requires manager approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve'],
            'Next' => 'Next'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        
        $this->assertEquals('Requires manager approval', $state->getComment());
    }

    public function testApprovalMetadata(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve', 'reject'],
            'Next' => 'NextState',
            'ResultPath' => '$.approval'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $context = new ExecutionContext([]);
        
        $state->setApprovalResponse([
            'approval' => 'approve',
            'approver' => 'admin@example.com',
            'comment' => 'Approved with conditions',
            'timestamp' => '2024-01-15T10:30:00Z'
        ]);
        
        $result = $state->execute($context);
        
        $output = $result->getOutput();
        
        $this->assertEquals('approve', $output['approval']);
        $this->assertEquals('admin@example.com', $output['approver']);
    }

    public function testDefaultRoute(): void
    {
        $definition = [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Options' => ['approve', 'reject'],
            'Choices' => [
                [
                    'Variable' => '$.approval.approval',
                    'StringEquals' => 'approve',
                    'Next' => 'ApprovedState'
                ]
            ],
            'Default' => 'FallbackState',
            'ResultPath' => '$.approval'
        ];
        
        $state = new ApprovalState('TestApproval', $definition);
        $context = new ExecutionContext([]);
        
        $state->setApprovalResponse(['approval' => 'unknown']);
        $result = $state->execute($context);
        
        $this->assertEquals('FallbackState', $result->getNextState());
    }
}
