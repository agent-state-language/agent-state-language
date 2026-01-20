<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\JsonPath;
use PHPUnit\Framework\TestCase;

class JsonPathTest extends TestCase
{
    public function testEvaluateRootPath(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        
        $result = JsonPath::evaluate('$', $data);
        
        $this->assertEquals($data, $result);
    }

    public function testEvaluateSimpleProperty(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        
        $result = JsonPath::evaluate('$.name', $data);
        
        $this->assertEquals('John', $result);
    }

    public function testEvaluateNestedProperty(): void
    {
        $data = [
            'user' => [
                'profile' => [
                    'name' => 'John',
                    'email' => 'john@example.com'
                ]
            ]
        ];
        
        $result = JsonPath::evaluate('$.user.profile.name', $data);
        
        $this->assertEquals('John', $result);
    }

    public function testEvaluateArrayIndex(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'cherry']
        ];
        
        $result = JsonPath::evaluate('$.items[0]', $data);
        
        $this->assertEquals('apple', $result);
    }

    public function testEvaluateArrayWithNegativeIndex(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'cherry']
        ];
        
        $result = JsonPath::evaluate('$.items[-1]', $data);
        
        $this->assertEquals('cherry', $result);
    }

    public function testEvaluateNestedArrayProperty(): void
    {
        $data = [
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25]
            ]
        ];
        
        $result = JsonPath::evaluate('$.users[1].name', $data);
        
        $this->assertEquals('Jane', $result);
    }

    public function testEvaluateReturnsNullForMissingPath(): void
    {
        $data = ['name' => 'John'];
        
        $result = JsonPath::evaluate('$.missing', $data);
        
        $this->assertNull($result);
    }

    public function testSetSimpleProperty(): void
    {
        $data = ['name' => 'John'];
        
        $result = JsonPath::set('$.age', $data, 30);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testSetNestedProperty(): void
    {
        $data = ['user' => ['name' => 'John']];
        
        $result = JsonPath::set('$.user.age', $data, 30);
        
        $this->assertEquals(['user' => ['name' => 'John', 'age' => 30]], $result);
    }

    public function testSetCreatesNestedStructure(): void
    {
        $data = [];
        
        $result = JsonPath::set('$.user.profile.name', $data, 'John');
        
        $this->assertEquals(['user' => ['profile' => ['name' => 'John']]], $result);
    }

    public function testContextVariableAccess(): void
    {
        $data = ['input' => ['value' => 'test']];
        $context = [
            'State' => ['Name' => 'TaskState', 'EnteredTime' => '2024-01-01T00:00:00Z'],
            'Execution' => ['Id' => 'exec-123']
        ];
        
        $result = JsonPath::evaluate('$$.State.Name', $data, $context);
        
        $this->assertEquals('TaskState', $result);
    }

    public function testWildcardArrayAccess(): void
    {
        $data = [
            'users' => [
                ['name' => 'John'],
                ['name' => 'Jane'],
                ['name' => 'Bob']
            ]
        ];
        
        $result = JsonPath::evaluate('$.users[*]', $data);
        
        $this->assertEquals([
            ['name' => 'John'],
            ['name' => 'Jane'],
            ['name' => 'Bob']
        ], $result);
    }

    public function testResolveParameters(): void
    {
        $parameters = [
            'name.$' => '$.user.name',
            'static' => 'value',
            'age.$' => '$.user.age'
        ];
        $data = ['user' => ['name' => 'John', 'age' => 30]];
        
        $result = JsonPath::resolveParameters($parameters, $data);
        
        $this->assertEquals([
            'name' => 'John',
            'static' => 'value',
            'age' => 30
        ], $result);
    }

    public function testResolveParametersWithNestedObjects(): void
    {
        $parameters = [
            'user' => [
                'name.$' => '$.data.name',
                'email.$' => '$.data.email'
            ]
        ];
        $data = ['data' => ['name' => 'John', 'email' => 'john@example.com']];
        
        $result = JsonPath::resolveParameters($parameters, $data);
        
        $this->assertEquals([
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com'
            ]
        ], $result);
    }

    public function testResolveParametersWithArrays(): void
    {
        $parameters = [
            'items.$' => '$.list'
        ];
        $data = ['list' => ['a', 'b', 'c']];
        
        $result = JsonPath::resolveParameters($parameters, $data);
        
        $this->assertEquals([
            'items' => ['a', 'b', 'c']
        ], $result);
    }

    public function testMapItemAccess(): void
    {
        $data = ['item' => 'test'];
        $context = [
            'Map' => [
                'Item' => ['Index' => 2, 'Value' => ['name' => 'John']]
            ]
        ];
        
        $result = JsonPath::evaluate('$$.Map.Item.Index', $data, $context);
        
        $this->assertEquals(2, $result);
    }

    public function testNullResultPath(): void
    {
        $data = ['preserved' => 'data'];
        
        $result = JsonPath::set('null', $data, ['discarded' => 'result']);
        
        $this->assertEquals(['preserved' => 'data'], $result);
    }
}
