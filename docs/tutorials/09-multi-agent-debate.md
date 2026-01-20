# Tutorial 9: Multi-Agent Debate

Learn how to orchestrate discussions between multiple agents to reach better decisions.

## What You'll Learn

- Setting up multi-agent debates
- Communication styles and turn order
- Consensus building with arbiters
- Building a technical decision-making system

## Prerequisites

- Completed [Tutorial 8: Human Approval](08-human-approval.md)
- Understanding of agent interactions

## The Scenario

We'll build a technical architecture review system where:

1. A **Proponent** agent argues for a proposed solution
2. An **Opponent** agent identifies potential issues
3. A **Judge** agent evaluates arguments and makes a decision
4. Multiple rounds refine the discussion

## Step 1: Understanding Debate States

Debate states enable structured multi-agent discussions. Unlike simple parallel execution, debates involve:

- **Turn-based communication** - Agents respond in order
- **Shared context** - Each agent sees previous responses
- **Consensus building** - Working toward a decision
- **Arbitration** - A final judge when needed

### Debate Flow

```
Round 1: Proponent → Opponent → Judge Summary
Round 2: Proponent Response → Opponent Response → Judge Summary
Round 3: Final Arguments → Final Decision
```

## Step 2: Create the Agents

### ProponentAgent

Argues in favor of the proposed solution:

```php
<?php

namespace MyOrg\TechDebate;

use AgentStateLanguage\Agents\AgentInterface;

class ProponentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? '';
        $proposal = $parameters['proposal'] ?? '';
        $round = $parameters['round'] ?? 1;
        $previousResponses = $parameters['history'] ?? [];
        
        // Build argument based on round and previous responses
        $argument = $this->buildArgument($topic, $proposal, $round, $previousResponses);
        
        return [
            'agent' => 'ProponentAgent',
            'role' => 'proponent',
            'round' => $round,
            'argument' => $argument,
            'confidence' => $this->calculateConfidence($round, $previousResponses),
            'keyPoints' => $this->extractKeyPoints($argument),
            'timestamp' => date('c')
        ];
    }
    
    private function buildArgument(string $topic, string $proposal, int $round, array $history): string
    {
        if ($round === 1) {
            return "I strongly support {$proposal} for {$topic}. " .
                   "The key benefits include: improved scalability, better maintainability, " .
                   "and alignment with industry best practices.";
        }
        
        // Address opponent's concerns in subsequent rounds
        $opponentPoints = $this->getOpponentPoints($history);
        
        if ($round === 2) {
            return "Addressing the concerns raised: " .
                   "While there are valid points about complexity, the long-term benefits " .
                   "outweigh the initial investment. Modern tooling has significantly reduced " .
                   "the operational overhead.";
        }
        
        return "In conclusion, {$proposal} remains the best choice. " .
               "We've addressed all major concerns and the path forward is clear.";
    }
    
    private function getOpponentPoints(array $history): array
    {
        $points = [];
        foreach ($history as $entry) {
            if (($entry['role'] ?? '') === 'opponent') {
                $points = array_merge($points, $entry['keyPoints'] ?? []);
            }
        }
        return $points;
    }
    
    private function calculateConfidence(int $round, array $history): float
    {
        // Confidence may change based on debate progress
        return match($round) {
            1 => 0.85,
            2 => 0.80,
            default => 0.75
        };
    }
    
    private function extractKeyPoints(string $argument): array
    {
        // Simplified key point extraction
        return [
            'Improved scalability',
            'Better maintainability',
            'Industry alignment'
        ];
    }

    public function getName(): string
    {
        return 'ProponentAgent';
    }
}
```

### OpponentAgent

Identifies concerns and counterarguments:

```php
<?php

namespace MyOrg\TechDebate;

use AgentStateLanguage\Agents\AgentInterface;

class OpponentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? '';
        $proposal = $parameters['proposal'] ?? '';
        $round = $parameters['round'] ?? 1;
        $previousResponses = $parameters['history'] ?? [];
        
        $argument = $this->buildCounterArgument($topic, $proposal, $round, $previousResponses);
        
        return [
            'agent' => 'OpponentAgent',
            'role' => 'opponent',
            'round' => $round,
            'argument' => $argument,
            'concerns' => $this->identifyConcerns($proposal),
            'keyPoints' => $this->extractKeyPoints($argument),
            'alternativeProposal' => $this->suggestAlternative($proposal),
            'timestamp' => date('c')
        ];
    }
    
    private function buildCounterArgument(string $topic, string $proposal, int $round, array $history): string
    {
        if ($round === 1) {
            return "While {$proposal} has merits, we must consider: " .
                   "increased operational complexity, steeper learning curve for the team, " .
                   "and potential over-engineering for our current scale.";
        }
        
        if ($round === 2) {
            return "The proponent's points about tooling are valid, but: " .
                   "our team lacks experience with this approach, timeline pressures don't " .
                   "allow for proper implementation, and simpler alternatives exist.";
        }
        
        return "I maintain reservations about {$proposal}. " .
               "If we proceed, we need clear mitigation strategies for the risks identified.";
    }
    
    private function identifyConcerns(string $proposal): array
    {
        return [
            ['type' => 'complexity', 'severity' => 'medium'],
            ['type' => 'learning_curve', 'severity' => 'high'],
            ['type' => 'timeline_risk', 'severity' => 'medium']
        ];
    }
    
    private function extractKeyPoints(string $argument): array
    {
        return [
            'Increased complexity',
            'Team experience gap',
            'Timeline concerns'
        ];
    }
    
    private function suggestAlternative(string $proposal): string
    {
        return "Consider a phased approach or simpler architecture initially";
    }

    public function getName(): string
    {
        return 'OpponentAgent';
    }
}
```

### JudgeAgent

Evaluates arguments and makes final decisions:

```php
<?php

namespace MyOrg\TechDebate;

use AgentStateLanguage\Agents\AgentInterface;

class JudgeAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? '';
        $proposal = $parameters['proposal'] ?? '';
        $round = $parameters['round'] ?? 1;
        $history = $parameters['history'] ?? [];
        $isFinalRound = $parameters['isFinalRound'] ?? false;
        
        $evaluation = $this->evaluateArguments($history);
        
        if ($isFinalRound) {
            return $this->makeFinalDecision($topic, $proposal, $history, $evaluation);
        }
        
        return [
            'agent' => 'JudgeAgent',
            'role' => 'arbiter',
            'round' => $round,
            'evaluation' => $evaluation,
            'summary' => $this->summarizeRound($history, $round),
            'guidanceForNextRound' => $this->provideGuidance($evaluation),
            'timestamp' => date('c')
        ];
    }
    
    private function evaluateArguments(array $history): array
    {
        $proponentScore = 0;
        $opponentScore = 0;
        
        foreach ($history as $entry) {
            $keyPoints = count($entry['keyPoints'] ?? []);
            $confidence = $entry['confidence'] ?? 0.5;
            
            if (($entry['role'] ?? '') === 'proponent') {
                $proponentScore += $keyPoints * $confidence;
            } else if (($entry['role'] ?? '') === 'opponent') {
                $opponentScore += $keyPoints * 0.7; // Weight concerns
            }
        }
        
        return [
            'proponentScore' => round($proponentScore, 2),
            'opponentScore' => round($opponentScore, 2),
            'balance' => $proponentScore - $opponentScore
        ];
    }
    
    private function summarizeRound(array $history, int $round): string
    {
        $roundEntries = array_filter($history, fn($e) => ($e['round'] ?? 0) === $round);
        
        return "Round {$round} Summary: Both sides presented valid points. " .
               "The proponent emphasized benefits while the opponent raised legitimate concerns.";
    }
    
    private function provideGuidance(array $evaluation): string
    {
        if ($evaluation['balance'] > 0) {
            return "The proponent should address remaining concerns. " .
                   "The opponent should propose concrete alternatives.";
        }
        
        return "The proponent needs stronger evidence. " .
               "The opponent should acknowledge any valid benefits.";
    }
    
    private function makeFinalDecision(string $topic, string $proposal, array $history, array $evaluation): array
    {
        $approved = $evaluation['balance'] > 0.5;
        
        return [
            'agent' => 'JudgeAgent',
            'role' => 'arbiter',
            'isFinalDecision' => true,
            'decision' => $approved ? 'approved_with_conditions' : 'rejected_with_alternative',
            'recommendation' => $approved 
                ? "Proceed with {$proposal}, but implement the mitigation strategies discussed."
                : "Consider the phased approach suggested by the opponent.",
            'evaluation' => $evaluation,
            'conditions' => $approved ? [
                'Address team training needs',
                'Implement in phases',
                'Set up monitoring early'
            ] : [],
            'consensus' => abs($evaluation['balance']) < 1.0 ? 'partial' : 'strong',
            'timestamp' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'JudgeAgent';
    }
}
```

## Step 3: Define the Workflow

Create `tech-debate.asl.json`:

```json
{
  "Comment": "Technical architecture debate with multi-agent discussion",
  "StartAt": "InitializeDebate",
  "States": {
    "InitializeDebate": {
      "Type": "Pass",
      "Parameters": {
        "topic.$": "$.topic",
        "proposal.$": "$.proposal",
        "maxRounds": 3,
        "currentRound": 1,
        "history": []
      },
      "Next": "DebateRound"
    },
    "DebateRound": {
      "Type": "Debate",
      "Agents": ["ProponentAgent", "OpponentAgent", "JudgeAgent"],
      "TopicPath": "$.topic",
      "Parameters": {
        "topic.$": "$.topic",
        "proposal.$": "$.proposal",
        "round.$": "$.currentRound",
        "history.$": "$.history"
      },
      "Communication": {
        "Style": "turn_based",
        "Order": ["ProponentAgent", "OpponentAgent", "JudgeAgent"],
        "VisibleHistory": "all"
      },
      "Rounds": 1,
      "ResultPath": "$.roundResult",
      "Next": "UpdateHistory"
    },
    "UpdateHistory": {
      "Type": "Pass",
      "Parameters": {
        "topic.$": "$.topic",
        "proposal.$": "$.proposal",
        "maxRounds.$": "$.maxRounds",
        "currentRound.$": "States.MathAdd($.currentRound, 1)",
        "history.$": "States.ArrayConcat($.history, $.roundResult.responses)"
      },
      "Next": "CheckDebateComplete"
    },
    "CheckDebateComplete": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.currentRound",
          "NumericGreaterThan": 3,
          "Next": "FinalDecision"
        }
      ],
      "Default": "DebateRound"
    },
    "FinalDecision": {
      "Type": "Task",
      "Agent": "JudgeAgent",
      "Parameters": {
        "topic.$": "$.topic",
        "proposal.$": "$.proposal",
        "round.$": "$.currentRound",
        "history.$": "$.history",
        "isFinalRound": true
      },
      "ResultPath": "$.finalDecision",
      "Next": "FormatResult"
    },
    "FormatResult": {
      "Type": "Pass",
      "Parameters": {
        "topic.$": "$.topic",
        "proposal.$": "$.proposal",
        "decision.$": "$.finalDecision.decision",
        "recommendation.$": "$.finalDecision.recommendation",
        "conditions.$": "$.finalDecision.conditions",
        "consensus.$": "$.finalDecision.consensus",
        "debateRounds": 3,
        "participantCount": 3
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
use MyOrg\TechDebate\ProponentAgent;
use MyOrg\TechDebate\OpponentAgent;
use MyOrg\TechDebate\JudgeAgent;

// Create registry and register agents
$registry = new AgentRegistry();
$registry->register('ProponentAgent', new ProponentAgent());
$registry->register('OpponentAgent', new OpponentAgent());
$registry->register('JudgeAgent', new JudgeAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile('tech-debate.asl.json', $registry);

// Run the debate
$result = $engine->run([
    'topic' => 'Backend Architecture for E-commerce Platform',
    'proposal' => 'Microservices Architecture'
]);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    
    echo "=== Technical Architecture Debate Results ===\n\n";
    echo "Topic: {$output['topic']}\n";
    echo "Proposal: {$output['proposal']}\n";
    echo "Rounds Completed: {$output['debateRounds']}\n";
    echo "Participants: {$output['participantCount']}\n";
    echo "\n";
    echo "DECISION: " . strtoupper($output['decision']) . "\n";
    echo "Consensus Level: {$output['consensus']}\n";
    echo "\nRecommendation:\n{$output['recommendation']}\n";
    
    if (!empty($output['conditions'])) {
        echo "\nConditions:\n";
        foreach ($output['conditions'] as $i => $condition) {
            echo "  " . ($i + 1) . ". {$condition}\n";
        }
    }
} else {
    echo "Debate failed: " . $result->getError() . "\n";
}
```

## Expected Output

```
=== Technical Architecture Debate Results ===

Topic: Backend Architecture for E-commerce Platform
Proposal: Microservices Architecture
Rounds Completed: 3
Participants: 3

DECISION: APPROVED_WITH_CONDITIONS
Consensus Level: partial

Recommendation:
Proceed with Microservices Architecture, but implement the mitigation strategies discussed.

Conditions:
  1. Address team training needs
  2. Implement in phases
  3. Set up monitoring early
```

## Debate Configuration Options

### Communication Styles

| Style | Description |
|-------|-------------|
| `turn_based` | Agents respond in defined order |
| `simultaneous` | All agents respond at once per round |
| `reactive` | Agents respond to specific triggers |

```json
{
  "Communication": {
    "Style": "turn_based",
    "Order": ["Agent1", "Agent2", "Agent3"],
    "VisibleHistory": "all"
  }
}
```

### History Visibility

| Option | Description |
|--------|-------------|
| `all` | All agents see all responses |
| `previous_only` | Only see immediately preceding response |
| `own_only` | Only see own previous responses |
| `none` | No history visibility |

### Consensus Configuration

```json
{
  "Consensus": {
    "Required": true,
    "Threshold": 0.7,
    "Arbiter": "JudgeAgent",
    "TieBreaker": "Arbiter",
    "MaxRoundsForConsensus": 5
  }
}
```

## Experiment

Try these modifications:

### Add a Fourth Perspective

```json
{
  "Agents": ["ProponentAgent", "OpponentAgent", "PragmatistAgent", "JudgeAgent"],
  "Communication": {
    "Order": ["ProponentAgent", "OpponentAgent", "PragmatistAgent", "JudgeAgent"]
  }
}
```

### Implement Voting

```json
{
  "Consensus": {
    "Method": "voting",
    "RequiredMajority": 0.66,
    "VotingAgents": ["Expert1", "Expert2", "Expert3"],
    "TieBreaker": "SeniorExpert"
  }
}
```

## Common Mistakes

### Infinite Debate Loop

```json
{
  "Consensus": {
    "Required": true,
    "Threshold": 1.0
  }
}
```

**Problem**: Perfect consensus may never be reached.

**Fix**: Set reasonable threshold and max rounds.

### Missing Arbiter

```json
{
  "Consensus": {
    "Required": true
  }
}
```

**Problem**: No way to break deadlocks.

**Fix**: Always specify an arbiter.

### History Not Passed

```json
{
  "Parameters": {
    "topic.$": "$.topic"
  }
}
```

**Problem**: Agents can't see previous responses.

**Fix**: Always pass history to agents.

## Use Cases for Multi-Agent Debate

1. **Architecture Decisions** - Evaluate technical proposals
2. **Risk Assessment** - Multiple perspectives on risk
3. **Code Review** - Different reviewers debate changes
4. **Content Quality** - Editors debate content decisions
5. **Security Review** - Red team vs blue team analysis

## Summary

You've learned:

- ✅ Setting up multi-agent debates
- ✅ Communication styles and turn ordering
- ✅ Consensus building with arbiters
- ✅ Building structured decision-making systems
- ✅ Handling debate history and context

## Next Steps

- [Tutorial 10: Cost Management](10-cost-management.md) - Budget control
- [Tutorial 11: Error Handling](11-error-handling.md) - Retry and recovery
