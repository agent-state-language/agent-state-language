# Tutorial 9: Multi-Agent Debate

Learn how to orchestrate discussions between multiple agents.

## What You'll Learn

- Debate states
- Communication styles
- Consensus building
- Arbiter patterns

## Basic Debate

```json
{
  "DebateSolution": {
    "Type": "Debate",
    "Agents": ["ProponentAgent", "OpponentAgent"],
    "Topic": "Should we use microservices?",
    "Rounds": 3,
    "Next": "ProcessDecision"
  }
}
```

## With Consensus

```json
{
  "ConsensusDecision": {
    "Type": "Debate",
    "Agents": ["ArchitectA", "ArchitectB", "JudgeAgent"],
    "TopicPath": "$.architectureQuestion",
    "Rounds": 3,
    "Consensus": {
      "Required": true,
      "Arbiter": "JudgeAgent",
      "Threshold": 0.7
    },
    "ResultPath": "$.decision"
  }
}
```

## Communication Styles

```json
{
  "TurnBasedDebate": {
    "Type": "Debate",
    "Agents": ["Expert1", "Expert2", "Expert3"],
    "Communication": {
      "Style": "turn_based",
      "VisibleHistory": "all"
    },
    "Rounds": 5
  }
}
```

## Result Structure

The debate produces:

```json
{
  "topic": "Should we use microservices?",
  "rounds": 3,
  "participants": ["ProponentAgent", "OpponentAgent"],
  "history": [
    { "round": 1, "agent": "ProponentAgent", "response": "..." },
    { "round": 1, "agent": "OpponentAgent", "response": "..." }
  ],
  "decision": "Final arbiter decision",
  "consensus": true
}
```

## Summary

You've learned:

- ✅ Setting up multi-agent debates
- ✅ Configuring rounds and topics
- ✅ Consensus requirements
- ✅ Arbiter patterns
