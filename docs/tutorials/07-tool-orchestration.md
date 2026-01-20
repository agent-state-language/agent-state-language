# Tutorial 7: Tool Orchestration

Learn how to control tool access and permissions for agents in your workflows.

## What You'll Learn

- Tool allowlists and denylists for security
- Rate limiting to prevent abuse
- Sandboxing for safe code execution
- File system and network restrictions
- Building a secure research agent

## Prerequisites

- Completed [Tutorial 6: Memory and Context](06-memory-and-context.md)
- Understanding of Task states

## The Scenario

We'll build a secure research workflow that:

1. Allows agents to search the web and read files
2. Prevents dangerous operations like file deletion
3. Rate limits API calls to avoid costs
4. Sandboxes any code execution

## Step 1: Understanding Tool Permissions

Tools are capabilities that agents can use during execution. ASL provides fine-grained control over which tools each agent can access.

### Permission Hierarchy

| Level | Scope | Example |
|-------|-------|---------|
| Workflow | All states | Default tools for entire workflow |
| State | Single state | Override for specific operations |
| Denied | Blocklist | Never allow these tools |

## Step 2: Create the Agents

### ResearcherAgent

An agent that uses web search and file reading tools:

```php
<?php

namespace MyOrg\SecureResearch;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;

class ResearcherAgent implements AgentInterface, ToolAwareAgentInterface
{
    private array $allowedTools = [];
    private array $toolResults = [];

    public function setAllowedTools(array $tools): void
    {
        $this->allowedTools = $tools;
    }

    public function execute(array $parameters): array
    {
        $query = $parameters['query'] ?? '';
        $sources = $parameters['sources'] ?? ['web'];
        
        $results = [];
        
        // Check if web_search is allowed before using it
        if (in_array('web', $sources) && $this->canUseTool('web_search')) {
            $results['web'] = $this->performWebSearch($query);
        }
        
        // Check if read_file is allowed
        if (in_array('files', $sources) && $this->canUseTool('read_file')) {
            $results['files'] = $this->searchLocalFiles($query);
        }
        
        return [
            'query' => $query,
            'results' => $results,
            'sourcesSearched' => array_keys($results),
            'toolsUsed' => $this->toolResults,
            'timestamp' => date('c')
        ];
    }
    
    private function canUseTool(string $tool): bool
    {
        return empty($this->allowedTools) || in_array($tool, $this->allowedTools);
    }
    
    private function performWebSearch(string $query): array
    {
        // Simulate web search - in real implementation, call search API
        $this->toolResults[] = ['tool' => 'web_search', 'query' => $query];
        
        return [
            ['title' => 'Result 1', 'url' => 'https://example.com/1', 'snippet' => 'Relevant content...'],
            ['title' => 'Result 2', 'url' => 'https://example.com/2', 'snippet' => 'More content...'],
        ];
    }
    
    private function searchLocalFiles(string $query): array
    {
        // Simulate file search
        $this->toolResults[] = ['tool' => 'read_file', 'query' => $query];
        
        return [
            ['file' => 'docs/notes.txt', 'matches' => 3],
            ['file' => 'data/research.md', 'matches' => 5],
        ];
    }

    public function getName(): string
    {
        return 'ResearcherAgent';
    }
}
```

### CodeAnalyzerAgent

An agent that needs sandboxed code execution:

```php
<?php

namespace MyOrg\SecureResearch;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;

class CodeAnalyzerAgent implements AgentInterface, ToolAwareAgentInterface
{
    private array $allowedTools = [];
    private bool $sandboxed = false;
    private array $sandboxConfig = [];

    public function setAllowedTools(array $tools): void
    {
        $this->allowedTools = $tools;
    }
    
    public function setSandboxConfig(array $config): void
    {
        $this->sandboxed = true;
        $this->sandboxConfig = $config;
    }

    public function execute(array $parameters): array
    {
        $code = $parameters['code'] ?? '';
        $language = $parameters['language'] ?? 'php';
        
        // Only execute if sandboxed and execute_code is allowed
        if (!$this->canUseTool('execute_code')) {
            return [
                'error' => 'Code execution not permitted',
                'analyzed' => false
            ];
        }
        
        if (!$this->sandboxed) {
            return [
                'error' => 'Code execution requires sandbox environment',
                'analyzed' => false
            ];
        }
        
        // Simulate sandboxed execution
        $analysis = $this->analyzeInSandbox($code, $language);
        
        return [
            'analyzed' => true,
            'language' => $language,
            'metrics' => $analysis,
            'sandboxUsed' => true,
            'sandboxConfig' => $this->sandboxConfig
        ];
    }
    
    private function canUseTool(string $tool): bool
    {
        return empty($this->allowedTools) || in_array($tool, $this->allowedTools);
    }
    
    private function analyzeInSandbox(string $code, string $language): array
    {
        // Simulate code analysis
        return [
            'lines' => substr_count($code, "\n") + 1,
            'complexity' => 'low',
            'issues' => [],
            'executionTime' => '0.05s',
            'memoryUsed' => '12M'
        ];
    }

    public function getName(): string
    {
        return 'CodeAnalyzerAgent';
    }
}
```

### FileProcessorAgent

An agent with restricted file system access:

```php
<?php

namespace MyOrg\SecureResearch;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;

class FileProcessorAgent implements AgentInterface, ToolAwareAgentInterface
{
    private array $allowedTools = [];
    private array $allowedPaths = [];
    private array $deniedPaths = [];
    private int $maxFileSize = 10485760; // 10MB default

    public function setAllowedTools(array $tools): void
    {
        $this->allowedTools = $tools;
    }
    
    public function setFileSystemConfig(array $config): void
    {
        $this->allowedPaths = $config['AllowedPaths'] ?? [];
        $this->deniedPaths = $config['DeniedPaths'] ?? [];
        if (isset($config['MaxFileSize'])) {
            $this->maxFileSize = $this->parseSize($config['MaxFileSize']);
        }
    }

    public function execute(array $parameters): array
    {
        $operation = $parameters['operation'] ?? 'read';
        $path = $parameters['path'] ?? '';
        
        // Validate path access
        if (!$this->isPathAllowed($path)) {
            return [
                'success' => false,
                'error' => "Access denied to path: {$path}",
                'allowedPaths' => $this->allowedPaths
            ];
        }
        
        // Check tool permissions
        $toolName = $operation === 'read' ? 'read_file' : 'write_file';
        if (!$this->canUseTool($toolName)) {
            return [
                'success' => false,
                'error' => "Tool not permitted: {$toolName}"
            ];
        }
        
        // Simulate file operation
        return [
            'success' => true,
            'operation' => $operation,
            'path' => $path,
            'size' => '1.2KB',
            'processedAt' => date('c')
        ];
    }
    
    private function canUseTool(string $tool): bool
    {
        return empty($this->allowedTools) || in_array($tool, $this->allowedTools);
    }
    
    private function isPathAllowed(string $path): bool
    {
        // Check denied paths first
        foreach ($this->deniedPaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }
        
        // Check allowed paths
        if (empty($this->allowedPaths)) {
            return true;
        }
        
        foreach ($this->allowedPaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function parseSize(string $size): int
    {
        $units = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824];
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;
        
        return isset($units[$unit]) ? $value * $units[$unit] : $value;
    }

    public function getName(): string
    {
        return 'FileProcessorAgent';
    }
}
```

## Step 3: Define the Workflow

Create `secure-research.asl.json`:

```json
{
  "Comment": "Secure research workflow with tool restrictions",
  "StartAt": "WebResearch",
  "States": {
    "WebResearch": {
      "Type": "Task",
      "Agent": "ResearcherAgent",
      "Parameters": {
        "query.$": "$.query",
        "sources": ["web"]
      },
      "Tools": {
        "Allowed": ["web_search", "fetch_url"],
        "Denied": ["execute_shell", "write_file", "delete_file"],
        "RateLimits": {
          "web_search": {
            "MaxPerMinute": 10,
            "MaxPerHour": 100
          },
          "fetch_url": {
            "MaxPerMinute": 20
          }
        }
      },
      "ResultPath": "$.webResults",
      "Next": "LocalFileSearch"
    },
    "LocalFileSearch": {
      "Type": "Task",
      "Agent": "FileProcessorAgent",
      "Parameters": {
        "operation": "read",
        "path.$": "$.searchPath"
      },
      "Tools": {
        "Allowed": ["read_file", "grep"],
        "Denied": ["write_file", "delete_file"],
        "FileSystem": {
          "AllowedPaths": ["./data/**", "./docs/**", "./research/**"],
          "DeniedPaths": ["./.env", "./secrets/**", "./.git/**"],
          "MaxFileSize": "10M"
        }
      },
      "ResultPath": "$.fileResults",
      "Next": "CheckCodeAnalysis"
    },
    "CheckCodeAnalysis": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.analyzeCode",
          "BooleanEquals": true,
          "Next": "AnalyzeCode"
        }
      ],
      "Default": "CombineResults"
    },
    "AnalyzeCode": {
      "Type": "Task",
      "Agent": "CodeAnalyzerAgent",
      "Parameters": {
        "code.$": "$.codeSnippet",
        "language.$": "$.language"
      },
      "Tools": {
        "Allowed": ["execute_code"],
        "Sandboxed": true,
        "Sandbox": {
          "Environment": "docker",
          "Image": "php:8.1-cli",
          "Timeout": "30s",
          "Memory": "256M",
          "Network": false,
          "ReadOnlyFS": true
        }
      },
      "ResultPath": "$.codeAnalysis",
      "Next": "CombineResults"
    },
    "CombineResults": {
      "Type": "Pass",
      "Parameters": {
        "query.$": "$.query",
        "webResults.$": "$.webResults",
        "fileResults.$": "$.fileResults",
        "codeAnalysis.$": "$.codeAnalysis",
        "completedAt.$": "$$.State.EnteredTime"
      },
      "End": true
    }
  }
}
```

## Step 4: Run the Workflow

Create `run.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use MyOrg\SecureResearch\ResearcherAgent;
use MyOrg\SecureResearch\FileProcessorAgent;
use MyOrg\SecureResearch\CodeAnalyzerAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('ResearcherAgent', new ResearcherAgent());
$registry->register('FileProcessorAgent', new FileProcessorAgent());
$registry->register('CodeAnalyzerAgent', new CodeAnalyzerAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('secure-research.asl.json', $registry);

// Run research without code analysis
$result1 = $engine->run([
    'query' => 'PHP best practices 2024',
    'searchPath' => './docs/php-guide.md',
    'analyzeCode' => false
]);

if ($result1->isSuccess()) {
    echo "Research completed!\n";
    echo "Web sources searched: " . count($result1->getOutput()['webResults']['results']['web'] ?? []) . "\n";
    echo "File search: " . ($result1->getOutput()['fileResults']['success'] ? 'Success' : 'Failed') . "\n";
    echo "---\n";
}

// Run with code analysis (sandboxed)
$result2 = $engine->run([
    'query' => 'Analyze this code pattern',
    'searchPath' => './data/example.php',
    'analyzeCode' => true,
    'codeSnippet' => '<?php echo "Hello World";',
    'language' => 'php'
]);

if ($result2->isSuccess()) {
    echo "Research with code analysis completed!\n";
    $codeResult = $result2->getOutput()['codeAnalysis'] ?? [];
    echo "Code analyzed: " . ($codeResult['analyzed'] ? 'Yes' : 'No') . "\n";
    echo "Sandbox used: " . ($codeResult['sandboxUsed'] ? 'Yes' : 'No') . "\n";
}

// Attempt to access denied path (will fail)
$result3 = $engine->run([
    'query' => 'Secret data',
    'searchPath' => './secrets/api-keys.txt',
    'analyzeCode' => false
]);

if ($result3->isSuccess()) {
    $fileResult = $result3->getOutput()['fileResults'];
    echo "---\nAccess to secrets/: " . ($fileResult['success'] ? 'Allowed (BAD!)' : 'Denied (GOOD!)') . "\n";
    if (!$fileResult['success']) {
        echo "Error: " . $fileResult['error'] . "\n";
    }
}
```

## Expected Output

```
Research completed!
Web sources searched: 2
File search: Success
---
Research with code analysis completed!
Code analyzed: Yes
Sandbox used: Yes
---
Access to secrets/: Denied (GOOD!)
Error: Access denied to path: ./secrets/api-keys.txt
```

## Tool Configuration Reference

### Allowlist (Recommended)

Explicitly list permitted tools:

```json
{
  "Tools": {
    "Allowed": ["web_search", "read_file"]
  }
}
```

### Denylist

Block specific dangerous tools:

```json
{
  "Tools": {
    "Denied": ["execute_shell", "delete_file", "write_file"]
  }
}
```

### Combined Approach

Use both for defense in depth:

```json
{
  "Tools": {
    "Allowed": ["read_file", "grep", "web_search"],
    "Denied": ["execute_shell"]
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
        "MaxPerHour": 100,
        "MaxConcurrent": 3
      }
    }
  }
}
```

| Option | Description |
|--------|-------------|
| `MaxPerMinute` | Maximum calls per minute |
| `MaxPerHour` | Maximum calls per hour |
| `MaxConcurrent` | Maximum simultaneous calls |

### Sandbox Configuration

For code execution:

```json
{
  "Tools": {
    "Sandboxed": true,
    "Sandbox": {
      "Environment": "docker",
      "Image": "python:3.11-slim",
      "Timeout": "30s",
      "Memory": "256M",
      "CPU": "0.5",
      "Network": false,
      "ReadOnlyFS": true
    }
  }
}
```

| Option | Description |
|--------|-------------|
| `Environment` | `docker`, `wasm`, or `process` |
| `Timeout` | Maximum execution time |
| `Memory` | Memory limit |
| `Network` | Allow network access |
| `ReadOnlyFS` | Prevent file writes |

### File System Restrictions

```json
{
  "Tools": {
    "FileSystem": {
      "AllowedPaths": ["./data/**", "./output/**"],
      "DeniedPaths": ["./.env", "./secrets/**", "**/.git/**"],
      "MaxFileSize": "10M",
      "AllowedExtensions": [".txt", ".json", ".csv"]
    }
  }
}
```

## Experiment

Try these modifications:

### Add Network Restrictions

```json
{
  "Tools": {
    "Network": {
      "AllowedDomains": ["api.example.com", "*.trusted.org"],
      "DeniedDomains": ["*.malicious.com"],
      "MaxRequestsPerMinute": 30
    }
  }
}
```

### Tool Usage Logging

Enable audit logging for tool usage:

```json
{
  "Tools": {
    "Allowed": ["web_search"],
    "Audit": {
      "Enabled": true,
      "LogLevel": "detailed",
      "IncludeParameters": true
    }
  }
}
```

## Common Mistakes

### Empty Allowlist Allows Everything

```json
{
  "Tools": {
    "Allowed": []
  }
}
```

**Problem**: An empty allowlist means no restrictions.

**Fix**: Always specify tools explicitly or use denylists.

### Forgetting Nested Paths

```json
{
  "FileSystem": {
    "DeniedPaths": ["./.env"]
  }
}
```

**Problem**: Doesn't block `./.env.local` or `./.env.production`.

**Fix**: Use wildcards: `"./.env*"` or `"./.env**"`.

### Rate Limit Too Aggressive

```json
{
  "RateLimits": {
    "web_search": { "MaxPerMinute": 1 }
  }
}
```

**Problem**: Workflow fails due to rate limiting.

**Fix**: Set realistic limits based on expected usage.

### Missing Sandbox for Code Execution

```json
{
  "Tools": {
    "Allowed": ["execute_code"]
  }
}
```

**Problem**: Code runs unsandboxed - security risk!

**Fix**: Always enable sandboxing for code execution.

## Security Best Practices

1. **Principle of Least Privilege**: Only allow tools the agent actually needs
2. **Defense in Depth**: Use both allowlists and denylists
3. **Always Sandbox Code**: Never execute untrusted code outside a sandbox
4. **Rate Limit External Calls**: Prevent abuse and control costs
5. **Audit Tool Usage**: Log all tool invocations for review
6. **Restrict File Paths**: Use explicit allowlists for file access

## Summary

You've learned:

- ✅ Tool allowlists and denylists for access control
- ✅ Rate limiting to prevent abuse
- ✅ Sandboxing for safe code execution
- ✅ File system and network restrictions
- ✅ Building secure, production-ready workflows
- ✅ Security best practices

## Next Steps

- [Tutorial 8: Human Approval](08-human-approval.md) - Add approval gates
- [Tutorial 9: Multi-Agent Debate](09-multi-agent-debate.md) - Agent collaboration
