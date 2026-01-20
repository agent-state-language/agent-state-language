<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\ContextAwareAgentInterface;
use AgentStateLanguage\Agents\ToolAwareAgentInterface;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Task state that executes an agent.
 */
class TaskState extends AbstractState
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
            'stateType' => 'Task',
        ]);

        try {
            // Apply InputPath
            $filteredInput = $this->applyInputPath($input, $context);

            // Resolve Parameters
            $parameters = $this->resolveParameters($filteredInput, $context);

            // Get the agent
            $agentName = $this->definition['Agent'] ?? null;
            if ($agentName === null) {
                throw new StateException(
                    'Task state missing required Agent field',
                    $this->name,
                    'States.TaskFailed'
                );
            }

            $agent = $this->registry->get($agentName);

            // Configure agent if needed
            $this->configureAgent($agent, $parameters);

            // Execute the agent
            $result = $agent->execute($parameters);

            // Apply ResultSelector if present
            $result = $this->applyResultSelector($result, $filteredInput, $context);

            // Apply ResultPath
            $output = $this->applyResultPath($input, $result);

            // Apply OutputPath
            $output = $this->applyOutputPath($output, $context);

            // Track metrics (simplified - real implementation would get from agent)
            $tokensUsed = $result['_tokens'] ?? 0;
            $cost = $result['_cost'] ?? 0.0;

            $context->addTokens($tokensUsed);
            $context->addCost($cost);

            $context->addTraceEntry([
                'type' => 'state_exit',
                'stateName' => $this->name,
                'success' => true,
                'tokensUsed' => $tokensUsed,
            ]);

            return $this->createResult($output, $tokensUsed, $cost);
        } catch (AgentException $e) {
            $context->addTraceEntry([
                'type' => 'state_error',
                'stateName' => $this->name,
                'error' => $e->getErrorCode(),
                'cause' => $e->getMessage(),
            ]);

            return StateResult::error(
                $e->getErrorCode(),
                $e->getMessage(),
                $input
            );
        } catch (\Exception $e) {
            $context->addTraceEntry([
                'type' => 'state_error',
                'stateName' => $this->name,
                'error' => 'States.TaskFailed',
                'cause' => $e->getMessage(),
            ]);

            return StateResult::error(
                'States.TaskFailed',
                $e->getMessage(),
                $input
            );
        }
    }

    /**
     * Configure the agent based on state configuration.
     *
     * @param AgentInterface $agent
     * @param array<string, mixed> $parameters
     */
    private function configureAgent(AgentInterface $agent, array $parameters): void
    {
        // Configure context if agent supports it
        if ($agent instanceof ContextAwareAgentInterface) {
            $contextConfig = $this->definition['Context'] ?? null;
            if ($contextConfig !== null) {
                $maxTokens = $contextConfig['MaxTokens'] ?? 8000;
                $agent->setMaxContextTokens($maxTokens);

                // Build context from priority paths
                // (simplified - real implementation would be more sophisticated)
                $agent->setContext($parameters);
            }
        }

        // Configure tools if agent supports it
        if ($agent instanceof ToolAwareAgentInterface) {
            $toolsConfig = $this->definition['Tools'] ?? null;
            if ($toolsConfig !== null) {
                $allowed = $toolsConfig['Allowed'] ?? [];
                $agent->setAllowedTools($allowed);
            }
        }
    }
}
