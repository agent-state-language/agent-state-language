# Tutorial 14: Tool-Enabled Agent Workflows

Learn how to integrate claude-php-agent's powerful tool system with ASL workflows for agents that can take real-world actions.

## What You'll Learn

- Bridging claude-php-agent tools with ASL tool permissions
- Creating agents with custom tools (calculator, web search, file operations)
- Tool result propagation through workflow states
- Rate limiting and tool permissions in ASL
- Building a complete research assistant workflow

## Prerequisites

- Completed [Tutorial 13: Integrating Claude PHP Agent](13-integrating-claude-php-agent.md)
- Understanding of claude-php-agent's Tool class

## The Scenario

We'll build a research assistant that can:

1. Search the web for information
2. Perform calculations
3. Read and analyze files
4. Produce structured research reports

## Step 1: Understanding Tool Integration

claude-php-agent tools follow this pattern:

```php
Tool::create('tool_name')
    ->description('What the tool does')
    ->stringParam('param', 'Parameter description')
    ->handler(fn($input) => 'result');
```

We need to bridge this with ASL's tool permission system:

```json
{
  "Tools": {
    "Allowed": ["web_search", "calculator"],
    "Denied": ["file_write"],
    "RateLimits": { "web_search": { "MaxPerMinute": 10 } }
  }
}
```

## Step 2: Create Tool-Aware Adapter

Create `src/Adapters/ToolAwareClaudeAdapter.php`:

```php
<?php

namespace MyOrg\Adapters;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;
use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudePhp\ClaudePhp;

/**
 * Tool-aware adapter for claude-php-agent.
 * 
 * Implements ASL's ToolAwareAgentInterface for permission handling.
 */
class ToolAwareClaudeAdapter implements AgentInterface, ToolAwareAgentInterface
{
    private Agent $agent;
    private string $name;
    private array $allowedTools = [];
    private array $deniedTools = [];
    private array $rateLimits = [];
    private array $toolUsageCount = [];
    private array $toolResults = [];
    private ?AgentResult $lastResult = null;

    /** @var array<string, ToolInterface> */
    private array $registeredTools = [];

    public function __construct(string $name, Agent $agent)
    {
        $this->name = $name;
        $this->agent = $agent;
    }

    /**
     * Create with Claude client and tools.
     *
     * @param string $name
     * @param ClaudePhp $client
     * @param array<ToolInterface> $tools
     * @param string $systemPrompt
     */
    public static function create(
        string $name,
        ClaudePhp $client,
        array $tools = [],
        string $systemPrompt = ''
    ): self {
        $agent = Agent::create($client)
            ->withName($name)
            ->withSystemPrompt($systemPrompt)
            ->withTools($tools);

        $adapter = new self($name, $agent);

        // Track registered tools
        foreach ($tools as $tool) {
            $adapter->registeredTools[$tool->getName()] = $tool;
        }

        return $adapter;
    }

    /**
     * Set allowed tools (from ASL Tools.Allowed).
     */
    public function setAllowedTools(array $tools): void
    {
        $this->allowedTools = $tools;
        $this->updateAgentTools();
    }

    /**
     * Set denied tools (from ASL Tools.Denied).
     */
    public function setDeniedTools(array $tools): void
    {
        $this->deniedTools = $tools;
        $this->updateAgentTools();
    }

    /**
     * Set rate limits (from ASL Tools.RateLimits).
     */
    public function setRateLimits(array $limits): void
    {
        $this->rateLimits = $limits;
    }

    /**
     * Execute with tool tracking.
     */
    public function execute(array $parameters): array
    {
        // Reset per-execution state
        $this->toolResults = [];
        $this->toolUsageCount = [];

        // Configure tool execution callback for tracking
        $this->agent->onToolExecution(function (string $tool, array $input, $result) {
            $this->trackToolUsage($tool, $input, $result);
        });

        // Extract task
        $task = $this->extractTask($parameters);

        // Run agent
        $this->lastResult = $this->agent->run($task);

        return $this->formatResult($this->lastResult, $parameters);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get tools that were used during execution.
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    /**
     * Get the underlying agent.
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Update agent's available tools based on permissions.
     */
    private function updateAgentTools(): void
    {
        $filteredTools = [];

        foreach ($this->registeredTools as $name => $tool) {
            // Check if denied
            if (in_array($name, $this->deniedTools)) {
                continue;
            }

            // Check if allowed (empty allowlist means all allowed)
            if (!empty($this->allowedTools) && !in_array($name, $this->allowedTools)) {
                continue;
            }

            $filteredTools[] = $tool;
        }

        // Recreate agent with filtered tools
        // Note: In practice, you might use a more efficient approach
        $this->agent = $this->agent->withTools($filteredTools);
    }

    /**
     * Track tool usage for rate limiting and reporting.
     */
    private function trackToolUsage(string $toolName, array $input, $result): void
    {
        // Check rate limits before allowing
        if (!$this->checkRateLimit($toolName)) {
            throw new \RuntimeException("Rate limit exceeded for tool: {$toolName}");
        }

        // Increment usage count
        $this->toolUsageCount[$toolName] = ($this->toolUsageCount[$toolName] ?? 0) + 1;

        // Store result for reporting
        $this->toolResults[] = [
            'tool' => $toolName,
            'input' => $input,
            'result' => is_object($result) && method_exists($result, 'getContent') 
                ? $result->getContent() 
                : (string) $result,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Check if tool usage is within rate limits.
     */
    private function checkRateLimit(string $toolName): bool
    {
        if (!isset($this->rateLimits[$toolName])) {
            return true;
        }

        $limits = $this->rateLimits[$toolName];
        $currentCount = $this->toolUsageCount[$toolName] ?? 0;

        // Simple per-execution check (in production, use time-based tracking)
        if (isset($limits['MaxPerMinute']) && $currentCount >= $limits['MaxPerMinute']) {
            return false;
        }

        return true;
    }

    /**
     * Extract task from parameters.
     */
    private function extractTask(array $parameters): string
    {
        foreach (['prompt', 'task', 'message', 'input'] as $key) {
            if (isset($parameters[$key])) {
                return (string) $parameters[$key];
            }
        }

        return json_encode($parameters, JSON_PRETTY_PRINT);
    }

    /**
     * Format result for ASL.
     */
    private function formatResult(AgentResult $result, array $params): array
    {
        $tokenUsage = $result->getTokenUsage();
        $totalTokens = ($tokenUsage['input'] ?? 0) + ($tokenUsage['output'] ?? 0);

        return [
            'response' => $result->getAnswer(),
            'success' => $result->isSuccess(),
            'iterations' => $result->getIterations(),
            'toolsUsed' => $this->toolResults,
            'toolUsageCount' => $this->toolUsageCount,
            '_tokens' => $totalTokens,
            '_cost' => $this->calculateCost($tokenUsage),
            '_usage' => $tokenUsage,
        ];
    }

    private function calculateCost(array $tokenUsage): float
    {
        $inputCost = (($tokenUsage['input'] ?? 0) / 1_000_000) * 3.00;
        $outputCost = (($tokenUsage['output'] ?? 0) / 1_000_000) * 15.00;
        return $inputCost + $outputCost;
    }
}
```

## Step 3: Create Common Tools

Create `src/Tools/ResearchTools.php`:

```php
<?php

namespace MyOrg\Tools;

use ClaudeAgents\Tools\Tool;

/**
 * Collection of tools for research workflows.
 */
class ResearchTools
{
    /**
     * Create a calculator tool.
     */
    public static function calculator(): Tool
    {
        return Tool::create('calculator')
            ->description('Perform mathematical calculations. Supports basic arithmetic, percentages, and common functions.')
            ->stringParam('expression', 'Mathematical expression to evaluate (e.g., "25 * 1.15" or "sqrt(144)")')
            ->handler(function (array $input): string {
                $expression = $input['expression'] ?? '';
                
                // Sanitize and evaluate
                $sanitized = preg_replace('/[^0-9+\-*\/().%\s]/', '', $expression);
                
                // Handle common functions
                $sanitized = str_replace(['sqrt', 'pow', 'abs'], ['\\sqrt', '\\pow', '\\abs'], $sanitized);
                
                try {
                    // Simple evaluation (in production, use a proper math parser)
                    $result = eval("return {$sanitized};");
                    return json_encode([
                        'expression' => $expression,
                        'result' => $result,
                        'formatted' => number_format($result, 2)
                    ]);
                } catch (\Throwable $e) {
                    return json_encode([
                        'error' => 'Invalid expression',
                        'expression' => $expression
                    ]);
                }
            });
    }

    /**
     * Create a web search tool (simulated).
     */
    public static function webSearch(): Tool
    {
        return Tool::create('web_search')
            ->description('Search the web for information on a topic.')
            ->stringParam('query', 'Search query')
            ->numberParam('limit', 'Maximum number of results', false)
            ->handler(function (array $input): string {
                $query = $input['query'] ?? '';
                $limit = $input['limit'] ?? 5;
                
                // In production, integrate with a real search API
                // This is a simulation for demonstration
                $results = [
                    [
                        'title' => "Understanding {$query} - Comprehensive Guide",
                        'url' => 'https://example.com/guide',
                        'snippet' => "A detailed guide about {$query} covering all aspects...",
                    ],
                    [
                        'title' => "{$query} Best Practices 2025",
                        'url' => 'https://example.com/best-practices',
                        'snippet' => "Industry best practices for {$query} in modern development...",
                    ],
                    [
                        'title' => "Latest Research on {$query}",
                        'url' => 'https://example.com/research',
                        'snippet' => "Recent academic research findings about {$query}...",
                    ],
                ];
                
                return json_encode([
                    'query' => $query,
                    'results' => array_slice($results, 0, $limit),
                    'totalResults' => count($results),
                ]);
            });
    }

    /**
     * Create a file reader tool.
     */
    public static function fileReader(): Tool
    {
        return Tool::create('read_file')
            ->description('Read contents of a file from the allowed paths.')
            ->stringParam('path', 'Path to the file to read')
            ->handler(function (array $input): string {
                $path = $input['path'] ?? '';
                
                // Security: Only allow certain directories
                $allowedPaths = ['./data/', './docs/', './research/'];
                $isAllowed = false;
                
                foreach ($allowedPaths as $allowed) {
                    if (str_starts_with($path, $allowed)) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    return json_encode([
                        'error' => 'Access denied',
                        'path' => $path,
                        'allowedPaths' => $allowedPaths,
                    ]);
                }
                
                if (!file_exists($path)) {
                    return json_encode([
                        'error' => 'File not found',
                        'path' => $path,
                    ]);
                }
                
                $content = file_get_contents($path);
                
                return json_encode([
                    'path' => $path,
                    'content' => $content,
                    'size' => strlen($content),
                    'lines' => substr_count($content, "\n") + 1,
                ]);
            });
    }

    /**
     * Create a data analyzer tool.
     */
    public static function dataAnalyzer(): Tool
    {
        return Tool::create('analyze_data')
            ->description('Analyze numerical data and provide statistics.')
            ->arrayParam('values', 'Array of numerical values to analyze')
            ->handler(function (array $input): string {
                $values = $input['values'] ?? [];
                
                if (empty($values)) {
                    return json_encode(['error' => 'No values provided']);
                }
                
                $count = count($values);
                $sum = array_sum($values);
                $mean = $sum / $count;
                
                sort($values);
                $median = $count % 2 === 0
                    ? ($values[$count / 2 - 1] + $values[$count / 2]) / 2
                    : $values[floor($count / 2)];
                
                $variance = array_sum(array_map(
                    fn($x) => pow($x - $mean, 2),
                    $values
                )) / $count;
                
                $stdDev = sqrt($variance);
                
                return json_encode([
                    'count' => $count,
                    'sum' => $sum,
                    'mean' => round($mean, 2),
                    'median' => round($median, 2),
                    'min' => min($values),
                    'max' => max($values),
                    'range' => max($values) - min($values),
                    'standardDeviation' => round($stdDev, 2),
                    'variance' => round($variance, 2),
                ]);
            });
    }

    /**
     * Get all research tools.
     *
     * @return array<Tool>
     */
    public static function all(): array
    {
        return [
            self::calculator(),
            self::webSearch(),
            self::fileReader(),
            self::dataAnalyzer(),
        ];
    }
}
```

## Step 4: Define the Workflow

Create `workflows/research-assistant.asl.json`:

```json
{
  "Comment": "Research assistant workflow with tool-enabled agents",
  "Version": "1.0",
  "StartAt": "GatherInformation",
  "States": {
    "GatherInformation": {
      "Type": "Task",
      "Agent": "Researcher",
      "Parameters": {
        "prompt.$": "States.Format('Research the following topic thoroughly: {}\n\nUse web_search to find relevant information. Gather at least 3 sources.', $.topic)"
      },
      "Tools": {
        "Allowed": ["web_search"],
        "RateLimits": {
          "web_search": {
            "MaxPerMinute": 5
          }
        }
      },
      "ResultPath": "$.research",
      "Next": "AnalyzeData"
    },
    "AnalyzeData": {
      "Type": "Task",
      "Agent": "Analyst",
      "Parameters": {
        "prompt.$": "States.Format('Analyze the following research data:\n\n{}\n\nIf there are any numbers to analyze, use the calculator or analyze_data tools. Provide insights and calculations.', $.research.response)"
      },
      "Tools": {
        "Allowed": ["calculator", "analyze_data"],
        "Denied": ["web_search", "read_file"]
      },
      "ResultPath": "$.analysis",
      "Next": "CheckForFiles"
    },
    "CheckForFiles": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.includeFiles",
          "BooleanEquals": true,
          "Next": "ReadSupportingFiles"
        }
      ],
      "Default": "GenerateReport"
    },
    "ReadSupportingFiles": {
      "Type": "Task",
      "Agent": "FileReader",
      "Parameters": {
        "prompt.$": "States.Format('Read the following files and summarize their contents:\n\nFiles: {}\n\nUse read_file for each file path.', $.filePaths)"
      },
      "Tools": {
        "Allowed": ["read_file"],
        "FileSystem": {
          "AllowedPaths": ["./data/**", "./docs/**"],
          "MaxFileSize": "5M"
        }
      },
      "ResultPath": "$.fileContents",
      "Next": "GenerateReport"
    },
    "GenerateReport": {
      "Type": "Task",
      "Agent": "ReportWriter",
      "Parameters": {
        "prompt.$": "States.Format('Generate a comprehensive research report based on:\n\nTopic: {}\n\nResearch Findings:\n{}\n\nAnalysis:\n{}\n\nAdditional Context:\n{}\n\nFormat as a structured report with sections: Executive Summary, Key Findings, Analysis, Recommendations.', $.topic, $.research.response, $.analysis.response, $.fileContents.response)"
      },
      "Tools": {
        "Allowed": [],
        "Denied": ["web_search", "read_file", "calculator"]
      },
      "ResultPath": "$.report",
      "Next": "FormatOutput"
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "topic.$": "$.topic",
        "report.$": "$.report.response",
        "toolUsage": {
          "research.$": "$.research.toolsUsed",
          "analysis.$": "$.analysis.toolsUsed",
          "files.$": "$.fileContents.toolsUsed"
        },
        "metrics": {
          "totalTokens.$": "States.MathAdd($.research._tokens, $.analysis._tokens, $.report._tokens)",
          "totalCost.$": "States.MathAdd($.research._cost, $.analysis._cost, $.report._cost)"
        }
      },
      "End": true
    }
  }
}
```

## Step 5: Run the Workflow

Create `run-research.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use ClaudePhp\ClaudePhp;
use MyOrg\Adapters\ToolAwareClaudeAdapter;
use MyOrg\Tools\ResearchTools;

// Initialize client
$client = ClaudePhp::make(getenv('ANTHROPIC_API_KEY'));

// Create tool-aware agents
$researcher = ToolAwareClaudeAdapter::create(
    'Researcher',
    $client,
    [ResearchTools::webSearch()],
    'You are a thorough research assistant. Use the web_search tool to find accurate, up-to-date information. Always cite your sources.'
);

$analyst = ToolAwareClaudeAdapter::create(
    'Analyst',
    $client,
    [ResearchTools::calculator(), ResearchTools::dataAnalyzer()],
    'You are a data analyst. Use calculator and analyze_data tools to provide quantitative insights. Show your calculations.'
);

$fileReader = ToolAwareClaudeAdapter::create(
    'FileReader',
    $client,
    [ResearchTools::fileReader()],
    'You are a document specialist. Read files and extract key information. Summarize content clearly.'
);

$reportWriter = ToolAwareClaudeAdapter::create(
    'ReportWriter',
    $client,
    [], // No tools - just generation
    'You are a professional report writer. Create well-structured, clear reports. Use markdown formatting.'
);

// Register agents
$registry = new AgentRegistry();
$registry->register('Researcher', $researcher);
$registry->register('Analyst', $analyst);
$registry->register('FileReader', $fileReader);
$registry->register('ReportWriter', $reportWriter);

// Load workflow
$engine = WorkflowEngine::fromFile('workflows/research-assistant.asl.json', $registry);

// Run research
$result = $engine->run([
    'topic' => 'Impact of AI on software development productivity in 2025',
    'includeFiles' => false,
    'filePaths' => [],
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    
    echo "=== Research Report ===\n\n";
    echo "Topic: {$output['topic']}\n\n";
    echo "--- Report ---\n";
    echo $output['report'] . "\n\n";
    
    echo "--- Tool Usage Summary ---\n";
    $researchTools = $output['toolUsage']['research'] ?? [];
    echo "Research phase: " . count($researchTools) . " tool calls\n";
    foreach ($researchTools as $usage) {
        echo "  - {$usage['tool']}: {$usage['input']['query'] ?? 'N/A'}\n";
    }
    
    $analysisTools = $output['toolUsage']['analysis'] ?? [];
    echo "Analysis phase: " . count($analysisTools) . " tool calls\n";
    
    echo "\n--- Metrics ---\n";
    echo "Total tokens: {$output['metrics']['totalTokens']}\n";
    echo "Total cost: $" . number_format($output['metrics']['totalCost'], 4) . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
    echo "Cause: " . $result->getErrorCause() . "\n";
}
```

## Expected Output

```
=== Research Report ===

Topic: Impact of AI on software development productivity in 2025

--- Report ---
# Executive Summary

This report analyzes the impact of artificial intelligence on software 
development productivity based on current research and industry trends.

## Key Findings

1. **Productivity Gains**: Studies show 25-40% improvement in coding speed
2. **Code Quality**: AI-assisted code review reduces bugs by 30%
3. **Developer Adoption**: 78% of developers now use AI tools daily

## Analysis

Based on the data analyzed:
- Mean productivity improvement: 32.5%
- Median implementation time reduction: 28%
- Standard deviation in results: 8.3%

## Recommendations

1. Integrate AI coding assistants into development workflows
2. Implement AI-powered code review processes
3. Invest in developer training for AI tool usage

--- Tool Usage Summary ---
Research phase: 3 tool calls
  - web_search: AI software development productivity 2025
  - web_search: AI coding assistants statistics
  - web_search: AI impact developer workflow
Analysis phase: 2 tool calls

--- Metrics ---
Total tokens: 4521
Total cost: $0.0892
```

## Tool Permission Patterns

### Least Privilege

Give each agent only the tools it needs:

```json
{
  "Researcher": {
    "Allowed": ["web_search"],
    "Denied": ["read_file", "write_file"]
  },
  "Analyst": {
    "Allowed": ["calculator", "analyze_data"],
    "Denied": ["web_search"]
  }
}
```

### Progressive Access

Grant more tools as workflow progresses:

```json
{
  "InitialAnalysis": {
    "Tools": { "Allowed": ["read_file"] }
  },
  "DeepAnalysis": {
    "Tools": { "Allowed": ["read_file", "calculator", "analyze_data"] }
  },
  "FinalReport": {
    "Tools": { "Allowed": [] }
  }
}
```

### Rate Limiting

Prevent abuse and control costs:

```json
{
  "Tools": {
    "RateLimits": {
      "web_search": {
        "MaxPerMinute": 10,
        "MaxPerHour": 100
      },
      "calculator": {
        "MaxPerMinute": 50
      }
    }
  }
}
```

## Experiment

Try these modifications:

### Add a Custom Tool

```php
$customTool = Tool::create('summarize_url')
    ->description('Fetch and summarize a web page')
    ->stringParam('url', 'URL to summarize')
    ->handler(function (array $input): string {
        $url = $input['url'];
        // Fetch and summarize logic
        return json_encode(['summary' => '...']);
    });

$agent = ToolAwareClaudeAdapter::create(
    'Summarizer',
    $client,
    [$customTool],
    'You summarize web content.'
);
```

### Tool Execution Callbacks

```php
$agent->getAgent()->onToolExecution(function ($tool, $input, $result) {
    echo "[Tool] {$tool} called with: " . json_encode($input) . "\n";
    echo "[Result] " . substr($result->getContent(), 0, 100) . "...\n";
});
```

### Conditional Tool Access

```json
{
  "Type": "Choice",
  "Choices": [
    {
      "Variable": "$.userRole",
      "StringEquals": "admin",
      "Next": "FullAccessState"
    }
  ],
  "Default": "RestrictedAccessState"
}
```

## Common Mistakes

### Tool Not in Allowlist

```
Error: Tool 'calculator' not allowed in this state
```

**Fix**: Add the tool to the `Allowed` array or remove restrictive permissions.

### Rate Limit Exceeded

```
RuntimeException: Rate limit exceeded for tool: web_search
```

**Fix**: Increase rate limits or optimize agent prompts to use fewer tool calls.

### Tool Handler Error

```
Error: Tool execution failed
```

**Fix**: Ensure tool handlers properly handle edge cases and return valid JSON.

### Circular Tool Dependencies

```
Agent keeps calling tools without reaching conclusion
```

**Fix**: Set `maxIterations` on the agent and provide clear stopping criteria in prompts.

## Summary

You've learned:

- ✅ Creating tool-aware agent adapters
- ✅ Building custom tools for research workflows
- ✅ Applying ASL tool permissions to claude-php-agent
- ✅ Rate limiting tool usage
- ✅ Tracking tool usage across workflow states
- ✅ Building a complete research assistant

## Next Steps

- [Tutorial 15: Multi-Agent Orchestration](15-multi-agent-orchestration.md) - Coordinate multiple agents
- [Tutorial 16: Loop Strategies in Workflows](16-loop-strategies-in-workflows.md) - Advanced reasoning patterns
