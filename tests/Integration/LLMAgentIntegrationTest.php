<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Integration;

use AgentStateLanguage\Agents\LLM\ClaudeAgent;
use AgentStateLanguage\Agents\LLM\LLMAgentFactory;
use AgentStateLanguage\Tests\TestCase;

/**
 * Integration tests for LLM agents using real API calls.
 * 
 * These tests require API keys to be set as environment variables:
 * - ANTHROPIC_API_KEY for Claude tests
 * - OPENAI_API_KEY for OpenAI tests (if available)
 * 
 * Run with: ANTHROPIC_API_KEY=your-key ./vendor/bin/phpunit tests/Integration/LLMAgentIntegrationTest.php --testdox
 * Or export the key first and run the tests.
 */
class LLMAgentIntegrationTest extends TestCase
{
    private static ?string $anthropicApiKey = null;
    private static ?string $openaiApiKey = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Load .env file manually if present
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    if (!getenv($key)) {
                        putenv("{$key}={$value}");
                    }
                }
            }
        }
        
        self::$anthropicApiKey = getenv('ANTHROPIC_API_KEY') ?: null;
        self::$openaiApiKey = getenv('OPENAI_API_KEY') ?: null;
    }

    // Claude Integration Tests

    public function testClaudeAgentSimplePrompt(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = new ClaudeAgent(
            name: 'TestAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'You are a helpful assistant. Be very brief.'
        );
        $agent->setMaxTokens(100);

        $result = $agent->execute(['prompt' => 'Say hello in exactly 3 words.']);

        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('_tokens', $result);
        $this->assertArrayHasKey('_cost', $result);
        $this->assertArrayHasKey('_usage', $result);
        $this->assertNotEmpty($result['response']);
        $this->assertGreaterThan(0, $result['_tokens']);
        $this->assertGreaterThan(0, $result['_cost']);
    }

    public function testClaudeAgentJsonResponse(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = new ClaudeAgent(
            name: 'JsonAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'You always respond with valid JSON only. No markdown, no explanation.'
        );
        $agent->setMaxTokens(100);

        $result = $agent->execute([
            'prompt' => 'Return a JSON object with keys "name" set to "Alice" and "age" set to 30.'
        ]);

        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('parsed', $result);
        
        // Check that parsed JSON contains expected keys
        if ($result['parsed'] !== null) {
            $this->assertArrayHasKey('name', $result['parsed']);
            $this->assertArrayHasKey('age', $result['parsed']);
            $this->assertEquals('Alice', $result['parsed']['name']);
            $this->assertEquals(30, $result['parsed']['age']);
        }
    }

    public function testClaudeAgentTokenTracking(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = new ClaudeAgent(
            name: 'TokenAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514'
        );
        $agent->setMaxTokens(50);

        $result = $agent->execute(['prompt' => 'Say "test" only.']);

        // Check token usage from result
        $this->assertGreaterThan(0, $result['_tokens']);
        $this->assertGreaterThan(0, $result['_usage']['input']);
        $this->assertGreaterThan(0, $result['_usage']['output']);
        
        // Check agent's last usage tracking
        $lastUsage = $agent->getLastTokenUsage();
        $this->assertEquals($result['_usage']['input'], $lastUsage['input']);
        $this->assertEquals($result['_usage']['output'], $lastUsage['output']);
        
        // Check cost is calculated
        $this->assertGreaterThan(0, $agent->getLastCost());
        $this->assertEquals($result['_cost'], $agent->getLastCost());
    }

    public function testClaudeAgentWithTemperature(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = new ClaudeAgent(
            name: 'TempAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514'
        );
        $agent->setTemperature(0.0)
              ->setMaxTokens(50);

        $result = $agent->execute(['prompt' => 'What is 2+2? Reply with just the number.']);

        $this->assertArrayHasKey('response', $result);
        $this->assertStringContainsString('4', $result['response']);
    }

    public function testClaudeAgentHaikuModel(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        // Test with the faster/cheaper Haiku model
        $agent = new ClaudeAgent(
            name: 'HaikuAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-3-5-haiku-latest',
            systemPrompt: 'Be very brief.'
        );
        $agent->setMaxTokens(50);

        $result = $agent->execute(['prompt' => 'What color is the sky? One word.']);

        $this->assertArrayHasKey('response', $result);
        $this->assertNotEmpty($result['response']);
        // API returns resolved model name (e.g., claude-3-5-haiku-20241022) not the alias
        $this->assertStringContainsString('haiku', $result['model']);
    }

    // Factory Integration Tests

    public function testFactoryCreatedClaudeAgent(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = LLMAgentFactory::claude(
            name: 'FactoryAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'Be brief.'
        );
        $agent->setMaxTokens(50);

        $result = $agent->execute(['prompt' => 'Say "hello" only.']);

        $this->assertArrayHasKey('response', $result);
        $this->assertNotEmpty($result['response']);
    }

    public function testFactoryCreateFromConfig(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = LLMAgentFactory::create([
            'name' => 'ConfigAgent',
            'provider' => 'claude',
            'api_key' => self::$anthropicApiKey,
            'model' => 'claude-sonnet-4-20250514',
            'system_prompt' => 'Always respond with exactly one word.',
            'temperature' => 0.0,
            'max_tokens' => 20
        ]);

        $result = $agent->execute(['prompt' => 'What is the color of grass?']);

        $this->assertArrayHasKey('response', $result);
        $this->assertNotEmpty($result['response']);
    }

    // Cost calculation tests
    
    public function testCostCalculationAccuracy(): void
    {
        if (empty(self::$anthropicApiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $agent = new ClaudeAgent(
            name: 'CostAgent',
            apiKey: self::$anthropicApiKey,
            model: 'claude-sonnet-4-20250514'
        );
        $agent->setMaxTokens(100);

        $result = $agent->execute(['prompt' => 'Count from 1 to 5.']);

        $inputTokens = $result['_usage']['input'];
        $outputTokens = $result['_usage']['output'];
        
        // Claude Sonnet 4 costs: $3.00 per 1M input, $15.00 per 1M output
        $expectedCost = ($inputTokens / 1_000_000) * 3.00 + ($outputTokens / 1_000_000) * 15.00;
        
        $this->assertEqualsWithDelta($expectedCost, $result['_cost'], 0.0001);
    }

    // Error handling tests

    public function testClaudeAgentInvalidApiKey(): void
    {
        $agent = new ClaudeAgent(
            name: 'InvalidAgent',
            apiKey: 'invalid-api-key',
            model: 'claude-sonnet-4-20250514'
        );
        $agent->setMaxTokens(50);

        $this->expectException(\AgentStateLanguage\Exceptions\AgentException::class);
        $agent->execute(['prompt' => 'Hello']);
    }
}
