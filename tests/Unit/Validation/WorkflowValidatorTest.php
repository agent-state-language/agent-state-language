<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Validation;

use AgentStateLanguage\Exceptions\ValidationException;
use AgentStateLanguage\Validation\WorkflowValidator;
use AgentStateLanguage\Tests\TestCase;

class WorkflowValidatorTest extends TestCase
{
    private WorkflowValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WorkflowValidator();
    }

    public function testValidMinimalWorkflow(): void
    {
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testValidWorkflowWithMultipleStates(): void
    {
        $workflow = [
            'StartAt' => 'Step1',
            'States' => [
                'Step1' => [
                    'Type' => 'Pass',
                    'Next' => 'Step2'
                ],
                'Step2' => [
                    'Type' => 'Task',
                    'Agent' => 'TestAgent',
                    'Next' => 'Step3'
                ],
                'Step3' => [
                    'Type' => 'Succeed'
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testMissingStartAt(): void
    {
        $workflow = [
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testMissingStates(): void
    {
        $workflow = [
            'StartAt' => 'Start'
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testStartAtReferencesNonExistentState(): void
    {
        $workflow = [
            'StartAt' => 'NonExistent',
            'States' => [
                'Start' => ['Type' => 'Succeed']
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testStateMissingType(): void
    {
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'End' => true
                ]
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testTaskStateMissingAgent(): void
    {
        $workflow = [
            'StartAt' => 'Task',
            'States' => [
                'Task' => [
                    'Type' => 'Task',
                    'End' => true
                ]
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testChoiceStateMissingChoices(): void
    {
        $workflow = [
            'StartAt' => 'Choice',
            'States' => [
                'Choice' => [
                    'Type' => 'Choice'
                ]
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testNextReferencesNonExistentState(): void
    {
        $workflow = [
            'StartAt' => 'Start',
            'States' => [
                'Start' => [
                    'Type' => 'Pass',
                    'Next' => 'NonExistent'
                ]
            ]
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }

    public function testValidChoiceState(): void
    {
        $workflow = [
            'StartAt' => 'Route',
            'States' => [
                'Route' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        [
                            'Variable' => '$.status',
                            'StringEquals' => 'approved',
                            'Next' => 'Approved'
                        ]
                    ],
                    'Default' => 'Pending'
                ],
                'Approved' => ['Type' => 'Succeed'],
                'Pending' => ['Type' => 'Succeed']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testValidParallelState(): void
    {
        $workflow = [
            'StartAt' => 'Parallel',
            'States' => [
                'Parallel' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        [
                            'StartAt' => 'Branch1',
                            'States' => [
                                'Branch1' => ['Type' => 'Succeed']
                            ]
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testValidMapState(): void
    {
        $workflow = [
            'StartAt' => 'Map',
            'States' => [
                'Map' => [
                    'Type' => 'Map',
                    'ItemsPath' => '$.items',
                    'Iterator' => [
                        'StartAt' => 'Process',
                        'States' => [
                            'Process' => ['Type' => 'Succeed']
                        ]
                    ],
                    'End' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testValidFailState(): void
    {
        $workflow = [
            'StartAt' => 'Fail',
            'States' => [
                'Fail' => [
                    'Type' => 'Fail',
                    'Error' => 'CustomError',
                    'Cause' => 'Something went wrong'
                ]
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testValidWaitState(): void
    {
        $workflow = [
            'StartAt' => 'Wait',
            'States' => [
                'Wait' => [
                    'Type' => 'Wait',
                    'Seconds' => 10,
                    'Next' => 'Done'
                ],
                'Done' => ['Type' => 'Succeed']
            ]
        ];
        
        $result = $this->validator->validate($workflow);
        $this->assertTrue($result);
    }

    public function testGetErrors(): void
    {
        $validator = new WorkflowValidator();
        
        try {
            $validator->validate([
                'StartAt' => 'NonExistent',
                'States' => [
                    'Start' => ['End' => true]
                ]
            ]);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
        }
    }

    public function testEmptyStates(): void
    {
        $workflow = [
            'StartAt' => 'Start',
            'States' => []
        ];
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($workflow);
    }
}
