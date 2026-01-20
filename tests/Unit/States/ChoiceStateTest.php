<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Exceptions\StateException;
use AgentStateLanguage\States\ChoiceState;
use AgentStateLanguage\Tests\TestCase;

class ChoiceStateTest extends TestCase
{
    private function createContext(): ExecutionContext
    {
        return new ExecutionContext('TestWorkflow');
    }

    public function testStringEquals(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.status', 'StringEquals' => 'approved', 'Next' => 'Approved']
            ],
            'Default' => 'Pending'
        ]);
        
        $result = $state->execute(['status' => 'approved'], $this->createContext());
        $this->assertEquals('Approved', $result->getNextState());
    }

    public function testNumericGreaterThan(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.score', 'NumericGreaterThan' => 50, 'Next' => 'High']
            ],
            'Default' => 'Low'
        ]);
        
        $result = $state->execute(['score' => 75], $this->createContext());
        $this->assertEquals('High', $result->getNextState());
        
        $result2 = $state->execute(['score' => 25], $this->createContext());
        $this->assertEquals('Low', $result2->getNextState());
    }

    public function testNumericEquals(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.count', 'NumericEquals' => 10, 'Next' => 'Exact']
            ],
            'Default' => 'Other'
        ]);
        
        $result = $state->execute(['count' => 10], $this->createContext());
        $this->assertEquals('Exact', $result->getNextState());
    }

    public function testNumericLessThan(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.value', 'NumericLessThan' => 100, 'Next' => 'Small']
            ],
            'Default' => 'Large'
        ]);
        
        $result = $state->execute(['value' => 50], $this->createContext());
        $this->assertEquals('Small', $result->getNextState());
    }

    public function testBooleanEquals(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.active', 'BooleanEquals' => true, 'Next' => 'Active']
            ],
            'Default' => 'Inactive'
        ]);
        
        $result = $state->execute(['active' => true], $this->createContext());
        $this->assertEquals('Active', $result->getNextState());
        
        $result2 = $state->execute(['active' => false], $this->createContext());
        $this->assertEquals('Inactive', $result2->getNextState());
    }

    public function testIsNull(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.value', 'IsNull' => true, 'Next' => 'NullPath']
            ],
            'Default' => 'HasValue'
        ]);
        
        $result = $state->execute(['value' => null], $this->createContext());
        $this->assertEquals('NullPath', $result->getNextState());
        
        $result2 = $state->execute(['value' => 'something'], $this->createContext());
        $this->assertEquals('HasValue', $result2->getNextState());
    }

    public function testIsPresent(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.optional', 'IsPresent' => true, 'Next' => 'Present']
            ],
            'Default' => 'Missing'
        ]);
        
        $result = $state->execute(['optional' => 'value'], $this->createContext());
        $this->assertEquals('Present', $result->getNextState());
        
        $result2 = $state->execute(['other' => 'value'], $this->createContext());
        $this->assertEquals('Missing', $result2->getNextState());
    }

    public function testAndCondition(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'And' => [
                        ['Variable' => '$.score', 'NumericGreaterThanEquals' => 50],
                        ['Variable' => '$.score', 'NumericLessThan' => 80]
                    ],
                    'Next' => 'Medium'
                ]
            ],
            'Default' => 'Other'
        ]);
        
        $result = $state->execute(['score' => 65], $this->createContext());
        $this->assertEquals('Medium', $result->getNextState());
        
        $result2 = $state->execute(['score' => 90], $this->createContext());
        $this->assertEquals('Other', $result2->getNextState());
    }

    public function testOrCondition(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Or' => [
                        ['Variable' => '$.status', 'StringEquals' => 'approved'],
                        ['Variable' => '$.status', 'StringEquals' => 'auto-approved']
                    ],
                    'Next' => 'Approved'
                ]
            ],
            'Default' => 'NotApproved'
        ]);
        
        $result = $state->execute(['status' => 'approved'], $this->createContext());
        $this->assertEquals('Approved', $result->getNextState());
        
        $result2 = $state->execute(['status' => 'auto-approved'], $this->createContext());
        $this->assertEquals('Approved', $result2->getNextState());
        
        $result3 = $state->execute(['status' => 'pending'], $this->createContext());
        $this->assertEquals('NotApproved', $result3->getNextState());
    }

    public function testNotCondition(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Not' => ['Variable' => '$.banned', 'BooleanEquals' => true],
                    'Next' => 'Allowed'
                ]
            ],
            'Default' => 'Banned'
        ]);
        
        $result = $state->execute(['banned' => false], $this->createContext());
        $this->assertEquals('Allowed', $result->getNextState());
        
        $result2 = $state->execute(['banned' => true], $this->createContext());
        $this->assertEquals('Banned', $result2->getNextState());
    }

    public function testStringMatches(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.file', 'StringMatches' => '*.pdf', 'Next' => 'IsPdf']
            ],
            'Default' => 'OtherFile'
        ]);
        
        $result = $state->execute(['file' => 'document.pdf'], $this->createContext());
        $this->assertEquals('IsPdf', $result->getNextState());
        
        $result2 = $state->execute(['file' => 'image.jpg'], $this->createContext());
        $this->assertEquals('OtherFile', $result2->getNextState());
    }

    public function testDefaultPath(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.value', 'NumericEquals' => 999, 'Next' => 'Specific']
            ],
            'Default' => 'Fallback'
        ]);
        
        $result = $state->execute(['value' => 1], $this->createContext());
        $this->assertEquals('Fallback', $result->getNextState());
    }

    public function testNoMatchNoDefaultThrows(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.value', 'NumericEquals' => 999, 'Next' => 'Specific']
            ]
        ]);
        
        $this->expectException(StateException::class);
        $state->execute(['value' => 1], $this->createContext());
    }

    public function testEvaluationOrder(): void
    {
        // First matching choice wins
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [
                ['Variable' => '$.score', 'NumericGreaterThanEquals' => 90, 'Next' => 'Excellent'],
                ['Variable' => '$.score', 'NumericGreaterThanEquals' => 80, 'Next' => 'Great'],
                ['Variable' => '$.score', 'NumericGreaterThanEquals' => 70, 'Next' => 'Good']
            ],
            'Default' => 'NeedsWork'
        ]);
        
        $result = $state->execute(['score' => 95], $this->createContext());
        $this->assertEquals('Excellent', $result->getNextState());
    }

    public function testGetNext(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [],
            'Default' => 'Default'
        ]);
        
        // Choice states don't have a single Next
        $this->assertNull($state->getNext());
    }

    public function testIsEnd(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [],
            'Default' => 'Default'
        ]);
        
        $this->assertFalse($state->isEnd());
    }
}
