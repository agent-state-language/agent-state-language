<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\IntrinsicFunctions;
use PHPUnit\Framework\TestCase;

class IntrinsicFunctionsTest extends TestCase
{
    private IntrinsicFunctions $functions;

    protected function setUp(): void
    {
        $this->functions = new IntrinsicFunctions();
    }

    // String Functions

    public function testFormat(): void
    {
        $result = $this->functions->evaluate("States.Format('Hello, {}!', 'World')");
        $this->assertEquals('Hello, World!', $result);
    }

    public function testFormatWithMultiplePlaceholders(): void
    {
        $result = $this->functions->evaluate("States.Format('{} has {} apples', 'John', 5)");
        $this->assertEquals('John has 5 apples', $result);
    }

    public function testStringToJson(): void
    {
        $result = $this->functions->evaluate('States.StringToJson(\'{"name":"John"}\')');
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testJsonToString(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.JsonToString($.data)',
            ['data' => ['name' => 'John']]
        );
        $this->assertEquals('{"name":"John"}', $result);
    }

    public function testStringSplit(): void
    {
        $result = $this->functions->evaluate("States.StringSplit('a,b,c', ',')");
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    // Array Functions

    public function testArrayPartition(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayPartition($.items, 2)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertEquals([[1, 2], [3, 4], [5]], $result);
    }

    public function testArrayContains(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayContains($.items, 3)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertTrue($result);
    }

    public function testArrayContainsReturnsFalse(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayContains($.items, 10)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertFalse($result);
    }

    public function testArrayRange(): void
    {
        $result = $this->functions->evaluate('States.ArrayRange(1, 5)');
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function testArrayGetItem(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayGetItem($.items, 2)',
            ['items' => ['a', 'b', 'c', 'd']]
        );
        $this->assertEquals('c', $result);
    }

    public function testArrayLength(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayLength($.items)',
            ['items' => [1, 2, 3, 4, 5]]
        );
        $this->assertEquals(5, $result);
    }

    public function testArrayUnique(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayUnique($.items)',
            ['items' => [1, 2, 2, 3, 3, 3]]
        );
        $this->assertEquals([1, 2, 3], array_values($result));
    }

    // Math Functions

    public function testMathAdd(): void
    {
        $result = $this->functions->evaluate('States.MathAdd(5, 3)');
        $this->assertEquals(8, $result);
    }

    public function testMathSubtract(): void
    {
        $result = $this->functions->evaluate('States.MathSubtract(10, 4)');
        $this->assertEquals(6, $result);
    }

    public function testMathMultiply(): void
    {
        $result = $this->functions->evaluate('States.MathMultiply(4, 5)');
        $this->assertEquals(20, $result);
    }

    public function testMathDivide(): void
    {
        $result = $this->functions->evaluate('States.MathDivide(20, 4)');
        $this->assertEquals(5.0, $result);
    }

    public function testMathRandom(): void
    {
        $result = $this->functions->evaluate('States.MathRandom(1, 100)');
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    // Hash Functions

    public function testHash(): void
    {
        $result = $this->functions->evaluate("States.Hash('hello', 'sha256')");
        $expected = hash('sha256', 'hello');
        $this->assertEquals($expected, $result);
    }

    public function testHashMd5(): void
    {
        $result = $this->functions->evaluate("States.Hash('hello', 'md5')");
        $expected = md5('hello');
        $this->assertEquals($expected, $result);
    }

    // Encoding Functions

    public function testBase64Encode(): void
    {
        $result = $this->functions->evaluate("States.Base64Encode('hello')");
        $this->assertEquals(base64_encode('hello'), $result);
    }

    public function testBase64Decode(): void
    {
        $result = $this->functions->evaluate("States.Base64Decode('aGVsbG8=')");
        $this->assertEquals('hello', $result);
    }

    // UUID Function

    public function testUUID(): void
    {
        $result = $this->functions->evaluate('States.UUID()');
        
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    // JSON Merge

    public function testJsonMerge(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.JsonMerge($.a, $.b)',
            ['a' => ['name' => 'John'], 'b' => ['age' => 30]]
        );
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testJsonMergeOverwrites(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.JsonMerge($.a, $.b)',
            ['a' => ['name' => 'John', 'age' => 25], 'b' => ['age' => 30]]
        );
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    // Type Functions

    public function testIsString(): void
    {
        $this->assertTrue($this->functions->evaluate("States.IsString('hello')"));
        $this->assertFalse($this->functions->evaluate('States.IsString(123)'));
    }

    public function testIsNumber(): void
    {
        $this->assertTrue($this->functions->evaluate('States.IsNumber(123)'));
        $this->assertTrue($this->functions->evaluate('States.IsNumber(12.5)'));
        $this->assertFalse($this->functions->evaluate("States.IsNumber('hello')"));
    }

    public function testIsBoolean(): void
    {
        $this->assertTrue($this->functions->evaluate('States.IsBoolean(true)'));
        $this->assertTrue($this->functions->evaluate('States.IsBoolean(false)'));
        $this->assertFalse($this->functions->evaluate('States.IsBoolean(1)'));
    }

    public function testIsNull(): void
    {
        $this->assertTrue($this->functions->evaluate('States.IsNull(null)'));
        $this->assertFalse($this->functions->evaluate("States.IsNull('')"));
    }

    public function testIsArray(): void
    {
        $this->assertTrue($this->functions->evaluateWithContext(
            'States.IsArray($.items)',
            ['items' => [1, 2, 3]]
        ));
        $this->assertFalse($this->functions->evaluateWithContext(
            'States.IsArray($.items)',
            ['items' => ['key' => 'value']]
        ));
    }

    public function testIsObject(): void
    {
        $this->assertTrue($this->functions->evaluateWithContext(
            'States.IsObject($.data)',
            ['data' => ['key' => 'value']]
        ));
        $this->assertFalse($this->functions->evaluateWithContext(
            'States.IsObject($.data)',
            ['data' => [1, 2, 3]]
        ));
    }

    // Nested Function Calls

    public function testNestedFunctions(): void
    {
        $result = $this->functions->evaluate(
            "States.Format('Result: {}', States.MathAdd(5, 3))"
        );
        $this->assertEquals('Result: 8', $result);
    }

    public function testComplexNestedFunctions(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.ArrayLength(States.ArrayPartition($.items, 2))',
            ['items' => [1, 2, 3, 4, 5, 6]]
        );
        $this->assertEquals(3, $result);
    }

    // Context Variable Resolution

    public function testEvaluateWithJsonPath(): void
    {
        $result = $this->functions->evaluateWithContext(
            'States.MathAdd($.a, $.b)',
            ['a' => 10, 'b' => 20]
        );
        $this->assertEquals(30, $result);
    }

    // Error Handling

    public function testUnknownFunctionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->functions->evaluate('States.UnknownFunction()');
    }

    public function testInvalidSyntaxThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->functions->evaluate('States.Format(');
    }
}
