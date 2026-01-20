<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Debate state for multi-agent deliberation.
 */
class DebateState extends AbstractState
{
    private AgentRegistry $registry;

    /**
     * @param string $name State name
     * @param array<string, mixed> $definition State definition
     * @param AgentRegistry $registry Agent registry
     */
    public function __construct(
        string $name,
        array $definition,
        AgentRegistry $registry
    ) {
        parent::__construct($name, $definition);
        $this->registry = $registry;
    }

    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Debate',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Get debate configuration
        $agentNames = $this->definition['Agents'] ?? [];
        if (count($agentNames) < 2) {
            throw new StateException(
                'Debate state requires at least 2 agents',
                $this->name,
                'States.TaskFailed'
            );
        }

        // Get topic
        $topic = $this->getTopic($filteredInput, $context);

        // Get rounds
        $rounds = $this->definition['Rounds'] ?? 3;

        // Get consensus configuration
        $consensusConfig = $this->definition['Consensus'] ?? [];
        $requireConsensus = $consensusConfig['Required'] ?? false;
        $arbiter = $consensusConfig['Arbiter'] ?? end($agentNames);

        // Execute debate rounds
        $history = [];
        $totalTokens = 0;
        $totalCost = 0.0;

        for ($round = 1; $round <= $rounds; $round++) {
            $roundResults = [];

            foreach ($agentNames as $agentName) {
                // Skip arbiter until final decision
                if ($agentName === $arbiter && $requireConsensus) {
                    continue;
                }

                $agent = $this->registry->get($agentName);

                // Build prompt for this agent
                $prompt = $this->buildAgentPrompt(
                    $agentName,
                    $topic,
                    $history,
                    $round,
                    $rounds
                );

                // Execute agent
                $result = $agent->execute([
                    'prompt' => $prompt,
                    'topic' => $topic,
                    'round' => $round,
                    'history' => $history,
                ]);

                $roundResults[$agentName] = $result['response'] ?? $result;
                $totalTokens += $result['_tokens'] ?? 0;
                $totalCost += $result['_cost'] ?? 0.0;

                $history[] = [
                    'round' => $round,
                    'agent' => $agentName,
                    'response' => $result['response'] ?? json_encode($result),
                ];
            }
        }

        // Get final decision from arbiter if consensus required
        $decision = null;
        if ($requireConsensus) {
            $arbiterAgent = $this->registry->get($arbiter);

            $arbiterPrompt = $this->buildArbiterPrompt($topic, $history);
            $arbiterResult = $arbiterAgent->execute([
                'prompt' => $arbiterPrompt,
                'topic' => $topic,
                'history' => $history,
            ]);

            $decision = $arbiterResult['decision'] ?? $arbiterResult['response'] ?? null;
            $totalTokens += $arbiterResult['_tokens'] ?? 0;
            $totalCost += $arbiterResult['_cost'] ?? 0.0;
        }

        // Build debate result
        $debateResult = [
            'topic' => $topic,
            'rounds' => $rounds,
            'participants' => $agentNames,
            'history' => $history,
            'decision' => $decision,
            'consensus' => $requireConsensus ? ($decision !== null) : null,
        ];

        // Apply ResultSelector if present
        $result = $this->applyResultSelector($debateResult, $filteredInput, $context);

        // Apply ResultPath
        $output = $this->applyResultPath($input, $result);

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'rounds' => $rounds,
            'tokensUsed' => $totalTokens,
        ]);

        return $this->createResult($output, $totalTokens, $totalCost);
    }

    /**
     * Get the debate topic.
     *
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return string
     */
    private function getTopic(array $input, ExecutionContext $context): string
    {
        if (isset($this->definition['Topic'])) {
            return $this->definition['Topic'];
        }

        if (isset($this->definition['TopicPath'])) {
            $topic = JsonPath::evaluate(
                $this->definition['TopicPath'],
                $input,
                $context->toContextObject()
            );
            return (string) $topic;
        }

        return 'General discussion';
    }

    /**
     * Build prompt for a debate participant.
     *
     * @param string $agentName
     * @param string $topic
     * @param array<array<string, mixed>> $history
     * @param int $round
     * @param int $totalRounds
     * @return string
     */
    private function buildAgentPrompt(
        string $agentName,
        string $topic,
        array $history,
        int $round,
        int $totalRounds
    ): string {
        $prompt = "You are participating in a debate.\n";
        $prompt .= "Topic: {$topic}\n";
        $prompt .= "Round: {$round} of {$totalRounds}\n\n";

        if (!empty($history)) {
            $prompt .= "Previous contributions:\n";
            foreach ($history as $entry) {
                $prompt .= "- {$entry['agent']} (Round {$entry['round']}): {$entry['response']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Please provide your perspective on this topic.";

        return $prompt;
    }

    /**
     * Build prompt for the arbiter.
     *
     * @param string $topic
     * @param array<array<string, mixed>> $history
     * @return string
     */
    private function buildArbiterPrompt(string $topic, array $history): string
    {
        $prompt = "You are the arbiter of a debate.\n";
        $prompt .= "Topic: {$topic}\n\n";
        $prompt .= "Debate contributions:\n";

        foreach ($history as $entry) {
            $prompt .= "- {$entry['agent']} (Round {$entry['round']}): {$entry['response']}\n";
        }

        $prompt .= "\nPlease provide a final decision or synthesis of the debate.";
        $prompt .= " Return your decision in a 'decision' field.";

        return $prompt;
    }
}
