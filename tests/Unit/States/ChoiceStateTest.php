<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\ChoiceState;
use AgentStateLanguage\States\StateResult;
use AgentStateLanguage\Exceptions\StateException;
use PHPUnit\Framework\TestCase;

class ChoiceStateTest extends TestCase
{
    public function testStringEqualsMatch(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.status',
                    'StringEquals' => 'active',
                    'Next' => 'ActiveState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['status' => 'active']);
        
        $result = $state->execute($context);
        
        $this->assertEquals('ActiveState', $result->getNextState());
    }

    public function testStringEqualsNoMatch(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.status',
                    'StringEquals' => 'active',
                    'Next' => 'ActiveState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['status' => 'inactive']);
        
        $result = $state->execute($context);
        
        $this->assertEquals('DefaultState', $result->getNextState());
    }

    public function testNumericEquals(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.count',
                    'NumericEquals' => 10,
                    'Next' => 'TenState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['count' => 10]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('TenState', $result->getNextState());
    }

    public function testNumericGreaterThan(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.amount',
                    'NumericGreaterThan' => 100,
                    'Next' => 'HighAmount'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        $context = new ExecutionContext(['amount' => 150]);
        $result = $state->execute($context);
        $this->assertEquals('HighAmount', $result->getNextState());
        
        $context = new ExecutionContext(['amount' => 50]);
        $result = $state->execute($context);
        $this->assertEquals('DefaultState', $result->getNextState());
    }

    public function testNumericLessThan(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.score',
                    'NumericLessThan' => 60,
                    'Next' => 'FailState'
                ]
            ],
            'Default' => 'PassState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['score' => 45]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('FailState', $result->getNextState());
    }

    public function testNumericGreaterThanEquals(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.value',
                    'NumericGreaterThanEquals' => 100,
                    'Next' => 'MatchState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        // Equal value should match
        $context = new ExecutionContext(['value' => 100]);
        $result = $state->execute($context);
        $this->assertEquals('MatchState', $result->getNextState());
        
        // Greater value should match
        $context = new ExecutionContext(['value' => 150]);
        $result = $state->execute($context);
        $this->assertEquals('MatchState', $result->getNextState());
    }

    public function testBooleanEquals(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.isActive',
                    'BooleanEquals' => true,
                    'Next' => 'ActiveState'
                ]
            ],
            'Default' => 'InactiveState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['isActive' => true]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('ActiveState', $result->getNextState());
    }

    public function testIsPresent(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.optional',
                    'IsPresent' => true,
                    'Next' => 'HasOptional'
                ]
            ],
            'Default' => 'NoOptional'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        $context = new ExecutionContext(['optional' => 'value']);
        $result = $state->execute($context);
        $this->assertEquals('HasOptional', $result->getNextState());
        
        $context = new ExecutionContext(['other' => 'value']);
        $result = $state->execute($context);
        $this->assertEquals('NoOptional', $result->getNextState());
    }

    public function testIsNull(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.value',
                    'IsNull' => true,
                    'Next' => 'NullState'
                ]
            ],
            'Default' => 'NotNullState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['value' => null]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('NullState', $result->getNextState());
    }

    public function testStringMatches(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.email',
                    'StringMatches' => '*@example.com',
                    'Next' => 'ExampleEmail'
                ]
            ],
            'Default' => 'OtherEmail'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['email' => 'user@example.com']);
        
        $result = $state->execute($context);
        
        $this->assertEquals('ExampleEmail', $result->getNextState());
    }

    public function testAndCondition(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'And' => [
                        [
                            'Variable' => '$.age',
                            'NumericGreaterThanEquals' => 18
                        ],
                        [
                            'Variable' => '$.verified',
                            'BooleanEquals' => true
                        ]
                    ],
                    'Next' => 'AllowedState'
                ]
            ],
            'Default' => 'DeniedState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        // Both conditions true
        $context = new ExecutionContext(['age' => 25, 'verified' => true]);
        $result = $state->execute($context);
        $this->assertEquals('AllowedState', $result->getNextState());
        
        // One condition false
        $context = new ExecutionContext(['age' => 25, 'verified' => false]);
        $result = $state->execute($context);
        $this->assertEquals('DeniedState', $result->getNextState());
    }

    public function testOrCondition(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Or' => [
                        [
                            'Variable' => '$.role',
                            'StringEquals' => 'admin'
                        ],
                        [
                            'Variable' => '$.role',
                            'StringEquals' => 'superuser'
                        ]
                    ],
                    'Next' => 'AdminState'
                ]
            ],
            'Default' => 'UserState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        // First condition true
        $context = new ExecutionContext(['role' => 'admin']);
        $result = $state->execute($context);
        $this->assertEquals('AdminState', $result->getNextState());
        
        // Second condition true
        $context = new ExecutionContext(['role' => 'superuser']);
        $result = $state->execute($context);
        $this->assertEquals('AdminState', $result->getNextState());
        
        // Neither condition true
        $context = new ExecutionContext(['role' => 'user']);
        $result = $state->execute($context);
        $this->assertEquals('UserState', $result->getNextState());
    }

    public function testNotCondition(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Not' => [
                        'Variable' => '$.status',
                        'StringEquals' => 'blocked'
                    ],
                    'Next' => 'AllowedState'
                ]
            ],
            'Default' => 'BlockedState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        // Not blocked
        $context = new ExecutionContext(['status' => 'active']);
        $result = $state->execute($context);
        $this->assertEquals('AllowedState', $result->getNextState());
        
        // Blocked
        $context = new ExecutionContext(['status' => 'blocked']);
        $result = $state->execute($context);
        $this->assertEquals('BlockedState', $result->getNextState());
    }

    public function testMultipleChoices(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.type',
                    'StringEquals' => 'A',
                    'Next' => 'TypeAState'
                ],
                [
                    'Variable' => '$.type',
                    'StringEquals' => 'B',
                    'Next' => 'TypeBState'
                ],
                [
                    'Variable' => '$.type',
                    'StringEquals' => 'C',
                    'Next' => 'TypeCState'
                ]
            ],
            'Default' => 'UnknownTypeState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        
        $context = new ExecutionContext(['type' => 'B']);
        $result = $state->execute($context);
        
        $this->assertEquals('TypeBState', $result->getNextState());
    }

    public function testFirstMatchWins(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.value',
                    'NumericGreaterThan' => 50,
                    'Next' => 'FirstMatch'
                ],
                [
                    'Variable' => '$.value',
                    'NumericGreaterThan' => 30,
                    'Next' => 'SecondMatch'
                ]
            ],
            'Default' => 'NoMatch'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['value' => 75]);
        
        $result = $state->execute($context);
        
        // Both conditions are true, but first match wins
        $this->assertEquals('FirstMatch', $result->getNextState());
    }

    public function testNoDefaultThrowsException(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.status',
                    'StringEquals' => 'active',
                    'Next' => 'ActiveState'
                ]
            ]
            // No Default specified
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext(['status' => 'unknown']);
        
        $this->expectException(StateException::class);
        $state->execute($context);
    }

    public function testNestedJsonPath(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.user.profile.level',
                    'NumericGreaterThanEquals' => 10,
                    'Next' => 'VeteranUser'
                ]
            ],
            'Default' => 'NewUser'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext([
            'user' => ['profile' => ['level' => 15]]
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('VeteranUser', $result->getNextState());
    }

    public function testOutputPassthroughData(): void
    {
        $definition = [
            'Type' => 'Choice',
            'Choices' => [
                [
                    'Variable' => '$.action',
                    'StringEquals' => 'process',
                    'Next' => 'ProcessState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext([
            'action' => 'process',
            'data' => ['value' => 'preserved']
        ]);
        
        $result = $state->execute($context);
        
        // Choice state should pass through all data unchanged
        $this->assertEquals([
            'action' => 'process',
            'data' => ['value' => 'preserved']
        ], $result->getOutput());
    }

    public function testInputPath(): void
    {
        $definition = [
            'Type' => 'Choice',
            'InputPath' => '$.nested',
            'Choices' => [
                [
                    'Variable' => '$.status',
                    'StringEquals' => 'active',
                    'Next' => 'ActiveState'
                ]
            ],
            'Default' => 'DefaultState'
        ];
        
        $state = new ChoiceState('TestChoice', $definition);
        $context = new ExecutionContext([
            'nested' => ['status' => 'active']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('ActiveState', $result->getNextState());
    }

    public function testGetType(): void
    {
        $state = new ChoiceState('TestChoice', [
            'Type' => 'Choice',
            'Choices' => [],
            'Default' => 'Default'
        ]);
        
        $this->assertEquals('Choice', $state->getType());
    }
}
