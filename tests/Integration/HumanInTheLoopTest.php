<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\WorkflowEngine;
use PHPUnit\Framework\TestCase;

class HumanInTheLoopTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AgentRegistry();
    }

    public function testApprovalStateWithApprove(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Prepare',
            'States' => [
                'Prepare' => [
                    'Type' => 'Pass',
                    'Result' => ['content' => 'Needs approval'],
                    'Next' => 'RequestApproval'
                ],
                'RequestApproval' => [
                    'Type' => 'Approval',
                    'Prompt' => [
                        'Title' => 'Review Required',
                        'Content.$' => '$.content'
                    ],
                    'Options' => ['approve', 'reject'],
                    'ResultPath' => '$.approval',
                    'Choices' => [
                        [
                            'Variable' => '$.approval.approval',
                            'StringEquals' => 'approve',
                            'Next' => 'Approved'
                        ]
                    ],
                    'Default' => 'Rejected'
                ],
                'Approved' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'approved'],
                    'End' => true
                ],
                'Rejected' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'rejected'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        // Simulate approval response
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) {
            return [
                'approval' => 'approve',
                'approver' => 'test@example.com',
                'timestamp' => date('c')
            ];
        });
        
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['status' => 'approved'], $result->getOutput());
    }

    public function testApprovalStateWithReject(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'RequestApproval',
            'States' => [
                'RequestApproval' => [
                    'Type' => 'Approval',
                    'Prompt' => ['Title' => 'Approval Needed'],
                    'Options' => ['approve', 'reject'],
                    'ResultPath' => '$.approval',
                    'Choices' => [
                        [
                            'Variable' => '$.approval.approval',
                            'StringEquals' => 'approve',
                            'Next' => 'Approved'
                        ]
                    ],
                    'Default' => 'Rejected'
                ],
                'Approved' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'approved'],
                    'End' => true
                ],
                'Rejected' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'rejected'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) {
            return ['approval' => 'reject'];
        });
        
        $result = $engine->run([]);
        
        $this->assertEquals(['status' => 'rejected'], $result->getOutput());
    }

    public function testApprovalWithEdit(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'CreateDraft',
            'States' => [
                'CreateDraft' => [
                    'Type' => 'Pass',
                    'Result' => ['draft' => 'Original content'],
                    'Next' => 'ReviewDraft'
                ],
                'ReviewDraft' => [
                    'Type' => 'Approval',
                    'Prompt' => [
                        'Title' => 'Review Draft',
                        'Content.$' => '$.draft'
                    ],
                    'Options' => ['approve', 'edit', 'reject'],
                    'Editable' => [
                        'Fields' => ['$.draft'],
                        'ResultPath' => '$.editedContent'
                    ],
                    'ResultPath' => '$.approval',
                    'Choices' => [
                        [
                            'Variable' => '$.approval.approval',
                            'StringEquals' => 'edit',
                            'Next' => 'UseEditedContent'
                        ]
                    ],
                    'Default' => 'Finalize'
                ],
                'UseEditedContent' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'draft.$' => '$.editedContent.draft'
                    ],
                    'End' => true
                ],
                'Finalize' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) {
            return [
                'approval' => 'edit',
                'edits' => ['draft' => 'Edited content']
            ];
        });
        
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
    }

    public function testApprovalWithComments(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'RequestFeedback',
            'States' => [
                'RequestFeedback' => [
                    'Type' => 'Approval',
                    'Prompt' => ['Title' => 'Provide Feedback'],
                    'Options' => ['submit'],
                    'AllowComment' => true,
                    'ResultPath' => '$.feedback',
                    'Next' => 'ProcessFeedback'
                ],
                'ProcessFeedback' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'receivedComment.$' => '$.feedback.comment'
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) {
            return [
                'approval' => 'submit',
                'comment' => 'This is my feedback'
            ];
        });
        
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('This is my feedback', $result->getOutput()['receivedComment']);
    }

    public function testMultipleApprovalStages(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ManagerApproval',
            'States' => [
                'ManagerApproval' => [
                    'Type' => 'Approval',
                    'Prompt' => ['Title' => 'Manager Approval'],
                    'Options' => ['approve', 'reject'],
                    'ResultPath' => '$.managerApproval',
                    'Choices' => [
                        [
                            'Variable' => '$.managerApproval.approval',
                            'StringEquals' => 'approve',
                            'Next' => 'DirectorApproval'
                        ]
                    ],
                    'Default' => 'Rejected'
                ],
                'DirectorApproval' => [
                    'Type' => 'Approval',
                    'Prompt' => ['Title' => 'Director Approval'],
                    'Options' => ['approve', 'reject'],
                    'ResultPath' => '$.directorApproval',
                    'Choices' => [
                        [
                            'Variable' => '$.directorApproval.approval',
                            'StringEquals' => 'approve',
                            'Next' => 'FullyApproved'
                        ]
                    ],
                    'Default' => 'Rejected'
                ],
                'FullyApproved' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'fully_approved'],
                    'End' => true
                ],
                'Rejected' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'rejected'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $approvals = [
            'ManagerApproval' => ['approval' => 'approve', 'approver' => 'manager@example.com'],
            'DirectorApproval' => ['approval' => 'approve', 'approver' => 'director@example.com']
        ];
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) use ($approvals) {
            return $approvals[$stateName] ?? ['approval' => 'reject'];
        });
        
        $result = $engine->run([]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['status' => 'fully_approved'], $result->getOutput());
    }

    public function testConditionalApproval(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'CheckAmount',
            'States' => [
                'CheckAmount' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.amount',
                            'NumericGreaterThan' => 1000,
                            'Next' => 'RequireApproval'
                        ]
                    ],
                    'Default' => 'AutoApprove'
                ],
                'RequireApproval' => [
                    'Type' => 'Approval',
                    'Prompt' => [
                        'Title' => 'High Value Approval',
                        'Amount.$' => '$.amount'
                    ],
                    'Options' => ['approve', 'reject'],
                    'ResultPath' => '$.approval',
                    'Next' => 'ProcessApproval'
                ],
                'ProcessApproval' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.approval.approval',
                            'StringEquals' => 'approve',
                            'Next' => 'Complete'
                        ]
                    ],
                    'Default' => 'Rejected'
                ],
                'AutoApprove' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'auto_approved'],
                    'End' => true
                ],
                'Complete' => [
                    'Type' => 'Pass',
                    'Result' => ['status' => 'manually_approved'],
                    'End' => true
                ],
                'Rejected' => [
                    'Type' => 'Fail',
                    'Error' => 'ApprovalRejected',
                    'Cause' => 'Request was rejected'
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) {
            return ['approval' => 'approve'];
        });
        
        // Low amount - auto approved
        $result = $engine->run(['amount' => 500]);
        $this->assertEquals(['status' => 'auto_approved'], $result->getOutput());
        
        // High amount - requires approval
        $result = $engine->run(['amount' => 2000]);
        $this->assertEquals(['status' => 'manually_approved'], $result->getOutput());
    }

    public function testApprovalPromptResolution(): void
    {
        $capturedPrompt = null;
        
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'Approval',
            'States' => [
                'Approval' => [
                    'Type' => 'Approval',
                    'Prompt' => [
                        'Title' => 'Review Request',
                        'RequestedBy.$' => '$.user.name',
                        'Amount.$' => '$.request.amount',
                        'Description.$' => '$.request.description'
                    ],
                    'Options' => ['approve'],
                    'End' => true
                ]
            ]
        ];
        
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->setApprovalHandler(function ($stateName, $prompt, $options) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return ['approval' => 'approve'];
        });
        
        $result = $engine->run([
            'user' => ['name' => 'John Doe'],
            'request' => [
                'amount' => 500,
                'description' => 'Equipment purchase'
            ]
        ]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('John Doe', $capturedPrompt['RequestedBy']);
        $this->assertEquals(500, $capturedPrompt['Amount']);
        $this->assertEquals('Equipment purchase', $capturedPrompt['Description']);
    }
}
