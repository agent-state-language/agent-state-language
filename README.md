# Agent State Language (ASL)

A JSON-based domain-specific language for defining configurable, composable AI agent workflows.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net)

## Overview

Agent State Language extends the concepts of AWS Step Functions' Amazon States Language with **agent-native primitives** for:

- 🧠 **Memory & Context** - Persistent memory, sliding context windows
- 🔧 **Tool Orchestration** - Permissions, rate limits, sandboxing
- 👤 **Human-in-the-Loop** - Approval gates, feedback collection
- 💬 **Multi-Agent Communication** - Debates, delegation, consensus
- 💰 **Cost Management** - Token budgets, model fallbacks
- 🛡️ **Guardrails** - Input/output validation, content moderation
- 📊 **Observability** - Reasoning traces, execution logs

## Quick Start

### Installation

```bash
composer require agent-state-language/asl
```

### Define a Workflow

Create `workflow.asl.json`:

```json
{
  "Comment": "Simple greeting workflow",
  "StartAt": "Greet",
  "States": {
    "Greet": {
      "Type": "Task",
      "Agent": "GreeterAgent",
      "Parameters": {
        "name.$": "$.userName"
      },
      "End": true
    }
  }
}
```

### Run It

```php
<?php

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

$registry = new AgentRegistry();
$registry->register('GreeterAgent', new MyGreeterAgent());

$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$result = $engine->run(['userName' => 'Alice']);

echo $result->getOutput(); // "Hello, Alice!"
```

## State Types

### Core States (AWS ASL Compatible)

| State | Description |
|-------|-------------|
| `Task` | Execute an agent with parameters |
| `Choice` | Conditional branching |
| `Map` | Iterate over arrays |
| `Parallel` | Execute branches concurrently |
| `Pass` | Transform data |
| `Wait` | Pause execution |
| `Succeed` | End successfully |
| `Fail` | End with failure |

### Agent-Native States (ASL Extensions)

| State | Description |
|-------|-------------|
| `Approval` | Human-in-the-loop gate |
| `Debate` | Multi-agent deliberation |
| `Checkpoint` | Save/resume point |

## Agent-Native Extensions

### Memory Block

```json
{
  "AnalyzeCode": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Memory": {
      "Read": ["project_patterns", "user_preferences"],
      "Write": { "Key": "analysis_results", "TTL": "7d" }
    }
  }
}
```

### Context Block

```json
{
  "GenerateResponse": {
    "Type": "Task",
    "Agent": "Responder",
    "Context": {
      "Strategy": "sliding_window",
      "MaxTokens": 8000,
      "Priority": ["$.currentTask", "$.recentHistory"]
    }
  }
}
```

### Tools Block

```json
{
  "Research": {
    "Type": "Task",
    "Agent": "Researcher",
    "Tools": {
      "Allowed": ["web_search", "read_file"],
      "Denied": ["write_file", "execute_shell"],
      "RateLimits": { "web_search": { "MaxPerMinute": 10 } }
    }
  }
}
```

### Budget Block

```json
{
  "Comment": "Workflow with cost controls",
  "Budget": {
    "MaxTokens": 100000,
    "MaxCost": "$5.00",
    "OnExceed": "PauseAndNotify"
  }
}
```

### Approval State

```json
{
  "ReviewChanges": {
    "Type": "Approval",
    "Prompt": "Review the proposed changes",
    "Options": ["approve", "reject", "modify"],
    "Timeout": "24h",
    "Next": "ApplyChanges"
  }
}
```

### Debate State

```json
{
  "DebateSolution": {
    "Type": "Debate",
    "Agents": ["ProAgent", "ConAgent", "JudgeAgent"],
    "Topic.$": "$.proposal",
    "Rounds": 3,
    "Consensus": { "Required": true, "Arbiter": "JudgeAgent" }
  }
}
```

## Documentation

- 📖 [Specification](SPECIFICATION.md) - Complete language specification
- 🚀 [Getting Started](docs/getting-started.md) - First steps guide
- 📚 [Tutorials](docs/tutorials/) - Step-by-step learning path
- 📘 [Guides](docs/guides/) - Best practices and patterns
- 📋 [Reference](docs/reference/) - API and syntax reference

## Examples

| Example | Description |
|---------|-------------|
| [Task Breakdown](examples/task-breakdown/) | Recursive task decomposition |
| [Code Review](examples/code-review/) | Multi-agent review with approval |
| [Research Assistant](examples/research-assistant/) | Web search and synthesis |
| [Customer Support](examples/customer-support/) | Intent routing and escalation |
| [Content Pipeline](examples/content-pipeline/) | Generation and moderation |

## Tutorial Path

### Core Tutorials

1. [Hello World](docs/tutorials/01-hello-world.md) - Your first workflow
2. [Simple Workflow](docs/tutorials/02-simple-workflow.md) - Sequential states
3. [Conditional Logic](docs/tutorials/03-conditional-logic.md) - Choice states
4. [Parallel Execution](docs/tutorials/04-parallel-execution.md) - Concurrent branches
5. [Recursive Workflows](docs/tutorials/05-recursive-workflows.md) - Map iterations
6. [Memory & Context](docs/tutorials/06-memory-and-context.md) - State persistence
7. [Tool Orchestration](docs/tutorials/07-tool-orchestration.md) - Tool permissions
8. [Human Approval](docs/tutorials/08-human-approval.md) - Human-in-the-loop
9. [Multi-Agent Debate](docs/tutorials/09-multi-agent-debate.md) - Agent collaboration
10. [Cost Management](docs/tutorials/10-cost-management.md) - Budget controls
11. [Error Handling](docs/tutorials/11-error-handling.md) - Retry and catch
12. [Building Skills](docs/tutorials/12-building-skills.md) - Composition patterns

### claude-php-agent Integration

13. [Integrating Claude PHP Agent](docs/tutorials/13-integrating-claude-php-agent.md) - Wrapper pattern for ASL
14. [Tool-Enabled Agent Workflows](docs/tutorials/14-tool-enabled-agent-workflows.md) - Tools in workflows
15. [Multi-Agent Orchestration](docs/tutorials/15-multi-agent-orchestration.md) - Parallel agent execution
16. [Loop Strategies in Workflows](docs/tutorials/16-loop-strategies-in-workflows.md) - ReAct, Reflection, Plan-Execute
17. [RAG-Enhanced Workflows](docs/tutorials/17-rag-enhanced-workflows.md) - Knowledge-augmented agents

## Requirements

- PHP 8.1 or higher
- Composer

## License

MIT License - see [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

## Related Projects

- [claude-php-agent](https://github.com/claude-php/claude-php-agent) - PHP agent framework with ReAct, Reflection, and Plan-Execute loops
- [Claude PHP SDK](https://github.com/claude-php/claude-php-sdk) - Low-level PHP SDK for Claude API
- [AWS Step Functions](https://aws.amazon.com/step-functions/) - Inspiration for ASL

## Integration with claude-php-agent

ASL can be combined with claude-php-agent for advanced AI agent workflows:

```php
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;

// Create a claude-php-agent wrapped for ASL
$client = ClaudePhp::make(getenv('ANTHROPIC_API_KEY'));
$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful assistant.')
    ->withTools([...]);

// Register with ASL
$registry = new AgentRegistry();
$registry->register('Assistant', new ClaudeAgentAdapter('Assistant', $agent));

// Run workflow with advanced agent capabilities
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);
$result = $engine->run(['task' => 'Analyze this code...']);
```

See [Tutorial 13: Integrating Claude PHP Agent](docs/tutorials/13-integrating-claude-php-agent.md) for complete integration guide.
