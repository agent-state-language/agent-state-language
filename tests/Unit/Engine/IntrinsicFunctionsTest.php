<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\IntrinsicFunctions;
use PHPUnit\Framework\TestCase;

class IntrinsicFunctionsTest extends TestCase
{
    public function testFormat(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Format('Hello, {}!', 'World')", []);
        $this->assertEquals('Hello, World!', $result);
    }

    public function testFormatWithMultiplePlaceholders(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Format('{} has {} apples', 'John', 5)", []);
        $this->assertEquals('John has 5 apples', $result);
    }

    public function testStringToJson(): void
    {
        $result = IntrinsicFunctions::evaluate('States.StringToJson(\'{"name":"John"}\')', []);
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testJsonToString(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.JsonToString($.data)',
            ['data' => ['name' => 'John']]
        );
        $this->assertEquals('{"name":"John"}', $result);
    }

    public function testArrayPartition(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayPartition($.items, 2)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertEquals([[1, 2], [3, 4], [5]], $result);
    }

    public function testArrayContains(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayContains($.items, 3)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertTrue($result);
    }

    public function testArrayContainsReturnsFalse(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayContains($.items, 10)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertFalse($result);
    }

    public function testArrayRange(): void
    {
        $result = IntrinsicFunctions::evaluate('States.ArrayRange(1, 6)', []);
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function testArrayGetItem(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayGetItem($.items, 2)',
            ['items' => ['a', 'b', 'c', 'd']]
        );
        $this->assertEquals('c', $result);
    }

    public function testArrayLength(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayLength($.items)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertEquals(5, $result);
    }

    public function testArrayUnique(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.ArrayUnique($.items)',
            ['items' => [1, 2, 2, 3, 3, 3]]
        );
        $this->assertEquals([1, 2, 3], array_values($result));
    }

    public function testMathAdd(): void
    {
        $result = IntrinsicFunctions::evaluate('States.MathAdd(5, 3)', []);
        $this->assertEquals(8, $result);
    }

    public function testMathAddWithJsonPath(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.MathAdd($.a, $.b)',
            ['a' => 10, 'b' => 20]
        );
        $this->assertEquals(30, $result);
    }

    public function testHash(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Hash('hello', 'sha256')", []);
        $expected = hash('sha256', 'hello');
        $this->assertEquals($expected, $result);
    }

    public function testHashMd5(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Hash('hello', 'md5')", []);
        $expected = md5('hello');
        $this->assertEquals($expected, $result);
    }

    public function testBase64Encode(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Base64Encode('hello')", []);
        $this->assertEquals(base64_encode('hello'), $result);
    }

    public function testBase64Decode(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Base64Decode('aGVsbG8=')", []);
        $this->assertEquals('hello', $result);
    }

    public function testUUID(): void
    {
        $result = IntrinsicFunctions::evaluate('States.UUID()', []);
        
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    public function testJsonMerge(): void
    {
        $result = IntrinsicFunctions::evaluate(
            'States.Merge($.a, $.b)',
            ['a' => ['name' => 'John'], 'b' => ['age' => 30]]
        );
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testNestedFunctions(): void
    {
        $result = IntrinsicFunctions::evaluate(
            "States.Format('Result: {}', States.MathAdd(5, 3))",
            []
        );
        $this->assertEquals('Result: 8', $result);
    }

    public function testUnknownFunctionThrowsException(): void
    {
        $this->expectException(\AgentStateLanguage\Exceptions\ASLException::class);
        IntrinsicFunctions::evaluate('States.UnknownFunction()', []);
    }

    public function testTokenCount(): void
    {
        $result = IntrinsicFunctions::evaluate("States.TokenCount('hello world')", []);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testTruncate(): void
    {
        $longText = str_repeat('a', 1000);
        $result = IntrinsicFunctions::evaluate(
            'States.Truncate($.text, 50)',
            ['text' => $longText]
        );
        $this->assertLessThanOrEqual(203, strlen($result)); // 50*4 + 3 for "..."
    }

    public function testPick(): void
    {
        $result = IntrinsicFunctions::evaluate(
            "States.Pick($.obj, 'name', 'age')",
            ['obj' => ['name' => 'John', 'age' => 30, 'email' => 'john@example.com']]
        );
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testOmit(): void
    {
        $result = IntrinsicFunctions::evaluate(
            "States.Omit($.obj, 'password')",
            ['obj' => ['name' => 'John', 'password' => 'secret']]
        );
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testArray(): void
    {
        $result = IntrinsicFunctions::evaluate("States.Array(1, 2, 3)", []);
        $this->assertEquals([1, 2, 3], $result);
    }
}
