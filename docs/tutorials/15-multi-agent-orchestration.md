# Tutorial 15: Multi-Agent Orchestration

Learn how to orchestrate multiple specialized claude-php-agents in parallel and sequential ASL workflows for complex AI pipelines.

## What You'll Learn

- Registering and configuring multiple specialized agents
- Parallel branch execution with different agents
- Agent-to-agent data flow through ASL states
- Building a code review pipeline with specialized reviewers
- Aggregating and synthesizing results from multiple agents

## Prerequisites

- Completed [Tutorial 14: Tool-Enabled Agent Workflows](14-tool-enabled-agent-workflows.md)
- Understanding of ASL Parallel states

## The Scenario

We'll build a comprehensive code review pipeline with:

1. **Security Analyzer** - Scans for vulnerabilities
2. **Performance Reviewer** - Identifies optimization opportunities
3. **Style Checker** - Ensures code standards compliance
4. **Documentation Auditor** - Checks documentation quality
5. **Aggregator** - Synthesizes all reviews into a final report

## Step 1: Understanding Multi-Agent Architecture

Multi-agent systems excel when:

| Pattern | Use Case | Example |
|---------|----------|---------|
| **Parallel Experts** | Independent analysis tasks | Multiple reviewers analyzing code |
| **Sequential Pipeline** | Dependencies between stages | Extract → Transform → Load |
| **Hierarchical** | Coordinator delegates to workers | Manager assigns tasks to specialists |
| **Debate/Consensus** | Conflicting opinions need resolution | Pro/Con analysis with judge |

## Step 2: Create Specialized Agent Adapters

Create `src/Adapters/SpecializedAgentFactory.php`:

```php
<?php

namespace MyOrg\Adapters;

use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating specialized code review agents.
 */
class SpecializedAgentFactory
{
    private ClaudePhp $client;
    private LoggerInterface $logger;
    private string $defaultModel = 'claude-sonnet-4-20250514';

    public function __construct(ClaudePhp $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create a security analyzer agent.
     */
    public function securityAnalyzer(): ClaudeAgentAdapter
    {
        $agent = Agent::create($this->client)
            ->withName('SecurityAnalyzer')
            ->withModel($this->defaultModel)
            ->withSystemPrompt($this->getSecurityPrompt())
            ->withLogger($this->logger)
            ->maxTokens(2000)
            ->temperature(0.2); // Low temperature for consistent analysis

        return new ClaudeAgentAdapter('SecurityAnalyzer', $agent);
    }

    /**
     * Create a performance reviewer agent.
     */
    public function performanceReviewer(): ClaudeAgentAdapter
    {
        $agent = Agent::create($this->client)
            ->withName('PerformanceReviewer')
            ->withModel($this->defaultModel)
            ->withSystemPrompt($this->getPerformancePrompt())
            ->withLogger($this->logger)
            ->maxTokens(2000)
            ->temperature(0.3);

        return new ClaudeAgentAdapter('PerformanceReviewer', $agent);
    }

    /**
     * Create a style checker agent.
     */
    public function styleChecker(): ClaudeAgentAdapter
    {
        $agent = Agent::create($this->client)
            ->withName('StyleChecker')
            ->withModel('claude-3-5-haiku-latest') // Faster model for style checks
            ->withSystemPrompt($this->getStylePrompt())
            ->withLogger($this->logger)
            ->maxTokens(1500)
            ->temperature(0.1);

        return new ClaudeAgentAdapter('StyleChecker', $agent);
    }

    /**
     * Create a documentation auditor agent.
     */
    public function documentationAuditor(): ClaudeAgentAdapter
    {
        $agent = Agent::create($this->client)
            ->withName('DocumentationAuditor')
            ->withModel('claude-3-5-haiku-latest')
            ->withSystemPrompt($this->getDocumentationPrompt())
            ->withLogger($this->logger)
            ->maxTokens(1500)
            ->temperature(0.2);

        return new ClaudeAgentAdapter('DocumentationAuditor', $agent);
    }

    /**
     * Create a review aggregator agent.
     */
    public function reviewAggregator(): ClaudeAgentAdapter
    {
        $agent = Agent::create($this->client)
            ->withName('ReviewAggregator')
            ->withModel($this->defaultModel)
            ->withSystemPrompt($this->getAggregatorPrompt())
            ->withLogger($this->logger)
            ->maxTokens(3000)
            ->temperature(0.4);

        return new ClaudeAgentAdapter('ReviewAggregator', $agent);
    }

    /**
     * Create all review agents.
     *
     * @return array<string, ClaudeAgentAdapter>
     */
    public function createAllReviewers(): array
    {
        return [
            'SecurityAnalyzer' => $this->securityAnalyzer(),
            'PerformanceReviewer' => $this->performanceReviewer(),
            'StyleChecker' => $this->styleChecker(),
            'DocumentationAuditor' => $this->documentationAuditor(),
            'ReviewAggregator' => $this->reviewAggregator(),
        ];
    }

    private function getSecurityPrompt(): string
    {
        return <<<PROMPT
You are a security-focused code reviewer. Analyze code for:

1. **Injection Vulnerabilities**: SQL injection, command injection, XSS
2. **Authentication Issues**: Weak auth, session management, token handling
3. **Data Exposure**: Sensitive data in logs, hardcoded secrets, insecure storage
4. **Input Validation**: Missing sanitization, type coercion issues
5. **Access Control**: Broken authorization, privilege escalation

For each issue found, provide:
- Severity: CRITICAL, HIGH, MEDIUM, LOW
- Location: File and line number if possible
- Description: What the vulnerability is
- Recommendation: How to fix it

Respond in JSON format:
{
  "issues": [...],
  "overallRisk": "LOW|MEDIUM|HIGH|CRITICAL",
  "summary": "Brief summary",
  "recommendations": [...]
}
PROMPT;
    }

    private function getPerformancePrompt(): string
    {
        return <<<PROMPT
You are a performance optimization expert. Analyze code for:

1. **Algorithm Complexity**: O(n²) or worse operations, inefficient loops
2. **Memory Usage**: Memory leaks, excessive allocations, large objects
3. **Database Queries**: N+1 queries, missing indexes, large result sets
4. **Caching Opportunities**: Repeated computations, cache-able data
5. **Async Optimization**: Blocking operations, parallelization opportunities

For each issue, provide:
- Impact: HIGH, MEDIUM, LOW
- Category: Algorithm, Memory, Database, Caching, Async
- Description: The performance issue
- Suggestion: How to optimize

Respond in JSON format:
{
  "issues": [...],
  "estimatedImpact": "Potential improvement description",
  "summary": "Brief summary",
  "quickWins": [...]
}
PROMPT;
    }

    private function getStylePrompt(): string
    {
        return <<<PROMPT
You are a code style and standards reviewer. Check for:

1. **PSR-12 Compliance**: PHP coding standards
2. **Naming Conventions**: Classes, methods, variables
3. **Code Organization**: File structure, namespace usage
4. **Consistency**: Formatting, spacing, bracing style
5. **Best Practices**: SOLID principles, design patterns

For each issue:
- Severity: ERROR, WARNING, INFO
- Rule: The style rule violated
- Location: Where the issue occurs
- Fix: How to correct it

Respond in JSON format:
{
  "issues": [...],
  "complianceScore": 0-100,
  "summary": "Brief summary"
}
PROMPT;
    }

    private function getDocumentationPrompt(): string
    {
        return <<<PROMPT
You are a documentation quality auditor. Evaluate:

1. **DocBlocks**: Class and method documentation
2. **Parameter Descriptions**: @param tags completeness
3. **Return Types**: @return documentation
4. **Inline Comments**: Code explanation quality
5. **README/Usage**: User-facing documentation

For each issue:
- Type: MISSING, INCOMPLETE, OUTDATED, UNCLEAR
- Location: Where documentation is needed
- Suggestion: What should be documented

Respond in JSON format:
{
  "issues": [...],
  "documentationScore": 0-100,
  "coverage": "Percentage of documented code",
  "summary": "Brief summary"
}
PROMPT;
    }

    private function getAggregatorPrompt(): string
    {
        return <<<PROMPT
You are a senior code review coordinator. Synthesize multiple review reports into a comprehensive summary.

Your task:
1. **Prioritize Issues**: Rank all issues by importance
2. **Identify Patterns**: Find recurring themes across reviews
3. **Provide Actionable Summary**: Clear next steps for developers
4. **Calculate Overall Score**: Weighted assessment

Consider:
- Security issues are highest priority
- Performance issues affect user experience
- Style issues ensure maintainability
- Documentation enables collaboration

Respond in JSON format:
{
  "overallScore": 0-100,
  "grade": "A|B|C|D|F",
  "criticalIssues": [...],
  "topRecommendations": [...],
  "summary": "Executive summary",
  "detailedFindings": {
    "security": {...},
    "performance": {...},
    "style": {...},
    "documentation": {...}
  }
}
PROMPT;
    }
}
```

## Step 3: Define the Parallel Review Workflow

Create `workflows/code-review-pipeline.asl.json`:

```json
{
  "Comment": "Multi-agent code review pipeline with parallel execution",
  "Version": "1.0",
  "StartAt": "PrepareCode",
  "Budget": {
    "MaxCost": "$2.00",
    "OnExceed": "PauseAndNotify"
  },
  "States": {
    "PrepareCode": {
      "Type": "Pass",
      "Parameters": {
        "code.$": "$.code",
        "filename.$": "$.filename",
        "language.$": "$.language",
        "reviewId": "review_${$$.Execution.StartTime}"
      },
      "ResultPath": "$.prepared",
      "Next": "ParallelReview"
    },
    "ParallelReview": {
      "Type": "Parallel",
      "Comment": "Run all reviewers in parallel for speed",
      "Branches": [
        {
          "StartAt": "SecurityReview",
          "States": {
            "SecurityReview": {
              "Type": "Task",
              "Agent": "SecurityAnalyzer",
              "Parameters": {
                "prompt.$": "States.Format('Analyze this {} code for security vulnerabilities:\n\nFilename: {}\n\n```{}\n{}```', $.prepared.language, $.prepared.filename, $.prepared.language, $.prepared.code)"
              },
              "ResultPath": "$",
              "End": true
            }
          }
        },
        {
          "StartAt": "PerformanceReview",
          "States": {
            "PerformanceReview": {
              "Type": "Task",
              "Agent": "PerformanceReviewer",
              "Parameters": {
                "prompt.$": "States.Format('Analyze this {} code for performance issues:\n\nFilename: {}\n\n```{}\n{}```', $.prepared.language, $.prepared.filename, $.prepared.language, $.prepared.code)"
              },
              "ResultPath": "$",
              "End": true
            }
          }
        },
        {
          "StartAt": "StyleReview",
          "States": {
            "StyleReview": {
              "Type": "Task",
              "Agent": "StyleChecker",
              "Parameters": {
                "prompt.$": "States.Format('Check this {} code for style and standards compliance:\n\nFilename: {}\n\n```{}\n{}```', $.prepared.language, $.prepared.filename, $.prepared.language, $.prepared.code)"
              },
              "ResultPath": "$",
              "End": true
            }
          }
        },
        {
          "StartAt": "DocumentationReview",
          "States": {
            "DocumentationReview": {
              "Type": "Task",
              "Agent": "DocumentationAuditor",
              "Parameters": {
                "prompt.$": "States.Format('Audit documentation quality for this {} code:\n\nFilename: {}\n\n```{}\n{}```', $.prepared.language, $.prepared.filename, $.prepared.language, $.prepared.code)"
              },
              "ResultPath": "$",
              "End": true
            }
          }
        }
      ],
      "ResultPath": "$.reviews",
      "Next": "AggregateReviews"
    },
    "AggregateReviews": {
      "Type": "Task",
      "Agent": "ReviewAggregator",
      "Parameters": {
        "prompt.$": "States.Format('Synthesize these code review reports into a comprehensive summary:\n\nSecurity Review:\n{}\n\nPerformance Review:\n{}\n\nStyle Review:\n{}\n\nDocumentation Review:\n{}\n\nProvide a prioritized summary with overall score and recommendations.', $.reviews[0].response, $.reviews[1].response, $.reviews[2].response, $.reviews[3].response)"
      },
      "ResultPath": "$.aggregated",
      "Next": "DetermineOutcome"
    },
    "DetermineOutcome": {
      "Type": "Choice",
      "Choices": [
        {
          "And": [
            {
              "Variable": "$.aggregated.parsed.overallScore",
              "NumericGreaterThanEquals": 80
            },
            {
              "Variable": "$.aggregated.parsed.criticalIssues",
              "IsPresent": true
            }
          ],
          "Next": "ApproveWithNotes"
        },
        {
          "Variable": "$.aggregated.parsed.overallScore",
          "NumericLessThan": 50,
          "Next": "RequireChanges"
        }
      ],
      "Default": "RequestMinorChanges"
    },
    "ApproveWithNotes": {
      "Type": "Pass",
      "Parameters": {
        "decision": "APPROVED",
        "notes": "Code passes review with minor suggestions",
        "score.$": "$.aggregated.parsed.overallScore",
        "grade.$": "$.aggregated.parsed.grade"
      },
      "ResultPath": "$.decision",
      "Next": "FinalReport"
    },
    "RequestMinorChanges": {
      "Type": "Pass",
      "Parameters": {
        "decision": "CHANGES_REQUESTED",
        "notes": "Minor improvements needed before approval",
        "score.$": "$.aggregated.parsed.overallScore",
        "grade.$": "$.aggregated.parsed.grade"
      },
      "ResultPath": "$.decision",
      "Next": "FinalReport"
    },
    "RequireChanges": {
      "Type": "Pass",
      "Parameters": {
        "decision": "BLOCKED",
        "notes": "Significant issues must be addressed",
        "score.$": "$.aggregated.parsed.overallScore",
        "grade.$": "$.aggregated.parsed.grade"
      },
      "ResultPath": "$.decision",
      "Next": "FinalReport"
    },
    "FinalReport": {
      "Type": "Pass",
      "Parameters": {
        "reviewId.$": "$.prepared.reviewId",
        "filename.$": "$.prepared.filename",
        "decision.$": "$.decision",
        "summary.$": "$.aggregated.parsed.summary",
        "recommendations.$": "$.aggregated.parsed.topRecommendations",
        "details": {
          "security.$": "$.reviews[0].parsed",
          "performance.$": "$.reviews[1].parsed",
          "style.$": "$.reviews[2].parsed",
          "documentation.$": "$.reviews[3].parsed"
        },
        "metrics": {
          "totalTokens.$": "States.MathAdd($.reviews[0]._tokens, $.reviews[1]._tokens, $.reviews[2]._tokens, $.reviews[3]._tokens, $.aggregated._tokens)",
          "totalCost.$": "States.MathAdd($.reviews[0]._cost, $.reviews[1]._cost, $.reviews[2]._cost, $.reviews[3]._cost, $.aggregated._cost)",
          "parallelBranches": 4
        }
      },
      "End": true
    }
  }
}
```

## Step 4: Run the Pipeline

Create `run-code-review.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use ClaudePhp\ClaudePhp;
use MyOrg\Adapters\SpecializedAgentFactory;

// Initialize
$client = ClaudePhp::make(getenv('ANTHROPIC_API_KEY'));
$factory = new SpecializedAgentFactory($client);

// Create all reviewer agents
$reviewers = $factory->createAllReviewers();

// Register with ASL
$registry = new AgentRegistry();
foreach ($reviewers as $name => $agent) {
    $registry->register($name, $agent);
}

// Load workflow
$engine = WorkflowEngine::fromFile('workflows/code-review-pipeline.asl.json', $registry);

// Sample code to review
$codeToReview = <<<'PHP'
<?php

class UserController
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    public function getUser($id)
    {
        // Get user by ID
        $query = "SELECT * FROM users WHERE id = " . $id;
        $result = $this->db->query($query);
        return $result->fetch();
    }
    
    public function updateEmail($userId, $email)
    {
        $query = "UPDATE users SET email = '$email' WHERE id = $userId";
        $this->db->query($query);
        return true;
    }
    
    public function getAllUsers()
    {
        $users = [];
        $query = "SELECT * FROM users";
        $result = $this->db->query($query);
        
        while ($row = $result->fetch()) {
            $users[] = $row;
            // Process each user
            $this->processUser($row);
        }
        
        return $users;
    }
    
    private function processUser($user)
    {
        // Log user access
        file_put_contents('/tmp/access.log', $user['password'] . "\n", FILE_APPEND);
    }
}
PHP;

// Run review
$result = $engine->run([
    'code' => $codeToReview,
    'filename' => 'src/Controllers/UserController.php',
    'language' => 'php',
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║              CODE REVIEW REPORT                              ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Review ID: {$output['reviewId']}\n";
    echo "File: {$output['filename']}\n";
    echo "Decision: {$output['decision']['decision']}\n";
    echo "Score: {$output['decision']['score']}/100 (Grade: {$output['decision']['grade']})\n\n";
    
    echo "─── SUMMARY ───\n";
    echo $output['summary'] . "\n\n";
    
    echo "─── TOP RECOMMENDATIONS ───\n";
    foreach ($output['recommendations'] ?? [] as $i => $rec) {
        echo ($i + 1) . ". {$rec}\n";
    }
    
    echo "\n─── DETAILED FINDINGS ───\n";
    
    // Security
    $security = $output['details']['security'] ?? [];
    echo "\n🔒 Security: Risk Level - " . ($security['overallRisk'] ?? 'N/A') . "\n";
    foreach (array_slice($security['issues'] ?? [], 0, 3) as $issue) {
        echo "   [{$issue['severity']}] {$issue['description']}\n";
    }
    
    // Performance
    $perf = $output['details']['performance'] ?? [];
    echo "\n⚡ Performance: " . ($perf['estimatedImpact'] ?? 'N/A') . "\n";
    foreach (array_slice($perf['issues'] ?? [], 0, 3) as $issue) {
        echo "   [{$issue['impact']}] {$issue['description']}\n";
    }
    
    // Style
    $style = $output['details']['style'] ?? [];
    echo "\n📝 Style: Compliance Score - " . ($style['complianceScore'] ?? 'N/A') . "/100\n";
    
    // Documentation
    $docs = $output['details']['documentation'] ?? [];
    echo "\n📚 Documentation: Score - " . ($docs['documentationScore'] ?? 'N/A') . "/100\n";
    
    echo "\n─── METRICS ───\n";
    echo "Total Tokens: {$output['metrics']['totalTokens']}\n";
    echo "Total Cost: $" . number_format($output['metrics']['totalCost'], 4) . "\n";
    echo "Parallel Branches: {$output['metrics']['parallelBranches']}\n";
    echo "Duration: " . number_format($result->getDuration(), 2) . "s\n";
} else {
    echo "Review failed: " . $result->getError() . "\n";
    echo "Cause: " . $result->getErrorCause() . "\n";
}
```

## Expected Output

```
╔══════════════════════════════════════════════════════════════╗
║              CODE REVIEW REPORT                              ║
╚══════════════════════════════════════════════════════════════╝

Review ID: review_2026-01-20T10:30:00Z
File: src/Controllers/UserController.php
Decision: BLOCKED
Score: 35/100 (Grade: F)

─── SUMMARY ───
This code contains critical security vulnerabilities that must be addressed 
before deployment. SQL injection risks and password logging are severe issues.

─── TOP RECOMMENDATIONS ───
1. Use parameterized queries to prevent SQL injection
2. Remove password logging immediately
3. Add input validation for all user inputs
4. Implement proper error handling
5. Add documentation for all public methods

─── DETAILED FINDINGS ───

🔒 Security: Risk Level - CRITICAL
   [CRITICAL] SQL Injection in getUser(): Direct concatenation of user input
   [CRITICAL] Password logged to file in processUser()
   [HIGH] SQL Injection in updateEmail(): Unsanitized email parameter

⚡ Performance: Potential for significant improvement with caching
   [HIGH] N+1 query pattern in getAllUsers() - processes each user individually
   [MEDIUM] No pagination for getAllUsers() may cause memory issues
   [LOW] Consider connection pooling for database

📝 Style: Compliance Score - 62/100

📚 Documentation: Score - 25/100

─── METRICS ───
Total Tokens: 6847
Total Cost: $0.1523
Parallel Branches: 4
Duration: 8.45s
```

## Multi-Agent Patterns

### Pattern 1: Fan-Out/Fan-In

```json
{
  "FanOut": {
    "Type": "Parallel",
    "Branches": [
      { "StartAt": "Agent1", "States": {...} },
      { "StartAt": "Agent2", "States": {...} },
      { "StartAt": "Agent3", "States": {...} }
    ],
    "ResultPath": "$.parallelResults",
    "Next": "FanIn"
  },
  "FanIn": {
    "Type": "Task",
    "Agent": "Aggregator",
    "Parameters": {
      "results.$": "$.parallelResults"
    }
  }
}
```

### Pattern 2: Sequential Pipeline

```json
{
  "Extract": {
    "Agent": "Extractor",
    "Next": "Transform"
  },
  "Transform": {
    "Agent": "Transformer", 
    "Next": "Load"
  },
  "Load": {
    "Agent": "Loader",
    "End": true
  }
}
```

### Pattern 3: Conditional Routing

```json
{
  "Classify": {
    "Type": "Task",
    "Agent": "Classifier",
    "Next": "RouteByType"
  },
  "RouteByType": {
    "Type": "Choice",
    "Choices": [
      { "Variable": "$.type", "StringEquals": "bug", "Next": "BugAgent" },
      { "Variable": "$.type", "StringEquals": "feature", "Next": "FeatureAgent" }
    ],
    "Default": "GeneralAgent"
  }
}
```

### Pattern 4: Iterative Refinement

```json
{
  "InitialDraft": {
    "Agent": "Writer",
    "Next": "Review"
  },
  "Review": {
    "Agent": "Reviewer",
    "Next": "CheckQuality"
  },
  "CheckQuality": {
    "Type": "Choice",
    "Choices": [
      { "Variable": "$.quality", "NumericGreaterThan": 0.8, "Next": "Publish" }
    ],
    "Default": "Refine"
  },
  "Refine": {
    "Agent": "Refiner",
    "Next": "Review"
  }
}
```

## Experiment

Try these modifications:

### Add a Fifth Reviewer

```php
public function accessibilityChecker(): ClaudeAgentAdapter
{
    $agent = Agent::create($this->client)
        ->withName('AccessibilityChecker')
        ->withSystemPrompt('You check code for accessibility compliance...');
    
    return new ClaudeAgentAdapter('AccessibilityChecker', $agent);
}
```

### Use Different Models Per Agent

```php
// Fast model for simple checks
->withModel('claude-3-5-haiku-latest')

// Powerful model for complex analysis
->withModel('claude-sonnet-4-20250514')

// Most capable for synthesis
->withModel('claude-opus-4-20250514')
```

### Add Weighted Scoring

```php
$weights = [
    'security' => 0.35,
    'performance' => 0.25,
    'style' => 0.20,
    'documentation' => 0.20,
];

$overallScore = 
    ($security['score'] * $weights['security']) +
    ($perf['score'] * $weights['performance']) +
    ($style['score'] * $weights['style']) +
    ($docs['score'] * $weights['documentation']);
```

## Common Mistakes

### Parallel Branch Index Mismatch

```json
// ❌ Wrong - assuming order
"security.$": "$.reviews[0]"

// ✅ Better - use named results if possible
"security.$": "$.reviews.security"
```

### Missing Error Handling

```json
{
  "ParallelReview": {
    "Type": "Parallel",
    "Catch": [
      {
        "ErrorEquals": ["States.ALL"],
        "ResultPath": "$.parallelError",
        "Next": "HandlePartialFailure"
      }
    ]
  }
}
```

### Token Budget Exceeded

```
Error: Budget exceeded in ParallelReview
```

**Fix**: Use `Budget.MaxCost` at workflow level and monitor parallel execution costs.

### Agent Not Registered

```
Error: Agent 'StyleChecker' not found
```

**Fix**: Ensure all agents in branches are registered:

```php
foreach ($reviewers as $name => $agent) {
    $registry->register($name, $agent);
}
```

## Summary

You've learned:

- ✅ Creating specialized agent factories
- ✅ Registering multiple agents with ASL
- ✅ Parallel branch execution for concurrent analysis
- ✅ Aggregating results from multiple agents
- ✅ Building production-ready review pipelines
- ✅ Multi-agent orchestration patterns

## Next Steps

- [Tutorial 16: Loop Strategies in Workflows](16-loop-strategies-in-workflows.md) - Advanced reasoning patterns
- [Tutorial 17: RAG-Enhanced Workflows](17-rag-enhanced-workflows.md) - Knowledge-augmented agents
