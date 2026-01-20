# Tutorial 7: Tool Orchestration

Learn how to control tool access and permissions for agents.

## What You'll Learn

- Tool allowlists and denylists
- Rate limiting
- Sandboxing
- File system and network restrictions

## Tools Block

### Basic Allowlist

```json
{
  "ResearchTask": {
    "Type": "Task",
    "Agent": "Researcher",
    "Tools": {
      "Allowed": ["web_search", "fetch_url", "read_file"]
    }
  }
}
```

### With Denylists

```json
{
  "SafeAnalysis": {
    "Type": "Task",
    "Agent": "Analyzer",
    "Tools": {
      "Allowed": ["read_file", "grep"],
      "Denied": ["write_file", "execute_shell", "delete_file"]
    }
  }
}
```

### Rate Limits

```json
{
  "RateLimitedSearch": {
    "Type": "Task",
    "Agent": "SearchAgent",
    "Tools": {
      "Allowed": ["web_search"],
      "RateLimits": {
        "web_search": {
          "MaxPerMinute": 10,
          "MaxPerHour": 100
        }
      }
    }
  }
}
```

### Sandboxing

```json
{
  "SandboxedExecution": {
    "Type": "Task",
    "Agent": "CodeRunner",
    "Tools": {
      "Allowed": ["execute_code"],
      "Sandboxed": true,
      "Sandbox": {
        "Environment": "docker",
        "Timeout": "30s",
        "Memory": "256M",
        "Network": false
      }
    }
  }
}
```

### File System Restrictions

```json
{
  "RestrictedFileAccess": {
    "Type": "Task",
    "Agent": "FileProcessor",
    "Tools": {
      "Allowed": ["read_file", "write_file"],
      "FileSystem": {
        "AllowedPaths": ["./data/**", "./output/**"],
        "DeniedPaths": ["./.env", "./secrets/**"],
        "MaxFileSize": "10M"
      }
    }
  }
}
```

## Summary

You've learned:

- ✅ Tool allowlists and denylists
- ✅ Rate limiting tool usage
- ✅ Sandboxing for security
- ✅ File and network restrictions
