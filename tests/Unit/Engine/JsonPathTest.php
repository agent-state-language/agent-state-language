<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\JsonPath;
use PHPUnit\Framework\TestCase;

class JsonPathTest extends TestCase
{
    private JsonPath $jsonPath;

    protected function setUp(): void
    {
        $this->jsonPath = new JsonPath();
    }

    public function testGetRootPath(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        
        $result = $this->jsonPath->get('$', $data);
        
        $this->assertEquals($data, $result);
    }

    public function testGetSimpleProperty(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        
        $result = $this->jsonPath->get('$.name', $data);
        
        $this->assertEquals('John', $result);
    }

    public function testGetNestedProperty(): void
    {
        $data = [
            'user' => [
                'profile' => [
                    'name' => 'John',
                    'email' => 'john@example.com'
                ]
            ]
        ];
        
        $result = $this->jsonPath->get('$.user.profile.name', $data);
        
        $this->assertEquals('John', $result);
    }

    public function testGetArrayIndex(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'cherry']
        ];
        
        $result = $this->jsonPath->get('$.items[0]', $data);
        
        $this->assertEquals('apple', $result);
    }

    public function testGetArrayWithNegativeIndex(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'cherry']
        ];
        
        $result = $this->jsonPath->get('$.items[-1]', $data);
        
        $this->assertEquals('cherry', $result);
    }

    public function testGetNestedArrayProperty(): void
    {
        $data = [
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25]
            ]
        ];
        
        $result = $this->jsonPath->get('$.users[1].name', $data);
        
        $this->assertEquals('Jane', $result);
    }

    public function testGetReturnsNullForMissingPath(): void
    {
        $data = ['name' => 'John'];
        
        $result = $this->jsonPath->get('$.missing', $data);
        
        $this->assertNull($result);
    }

    public function testGetWithDefaultValue(): void
    {
        $data = ['name' => 'John'];
        
        $result = $this->jsonPath->get('$.missing', $data, 'default');
        
        $this->assertEquals('default', $result);
    }

    public function testSetSimpleProperty(): void
    {
        $data = ['name' => 'John'];
        
        $result = $this->jsonPath->set('$.age', $data, 30);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testSetNestedProperty(): void
    {
        $data = ['user' => ['name' => 'John']];
        
        $result = $this->jsonPath->set('$.user.age', $data, 30);
        
        $this->assertEquals(['user' => ['name' => 'John', 'age' => 30]], $result);
    }

    public function testSetCreatesNestedStructure(): void
    {
        $data = [];
        
        $result = $this->jsonPath->set('$.user.profile.name', $data, 'John');
        
        $this->assertEquals(['user' => ['profile' => ['name' => 'John']]], $result);
    }

    public function testSetArrayIndex(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        
        $result = $this->jsonPath->set('$.items[1]', $data, 'x');
        
        $this->assertEquals(['items' => ['a', 'x', 'c']], $result);
    }

    public function testDeleteProperty(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        
        $result = $this->jsonPath->delete('$.age', $data);
        
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testDeleteNestedProperty(): void
    {
        $data = ['user' => ['name' => 'John', 'age' => 30]];
        
        $result = $this->jsonPath->delete('$.user.age', $data);
        
        $this->assertEquals(['user' => ['name' => 'John']], $result);
    }

    public function testExistsReturnsTrue(): void
    {
        $data = ['name' => 'John'];
        
        $result = $this->jsonPath->exists('$.name', $data);
        
        $this->assertTrue($result);
    }

    public function testExistsReturnsFalse(): void
    {
        $data = ['name' => 'John'];
        
        $result = $this->jsonPath->exists('$.missing', $data);
        
        $this->assertFalse($result);
    }

    public function testExistsReturnsTrueForNullValue(): void
    {
        $data = ['name' => null];
        
        $result = $this->jsonPath->exists('$.name', $data);
        
        $this->assertTrue($result);
    }

    public function testResolveParameters(): void
    {
        $parameters = [
            'name.$' => '$.user.name',
            'static' => 'value',
            'age.$' => '$.user.age'
        ];
        $data = ['user' => ['name' => 'John', 'age' => 30]];
        
        $result = $this->jsonPath->resolveParameters($parameters, $data);
        
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
        
        $result = $this->jsonPath->resolveParameters($parameters, $data);
        
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
        
        $result = $this->jsonPath->resolveParameters($parameters, $data);
        
        $this->assertEquals([
            'items' => ['a', 'b', 'c']
        ], $result);
    }

    public function testContextVariableAccess(): void
    {
        $data = ['input' => ['value' => 'test']];
        $context = [
            'State' => ['Name' => 'TaskState', 'EnteredTime' => '2024-01-01T00:00:00Z'],
            'Execution' => ['Id' => 'exec-123']
        ];
        
        $result = $this->jsonPath->get('$$.State.Name', $data, null, $context);
        
        $this->assertEquals('TaskState', $result);
    }

    public function testMapItemAccess(): void
    {
        $data = ['item' => 'test'];
        $context = [
            'Map' => [
                'Item' => ['Index' => 2, 'Value' => ['name' => 'John']]
            ]
        ];
        
        $result = $this->jsonPath->get('$$.Map.Item.Index', $data, null, $context);
        
        $this->assertEquals(2, $result);
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
        
        $result = $this->jsonPath->get('$.users[*].name', $data);
        
        $this->assertEquals(['John', 'Jane', 'Bob'], $result);
    }
}
