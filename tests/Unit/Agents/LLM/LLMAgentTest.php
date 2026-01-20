<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Agents\LLM;

use AgentStateLanguage\Agents\LLM\AbstractLLMAgent;
use AgentStateLanguage\Agents\LLM\ClaudeAgent;
use AgentStateLanguage\Agents\LLM\LLMAgentFactory;
use AgentStateLanguage\Agents\LLM\LLMAgentInterface;
use AgentStateLanguage\Agents\LLM\OpenAIAgent;
use AgentStateLanguage\Exceptions\ASLException;
use AgentStateLanguage\Tests\TestCase;

class LLMAgentTest extends TestCase
{
    // ClaudeAgent Tests
    public function testClaudeAgentConstruction(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key', 'claude-sonnet-4-20250514');

        $this->assertEquals('TestAgent', $agent->getName());
        $this->assertEquals('claude-sonnet-4-20250514', $agent->getModel());
    }

    public function testClaudeAgentSetSystemPrompt(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');
        $agent->setSystemPrompt('You are a helpful assistant.');

        $this->assertEquals('You are a helpful assistant.', $agent->getSystemPrompt());
    }

    public function testClaudeAgentSetModel(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');
        $agent->setModel('claude-3-haiku-20240307');

        $this->assertEquals('claude-3-haiku-20240307', $agent->getModel());
    }

    public function testClaudeAgentSetTemperature(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');
        $returned = $agent->setTemperature(0.5);

        // Should be fluent
        $this->assertSame($agent, $returned);
    }

    public function testClaudeAgentSetMaxTokens(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');
        $returned = $agent->setMaxTokens(2000);

        $this->assertSame($agent, $returned);
    }

    public function testClaudeAgentInitialTokenUsage(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');

        $usage = $agent->getLastTokenUsage();
        $this->assertEquals(['input' => 0, 'output' => 0], $usage);
        $this->assertEquals(0.0, $agent->getLastCost());
    }

    // OpenAIAgent Tests
    public function testOpenAIAgentConstruction(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key', 'gpt-4o');

        $this->assertEquals('TestAgent', $agent->getName());
        $this->assertEquals('gpt-4o', $agent->getModel());
    }

    public function testOpenAIAgentSetSystemPrompt(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key');
        $agent->setSystemPrompt('You are a code reviewer.');

        $this->assertEquals('You are a code reviewer.', $agent->getSystemPrompt());
    }

    public function testOpenAIAgentJsonMode(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key');
        $returned = $agent->setJsonMode(true);

        $this->assertSame($agent, $returned);
    }

    public function testOpenAIAgentDefaultModel(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key');

        $this->assertEquals('gpt-5.2', $agent->getModel());
    }

    // Factory Tests
    public function testFactoryCreateClaude(): void
    {
        $agent = LLMAgentFactory::claude('MyAgent', 'api-key');

        $this->assertInstanceOf(ClaudeAgent::class, $agent);
        $this->assertEquals('MyAgent', $agent->getName());
    }

    public function testFactoryCreateOpenAI(): void
    {
        $agent = LLMAgentFactory::openai('MyAgent', 'api-key');

        $this->assertInstanceOf(OpenAIAgent::class, $agent);
        $this->assertEquals('MyAgent', $agent->getName());
    }

    public function testFactoryCreateFromConfig(): void
    {
        $agent = LLMAgentFactory::create([
            'name' => 'ConfiguredAgent',
            'provider' => 'claude',
            'api_key' => 'test-key',
            'model' => 'claude-3-haiku-20240307',
            'system_prompt' => 'You are helpful.',
            'temperature' => 0.3,
            'max_tokens' => 1000
        ]);

        $this->assertInstanceOf(ClaudeAgent::class, $agent);
        $this->assertEquals('ConfiguredAgent', $agent->getName());
        $this->assertEquals('claude-3-haiku-20240307', $agent->getModel());
        $this->assertEquals('You are helpful.', $agent->getSystemPrompt());
    }

    public function testFactoryCreateOpenAIFromConfig(): void
    {
        $agent = LLMAgentFactory::create([
            'name' => 'GPTAgent',
            'provider' => 'openai',
            'api_key' => 'test-key'
        ]);

        $this->assertInstanceOf(OpenAIAgent::class, $agent);
        $this->assertEquals('gpt-5.2', $agent->getModel());
    }

    public function testFactoryUnknownProviderThrows(): void
    {
        $this->expectException(ASLException::class);

        LLMAgentFactory::create([
            'name' => 'Agent',
            'provider' => 'unknown',
            'api_key' => 'key'
        ]);
    }

    public function testFactoryMissingNameThrows(): void
    {
        $this->expectException(ASLException::class);

        LLMAgentFactory::create([
            'provider' => 'claude',
            'api_key' => 'key'
        ]);
    }

    public function testFactoryMissingApiKeyThrows(): void
    {
        $this->expectException(ASLException::class);

        LLMAgentFactory::create([
            'name' => 'Agent',
            'provider' => 'claude'
        ]);
    }

    public function testFactoryCreateMany(): void
    {
        $agents = LLMAgentFactory::createMany([
            'analyzer' => [
                'provider' => 'claude',
                'api_key' => 'key1',
                'system_prompt' => 'Analyze code'
            ],
            'reviewer' => [
                'provider' => 'openai',
                'api_key' => 'key2',
                'system_prompt' => 'Review code'
            ]
        ]);

        $this->assertCount(2, $agents);
        $this->assertInstanceOf(ClaudeAgent::class, $agents['analyzer']);
        $this->assertInstanceOf(OpenAIAgent::class, $agents['reviewer']);
    }

    public function testFactoryProviderAliases(): void
    {
        $agent1 = LLMAgentFactory::create([
            'name' => 'A1',
            'provider' => 'anthropic',
            'api_key' => 'key'
        ]);

        $agent2 = LLMAgentFactory::create([
            'name' => 'A2',
            'provider' => 'gpt',
            'api_key' => 'key'
        ]);

        $this->assertInstanceOf(ClaudeAgent::class, $agent1);
        $this->assertInstanceOf(OpenAIAgent::class, $agent2);
    }

    // Test fluent interface
    public function testFluentInterface(): void
    {
        $agent = new ClaudeAgent('Test', 'key');

        $result = $agent
            ->setSystemPrompt('System')
            ->setModel('claude-3-haiku-20240307')
            ->setTemperature(0.5)
            ->setMaxTokens(1000);

        $this->assertSame($agent, $result);
    }

    // Test temperature bounds
    public function testTemperatureBounds(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function getTemperature(): float
            {
                return $this->temperature;
            }
        };

        $agent->setTemperature(-0.5);
        $this->assertEquals(0.0, $agent->getTemperature());

        $agent->setTemperature(3.0);
        $this->assertEquals(2.0, $agent->getTemperature());

        $agent->setTemperature(0.7);
        $this->assertEquals(0.7, $agent->getTemperature());
    }

    // Test max tokens bounds
    public function testMaxTokensBounds(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function getMaxTokens(): int
            {
                return $this->maxTokens;
            }
        };

        $agent->setMaxTokens(0);
        $this->assertEquals(1, $agent->getMaxTokens());

        $agent->setMaxTokens(-100);
        $this->assertEquals(1, $agent->getMaxTokens());

        $agent->setMaxTokens(8000);
        $this->assertEquals(8000, $agent->getMaxTokens());
    }

    // Test buildUserMessage with different input types
    public function testBuildUserMessageWithPrompt(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function testBuildMessage(array $params): string
            {
                return $this->buildUserMessage($params);
            }
        };

        $this->assertEquals('Hello world', $agent->testBuildMessage(['prompt' => 'Hello world']));
    }

    public function testBuildUserMessageWithMessage(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function testBuildMessage(array $params): string
            {
                return $this->buildUserMessage($params);
            }
        };

        $this->assertEquals('Test message', $agent->testBuildMessage(['message' => 'Test message']));
    }

    public function testBuildUserMessageWithInput(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function testBuildMessage(array $params): string
            {
                return $this->buildUserMessage($params);
            }
        };

        $this->assertEquals('Input text', $agent->testBuildMessage(['input' => 'Input text']));
    }

    public function testBuildUserMessageFallbackToJson(): void
    {
        $agent = new class('Test', 'key', 'model') extends AbstractLLMAgent {
            public function execute(array $parameters): array
            {
                return [];
            }
            public function testBuildMessage(array $params): string
            {
                return $this->buildUserMessage($params);
            }
        };

        $result = $agent->testBuildMessage(['key1' => 'value1', 'key2' => 'value2']);
        $decoded = json_decode($result, true);

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $decoded);
    }

    // Test ClaudeAgent model costs
    public function testClaudeAgentDefaultModel(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key');

        $this->assertEquals('claude-sonnet-4-20250514', $agent->getModel());
    }

    public function testClaudeAgentWithClaude45Models(): void
    {
        $agent = new ClaudeAgent('TestAgent', 'test-api-key', 'claude-opus-4-5-20250514');
        $this->assertEquals('claude-opus-4-5-20250514', $agent->getModel());

        $agent->setModel('claude-sonnet-4-5-20250514');
        $this->assertEquals('claude-sonnet-4-5-20250514', $agent->getModel());

        $agent->setModel('claude-haiku-4-5-20250514');
        $this->assertEquals('claude-haiku-4-5-20250514', $agent->getModel());
    }

    // Test OpenAI GPT-5 models
    public function testOpenAIAgentWithGPT5Models(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key', 'gpt-5.2');
        $this->assertEquals('gpt-5.2', $agent->getModel());

        $agent->setModel('gpt-5.2-pro');
        $this->assertEquals('gpt-5.2-pro', $agent->getModel());

        $agent->setModel('gpt-5-mini');
        $this->assertEquals('gpt-5-mini', $agent->getModel());

        $agent->setModel('gpt-5-nano');
        $this->assertEquals('gpt-5-nano', $agent->getModel());
    }

    public function testOpenAIAgentWithReasoningModels(): void
    {
        $agent = new OpenAIAgent('TestAgent', 'test-api-key', 'o3');
        $this->assertEquals('o3', $agent->getModel());

        $agent->setModel('o3-pro');
        $this->assertEquals('o3-pro', $agent->getModel());

        $agent->setModel('o4-mini');
        $this->assertEquals('o4-mini', $agent->getModel());
    }

    // Test factory with new models
    public function testFactoryWithGPT5Model(): void
    {
        $agent = LLMAgentFactory::create([
            'name' => 'GPT5Agent',
            'provider' => 'openai',
            'api_key' => 'test-key',
            'model' => 'gpt-5.2-pro'
        ]);

        $this->assertInstanceOf(OpenAIAgent::class, $agent);
        $this->assertEquals('gpt-5.2-pro', $agent->getModel());
    }

    public function testFactoryWithClaude45Model(): void
    {
        $agent = LLMAgentFactory::create([
            'name' => 'Claude45Agent',
            'provider' => 'claude',
            'api_key' => 'test-key',
            'model' => 'claude-opus-4-5-20250514'
        ]);

        $this->assertInstanceOf(ClaudeAgent::class, $agent);
        $this->assertEquals('claude-opus-4-5-20250514', $agent->getModel());
    }

    // Test LLMAgentInterface implementation
    public function testLLMAgentInterfaceImplementation(): void
    {
        $claude = new ClaudeAgent('Claude', 'key');
        $openai = new OpenAIAgent('OpenAI', 'key');

        $this->assertInstanceOf(LLMAgentInterface::class, $claude);
        $this->assertInstanceOf(LLMAgentInterface::class, $openai);
    }

    // Test factory shorthand methods with custom models
    public function testFactoryClaudeShorthand(): void
    {
        $agent = LLMAgentFactory::claude(
            'TestClaude',
            'api-key',
            'claude-haiku-4-5-20250514',
            'You are a test agent'
        );

        $this->assertEquals('TestClaude', $agent->getName());
        $this->assertEquals('claude-haiku-4-5-20250514', $agent->getModel());
        $this->assertEquals('You are a test agent', $agent->getSystemPrompt());
    }

    public function testFactoryOpenAIShorthand(): void
    {
        $agent = LLMAgentFactory::openai(
            'TestOpenAI',
            'api-key',
            'gpt-5-mini',
            'You summarize things'
        );

        $this->assertEquals('TestOpenAI', $agent->getName());
        $this->assertEquals('gpt-5-mini', $agent->getModel());
        $this->assertEquals('You summarize things', $agent->getSystemPrompt());
    }
}
