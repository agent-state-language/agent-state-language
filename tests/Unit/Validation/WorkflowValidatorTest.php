<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Validation;

use AgentStateLanguage\Validation\WorkflowValidator;
use AgentStateLanguage\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class WorkflowValidatorTest extends TestCase
{
    private WorkflowValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WorkflowValidator();
    }

    public function testValidateMinimalWorkflow(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'OnlyState',
            'States' => [
                'OnlyState' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateWithComment(): void
    {
        $workflow = [
            'Comment' => 'A simple workflow',
            'Version' => '1.0',
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateMissingStartAt(): void
    {
        $workflow = [
            'Version' => '1.0',
            'States' => [
                'State1' => ['Type' => 'Pass', 'End' => true]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertContains('StartAt is required', $result->getErrors());
    }

    public function testValidateMissingStates(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1'
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertContains('States is required', $result->getErrors());
    }

    public function testValidateStartAtReferencesNonexistentState(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'NonexistentState',
            'States' => [
                'ActualState' => ['Type' => 'Succeed']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('NonexistentState', implode(' ', $result->getErrors()));
    }

    public function testValidateInvalidStateType(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'InvalidType',
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('InvalidType', implode(' ', $result->getErrors()));
    }

    public function testValidateTaskStateMissingAgent(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'TaskState',
            'States' => [
                'TaskState' => [
                    'Type' => 'Task',
                    'End' => true
                    // Missing Agent
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Agent', implode(' ', $result->getErrors()));
    }

    public function testValidateChoiceStateMissingChoices(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ChoiceState',
            'States' => [
                'ChoiceState' => [
                    'Type' => 'Choice'
                    // Missing Choices
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Choices', implode(' ', $result->getErrors()));
    }

    public function testValidateNextReferencesNonexistentState(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'Pass',
                    'Next' => 'NonexistentState'
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('NonexistentState', implode(' ', $result->getErrors()));
    }

    public function testValidateMissingNextAndEnd(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'Pass'
                    // Missing both Next and End
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
    }

    public function testValidateUnreachableState(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'Pass',
                    'End' => true
                ],
                'UnreachableState' => [
                    'Type' => 'Pass',
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        // Should warn about unreachable state
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('UnreachableState', implode(' ', $result->getWarnings()));
    }

    public function testValidateMapStateWithIterator(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'MapState',
            'States' => [
                'MapState' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'Iterator' => [
                        'StartAt' => 'ProcessItem',
                        'States' => [
                            'ProcessItem' => [
                                'Type' => 'Pass',
                                'End' => true
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateParallelStateWithBranches(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ParallelState',
            'States' => [
                'ParallelState' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Branch1',
                            'States' => [
                                'Branch1' => ['Type' => 'Pass', 'End' => true]
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateBudgetConfiguration(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'Budget' => [
                'MaxCost' => '$5.00',
                'MaxTokens' => 10000
            ],
            'States' => [
                'State1' => ['Type' => 'Succeed']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateInvalidBudgetFormat(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'Budget' => [
                'MaxCost' => 'invalid' // Should be "$X.XX" format
            ],
            'States' => [
                'State1' => ['Type' => 'Succeed']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
    }

    public function testValidateRetryConfiguration(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'TaskState',
            'States' => [
                'TaskState' => [
                    'Type' => 'Task',
                    'Agent' => 'TestAgent',
                    'Retry' => [
                        [
                            'ErrorEquals' => ['TransientError'],
                            'MaxAttempts' => 3,
                            'IntervalSeconds' => 1,
                            'BackoffRate' => 2.0
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateCatchConfiguration(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'TaskState',
            'States' => [
                'TaskState' => [
                    'Type' => 'Task',
                    'Agent' => 'TestAgent',
                    'Catch' => [
                        [
                            'ErrorEquals' => ['States.ALL'],
                            'Next' => 'ErrorHandler'
                        ]
                    ],
                    'Next' => 'NextState'
                ],
                'NextState' => ['Type' => 'Succeed'],
                'ErrorHandler' => ['Type' => 'Fail', 'Error' => 'Handled', 'Cause' => 'Error was caught']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateApprovalState(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'ApprovalState',
            'States' => [
                'ApprovalState' => [
                    'Type' => 'Approval',
                    'Prompt' => ['Title' => 'Review Required'],
                    'Options' => ['approve', 'reject'],
                    'Timeout' => '24h',
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateDebateState(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'DebateState',
            'States' => [
                'DebateState' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'Agent1'],
                        ['Agent' => 'Agent2']
                    ],
                    'Topic' => 'Test topic',
                    'Rounds' => 3,
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateDebateStateMinimumParticipants(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'DebateState',
            'States' => [
                'DebateState' => [
                    'Type' => 'Debate',
                    'Participants' => [
                        ['Agent' => 'OnlyOneAgent'] // Need at least 2
                    ],
                    'Topic' => 'Test topic',
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        $this->assertFalse($result->isValid());
    }

    public function testValidateFromJson(): void
    {
        $json = json_encode([
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => ['Type' => 'Succeed']
            ]
        ]);
        
        $result = $this->validator->validateJson($json);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateInvalidJson(): void
    {
        $invalidJson = '{ invalid json }';
        
        $this->expectException(ValidationException::class);
        $this->validator->validateJson($invalidJson);
    }

    public function testValidateCircularReference(): void
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => 'State1',
            'States' => [
                'State1' => [
                    'Type' => 'Pass',
                    'Next' => 'State2'
                ],
                'State2' => [
                    'Type' => 'Pass',
                    'Next' => 'State1' // Circular reference
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        
        // Circular references should be detected but might just be warnings
        // depending on implementation (loops are valid in ASL)
        $this->assertTrue($result->hasWarnings() || $result->isValid());
    }
}
