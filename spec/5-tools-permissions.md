# 5. Tools and Permissions

This section covers how ASL manages tool access and permissions for agents.

## Overview

Tools are capabilities that agents can invoke during execution, such as:

- Web search
- File operations
- API calls
- Code execution
- Database queries

ASL provides fine-grained control over which tools agents can use.

## Tools Block

The Tools block configures tool access for a Task state.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `Allowed` | array | Tools the agent can use |
| `Denied` | array | Tools explicitly forbidden |
| `RateLimits` | object | Per-tool rate limits |
| `Sandboxed` | boolean | Run in sandbox environment |
| `Timeout` | string | Default tool timeout |

### Basic Usage

```json
{
  "ResearchTask": {
    "Type": "Task",
    "Agent": "Researcher",
    "Tools": {
      "Allowed": ["web_search", "fetch_webpage", "read_file"],
      "Denied": ["write_file", "execute_shell"]
    },
    "Next": "Process"
  }
}
```

## Permission Models

### Allowlist Model

Only explicitly allowed tools are available:

```json
{
  "Tools": {
    "Allowed": ["web_search", "calculator"]
  }
}
```

### Denylist Model

All tools available except denied ones:

```json
{
  "Tools": {
    "Denied": ["execute_shell", "delete_file", "send_email"]
  }
}
```

### Combined Model

Allowlist with explicit denials:

```json
{
  "Tools": {
    "Allowed": ["file_operations", "web_access"],
    "Denied": ["delete_file", "write_to_system"]
  }
}
```

## Rate Limits

Control how frequently tools can be used.

### Per-Tool Limits

```json
{
  "Tools": {
    "Allowed": ["web_search", "api_call"],
    "RateLimits": {
      "web_search": {
        "MaxPerMinute": 10,
        "MaxPerHour": 100
      },
      "api_call": {
        "MaxConcurrent": 3,
        "MaxPerMinute": 30
      }
    }
  }
}
```

### Global Limits

```json
{
  "Tools": {
    "Allowed": ["*"],
    "RateLimits": {
      "*": {
        "MaxPerMinute": 60,
        "MaxConcurrent": 5
      }
    }
  }
}
```

### Rate Limit Fields

| Field | Type | Description |
|-------|------|-------------|
| `MaxPerMinute` | integer | Maximum calls per minute |
| `MaxPerHour` | integer | Maximum calls per hour |
| `MaxPerDay` | integer | Maximum calls per day |
| `MaxConcurrent` | integer | Maximum concurrent calls |
| `BurstLimit` | integer | Allowed burst size |

### Rate Limit Exceeded Behavior

```json
{
  "Tools": {
    "RateLimits": {
      "web_search": {
        "MaxPerMinute": 10,
        "OnExceed": "wait"
      }
    }
  }
}
```

| OnExceed | Description |
|----------|-------------|
| `fail` | Throw RateLimitExceeded error |
| `wait` | Wait until limit resets |
| `queue` | Queue for later execution |

## Sandboxing

Isolate tool execution for safety:

```json
{
  "Tools": {
    "Allowed": ["execute_code"],
    "Sandboxed": true,
    "Sandbox": {
      "Environment": "docker",
      "Image": "python:3.11-slim",
      "Timeout": "30s",
      "Memory": "256M",
      "Network": false,
      "Mounts": {
        "/data": {
          "Source": "./sandbox_data",
          "ReadOnly": true
        }
      }
    }
  }
}
```

### Sandbox Options

| Field | Type | Description |
|-------|------|-------------|
| `Environment` | string | Sandbox type |
| `Timeout` | string | Execution timeout |
| `Memory` | string | Memory limit |
| `CPU` | string | CPU limit |
| `Network` | boolean | Network access |
| `Mounts` | object | File mounts |

### Sandbox Environments

| Environment | Description |
|-------------|-------------|
| `docker` | Docker container isolation |
| `wasm` | WebAssembly sandbox |
| `vm` | Virtual machine |
| `process` | Separate process |

## Tool Categories

Define tool categories for easier management:

```json
{
  "Comment": "Workflow with tool categories",
  "ToolCategories": {
    "read_only": ["read_file", "list_dir", "grep", "web_search"],
    "write": ["write_file", "create_dir", "delete_file"],
    "network": ["web_search", "fetch_url", "api_call"],
    "dangerous": ["execute_shell", "sudo", "docker"]
  },
  "States": {
    "SafeAnalysis": {
      "Type": "Task",
      "Agent": "Analyzer",
      "Tools": {
        "AllowedCategories": ["read_only"],
        "Denied": ["dangerous"]
      }
    }
  }
}
```

## Tool Parameters

Configure default parameters for tools:

```json
{
  "Tools": {
    "Allowed": ["web_search", "fetch_url"],
    "Parameters": {
      "web_search": {
        "MaxResults": 10,
        "SafeSearch": true
      },
      "fetch_url": {
        "Timeout": "10s",
        "MaxSize": "1M",
        "AllowedDomains": ["docs.example.com", "api.example.com"]
      }
    }
  }
}
```

## Tool Validation

Validate tool inputs and outputs:

```json
{
  "Tools": {
    "Allowed": ["api_call"],
    "Validation": {
      "api_call": {
        "Input": {
          "Schema": {
            "type": "object",
            "required": ["endpoint"],
            "properties": {
              "endpoint": { "type": "string", "pattern": "^https://" }
            }
          }
        },
        "Output": {
          "MaxSize": "1M",
          "Sanitize": true
        }
      }
    }
  }
}
```

## Domain Restrictions

Limit network tools to specific domains:

```json
{
  "Tools": {
    "Allowed": ["fetch_url", "api_call"],
    "Domains": {
      "Allowed": [
        "api.example.com",
        "docs.example.com",
        "*.github.com"
      ],
      "Denied": [
        "*.internal.corp",
        "localhost",
        "127.0.0.1"
      ]
    }
  }
}
```

## File System Restrictions

Control file system access:

```json
{
  "Tools": {
    "Allowed": ["read_file", "write_file"],
    "FileSystem": {
      "AllowedPaths": [
        "./data/**",
        "./output/**"
      ],
      "DeniedPaths": [
        "./.env",
        "./.git/**",
        "./secrets/**"
      ],
      "AllowedExtensions": [".txt", ".json", ".md"],
      "MaxFileSize": "10M"
    }
  }
}
```

## Workflow-Level Tool Configuration

Set default tool configuration for the entire workflow:

```json
{
  "Comment": "Workflow with global tool config",
  "DefaultTools": {
    "Denied": ["execute_shell", "sudo"],
    "RateLimits": {
      "*": { "MaxPerMinute": 100 }
    },
    "Timeout": "30s"
  },
  "States": {
    "Step1": {
      "Type": "Task",
      "Agent": "Agent1",
      "Tools": {
        "Allowed": ["web_search"]
      }
    }
  }
}
```

## Tool Execution Hooks

Execute custom logic before/after tool calls:

```json
{
  "Tools": {
    "Allowed": ["api_call"],
    "Hooks": {
      "Before": {
        "Agent": "RequestValidator",
        "Parameters": {
          "request.$": "$.toolRequest"
        }
      },
      "After": {
        "Agent": "ResponseLogger",
        "Parameters": {
          "response.$": "$.toolResponse"
        }
      }
    }
  }
}
```

## Complete Example

```json
{
  "SecureCodeAnalysis": {
    "Type": "Task",
    "Comment": "Analyze code with restricted tool access",
    "Agent": "CodeAnalyzer",
    "Tools": {
      "Allowed": ["read_file", "grep", "ast_parse"],
      "Denied": ["write_file", "execute_shell", "network"],
      "RateLimits": {
        "read_file": {
          "MaxPerMinute": 50,
          "MaxConcurrent": 5
        }
      },
      "FileSystem": {
        "AllowedPaths": ["./src/**", "./tests/**"],
        "DeniedPaths": ["./.env", "./secrets/**"],
        "MaxFileSize": "5M"
      },
      "Sandboxed": true,
      "Sandbox": {
        "Environment": "process",
        "Timeout": "60s",
        "Network": false
      },
      "Timeout": "30s",
      "Validation": {
        "read_file": {
          "Input": {
            "Schema": {
              "type": "object",
              "required": ["path"],
              "properties": {
                "path": { "type": "string" }
              }
            }
          }
        }
      }
    },
    "Parameters": {
      "codebase.$": "$.projectPath"
    },
    "ResultPath": "$.analysis",
    "Next": "ReviewFindings"
  }
}
```

## Best Practices

### 1. Prefer Allowlists

```json
{
  "Tools": {
    "Allowed": ["specific_tool_1", "specific_tool_2"]
  }
}
```

### 2. Always Deny Dangerous Tools

```json
{
  "Tools": {
    "Allowed": ["read_file"],
    "Denied": ["execute_shell", "sudo", "rm"]
  }
}
```

### 3. Set Reasonable Rate Limits

```json
{
  "Tools": {
    "RateLimits": {
      "web_search": { "MaxPerMinute": 10 },
      "api_call": { "MaxPerMinute": 30 }
    }
  }
}
```

### 4. Use Sandboxing for Untrusted Operations

```json
{
  "Tools": {
    "Allowed": ["execute_code"],
    "Sandboxed": true
  }
}
```

### 5. Restrict File System Access

```json
{
  "Tools": {
    "FileSystem": {
      "AllowedPaths": ["./workspace/**"],
      "DeniedPaths": ["../**", "/etc/**"]
    }
  }
}
```
