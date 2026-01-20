<?php

/**
 * Task Breakdown Example Runner
 * 
 * This script demonstrates how to run the recursive task breakdown workflow.
 * It includes mock agents for testing without external dependencies.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\AgentInterface;

// =============================================================================
// Mock Agents for Testing
// =============================================================================

/**
 * Generates clarifying questions for project goals
 */
class ClarifierAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $goal = $parameters['goal'] ?? '';
        $context = $parameters['projectContext'] ?? [];

        $questions = [];
        $hasQuestions = false;

        // Check if key context is missing
        if (empty($context['language'])) {
            $questions[] = 'What programming language will you be using?';
            $hasQuestions = true;
        }

        if (empty($context['framework'])) {
            $questions[] = 'Are you using any specific framework?';
            $hasQuestions = true;
        }

        if (stripos($goal, 'api') !== false && empty($context['authentication'])) {
            $questions[] = 'What type of authentication do you need (JWT, OAuth, session)?';
        }

        if (stripos($goal, 'database') !== false && empty($context['database'])) {
            $questions[] = 'What database system will you use?';
        }

        return [
            'hasQuestions' => $hasQuestions,
            'questions' => $questions,
            'analyzedGoal' => $goal,
            'contextProvided' => !empty($context)
        ];
    }

    public function getName(): string
    {
        return 'ClarifierAgent';
    }
}

/**
 * Breaks down goals into subtasks
 */
class BreakdownAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $goal = $parameters['goal'] ?? '';
        $context = $parameters['projectContext'] ?? [];
        $parentTask = $parameters['parentTask'] ?? null;

        // Generate subtasks based on goal keywords
        $tasks = $this->generateTasks($goal, $context, $parentTask);

        return $tasks;
    }

    private function generateTasks(string $goal, array $context, ?array $parentTask): array
    {
        $goal = strtolower($goal);
        $tasks = [];

        // If this is a sub-breakdown (has parent), generate atomic tasks
        if ($parentTask !== null) {
            return $this->generateSubtasks($parentTask);
        }

        // Top-level breakdown based on goal
        if (stripos($goal, 'api') !== false) {
            $tasks = [
                [
                    'id' => 'task_1',
                    'title' => 'Set up project structure',
                    'description' => 'Initialize project with proper folder structure',
                    'complexity' => 'low',
                    'estimatedHours' => 2
                ],
                [
                    'id' => 'task_2',
                    'title' => 'Design database schema',
                    'description' => 'Create database tables and relationships',
                    'complexity' => 'medium',
                    'estimatedHours' => 4
                ],
                [
                    'id' => 'task_3',
                    'title' => 'Implement user authentication',
                    'description' => 'Build login, registration, and JWT handling',
                    'complexity' => 'high',
                    'estimatedHours' => 8
                ],
                [
                    'id' => 'task_4',
                    'title' => 'Create API endpoints',
                    'description' => 'Implement CRUD operations for resources',
                    'complexity' => 'medium',
                    'estimatedHours' => 6
                ],
                [
                    'id' => 'task_5',
                    'title' => 'Add validation and error handling',
                    'description' => 'Input validation and proper error responses',
                    'complexity' => 'medium',
                    'estimatedHours' => 4
                ],
                [
                    'id' => 'task_6',
                    'title' => 'Write tests',
                    'description' => 'Unit and integration tests',
                    'complexity' => 'medium',
                    'estimatedHours' => 6
                ]
            ];
        } elseif (stripos($goal, 'website') !== false || stripos($goal, 'web') !== false) {
            $tasks = [
                ['id' => 'task_1', 'title' => 'Create wireframes', 'complexity' => 'low', 'estimatedHours' => 3],
                ['id' => 'task_2', 'title' => 'Set up frontend framework', 'complexity' => 'low', 'estimatedHours' => 2],
                ['id' => 'task_3', 'title' => 'Implement pages', 'complexity' => 'high', 'estimatedHours' => 16],
                ['id' => 'task_4', 'title' => 'Add styling', 'complexity' => 'medium', 'estimatedHours' => 8],
                ['id' => 'task_5', 'title' => 'Deploy to production', 'complexity' => 'medium', 'estimatedHours' => 4]
            ];
        } else {
            // Generic breakdown
            $tasks = [
                ['id' => 'task_1', 'title' => 'Research and planning', 'complexity' => 'low', 'estimatedHours' => 4],
                ['id' => 'task_2', 'title' => 'Implementation', 'complexity' => 'high', 'estimatedHours' => 20],
                ['id' => 'task_3', 'title' => 'Testing', 'complexity' => 'medium', 'estimatedHours' => 8],
                ['id' => 'task_4', 'title' => 'Documentation', 'complexity' => 'low', 'estimatedHours' => 4]
            ];
        }

        return $tasks;
    }

    private function generateSubtasks(array $parentTask): array
    {
        $title = strtolower($parentTask['title'] ?? '');

        if (stripos($title, 'authentication') !== false) {
            return [
                ['id' => $parentTask['id'] . '_1', 'title' => 'Create user model', 'complexity' => 'low', 'estimatedHours' => 1],
                ['id' => $parentTask['id'] . '_2', 'title' => 'Implement registration endpoint', 'complexity' => 'medium', 'estimatedHours' => 2],
                ['id' => $parentTask['id'] . '_3', 'title' => 'Implement login endpoint', 'complexity' => 'medium', 'estimatedHours' => 2],
                ['id' => $parentTask['id'] . '_4', 'title' => 'Add JWT token generation', 'complexity' => 'medium', 'estimatedHours' => 2],
                ['id' => $parentTask['id'] . '_5', 'title' => 'Create auth middleware', 'complexity' => 'low', 'estimatedHours' => 1]
            ];
        }

        if (stripos($title, 'database') !== false) {
            return [
                ['id' => $parentTask['id'] . '_1', 'title' => 'Design entity relationships', 'complexity' => 'medium', 'estimatedHours' => 2],
                ['id' => $parentTask['id'] . '_2', 'title' => 'Create migrations', 'complexity' => 'low', 'estimatedHours' => 1],
                ['id' => $parentTask['id'] . '_3', 'title' => 'Set up seeders', 'complexity' => 'low', 'estimatedHours' => 1]
            ];
        }

        // Generic subtasks
        return [
            ['id' => $parentTask['id'] . '_1', 'title' => 'Subtask 1: Research', 'complexity' => 'low', 'estimatedHours' => 1],
            ['id' => $parentTask['id'] . '_2', 'title' => 'Subtask 2: Implement', 'complexity' => 'medium', 'estimatedHours' => 2]
        ];
    }

    public function getName(): string
    {
        return 'BreakdownAgent';
    }
}

/**
 * Validates if a task is atomic
 */
class ValidatorAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $task = $parameters['task'] ?? [];
        $depth = $parameters['depth'] ?? 0;

        $complexity = $task['complexity'] ?? 'medium';
        $estimatedHours = $task['estimatedHours'] ?? 4;

        // Task is atomic if:
        // - Low complexity
        // - Or estimated hours <= 2
        // - Or depth >= 2 (for simulation purposes)
        $isAtomic = $complexity === 'low' ||
            $estimatedHours <= 2 ||
            $depth >= 2;

        $confidence = $isAtomic ? 0.9 : 0.6;

        return [
            'isAtomic' => $isAtomic,
            'confidence' => $confidence,
            'complexity' => $complexity,
            'estimatedHours' => $estimatedHours,
            'reason' => $isAtomic
                ? 'Task is small enough to be actionable'
                : 'Task should be broken down further'
        ];
    }

    public function getName(): string
    {
        return 'ValidatorAgent';
    }
}

/**
 * Collects and organizes results from task processing
 */
class ResultCollectorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $processedTasks = $parameters['processedTasks'] ?? [];
        $currentDepth = $parameters['currentDepth'] ?? 0;

        $completedTasks = [];
        $pendingTasks = [];

        foreach ($processedTasks as $result) {
            $task = $result['task'] ?? [];
            $status = $result['status'] ?? 'unknown';
            $subtasks = $result['subtasks'] ?? [];

            if ($status === 'atomic' || $status === 'max_depth_reached') {
                $completedTasks[] = [
                    'task' => $task,
                    'status' => $status,
                    'depth' => $currentDepth
                ];
            } elseif ($status === 'has_subtasks' && !empty($subtasks)) {
                // Add subtasks as pending
                foreach ($subtasks as $subtask) {
                    $pendingTasks[] = $subtask;
                }
                // Also mark parent as having subtasks
                $completedTasks[] = [
                    'task' => $task,
                    'status' => 'has_subtasks',
                    'subtaskCount' => count($subtasks),
                    'depth' => $currentDepth
                ];
            }
        }

        return [
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'hasPendingTasks' => !empty($pendingTasks),
            'completedCount' => count($completedTasks),
            'pendingCount' => count($pendingTasks)
        ];
    }

    public function getName(): string
    {
        return 'ResultCollector';
    }
}

/**
 * Finalizes and formats the breakdown output
 */
class FinalizerAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $allTasks = $parameters['allTasks'] ?? [];
        $goal = $parameters['goal'] ?? '';

        // Organize tasks hierarchically
        $organizedTasks = [];
        $maxDepth = 0;

        foreach ($allTasks as $item) {
            $task = $item['task'] ?? [];
            $depth = $item['depth'] ?? 0;
            $status = $item['status'] ?? 'unknown';

            $maxDepth = max($maxDepth, $depth);

            // Only include leaf tasks (atomic or max_depth)
            if ($status === 'atomic' || $status === 'max_depth_reached') {
                $organizedTasks[] = [
                    'id' => $task['id'] ?? uniqid('task_'),
                    'title' => $task['title'] ?? 'Untitled Task',
                    'description' => $task['description'] ?? '',
                    'complexity' => $task['complexity'] ?? 'medium',
                    'estimatedHours' => $task['estimatedHours'] ?? 2,
                    'depth' => $depth
                ];
            }
        }

        // Calculate totals
        $totalHours = array_sum(array_column($organizedTasks, 'estimatedHours'));

        return [
            'organizedTasks' => $organizedTasks,
            'totalTasks' => count($organizedTasks),
            'maxDepthReached' => $maxDepth,
            'totalEstimatedHours' => $totalHours,
            'summary' => sprintf(
                'Broke down "%s" into %d actionable tasks, estimated %d hours total',
                $goal,
                count($organizedTasks),
                $totalHours
            )
        ];
    }

    public function getName(): string
    {
        return 'FinalizerAgent';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

echo "=== Task Breakdown Workflow Example ===\n\n";

// Create and configure the agent registry
$registry = new AgentRegistry();
$registry->register('ClarifierAgent', new ClarifierAgentAgent());
$registry->register('BreakdownAgent', new BreakdownAgentAgent());
$registry->register('ValidatorAgent', new ValidatorAgentAgent());
$registry->register('ResultCollector', new ResultCollectorAgent());
$registry->register('FinalizerAgent', new FinalizerAgentAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

// Test Case 1: API Project
echo "Test 1: REST API Project\n";
echo str_repeat('-', 60) . "\n";

$result1 = $engine->run([
    'goal' => 'Build a REST API with user authentication',
    'projectContext' => [
        'language' => 'PHP',
        'framework' => 'Laravel',
        'team_size' => 2
    ]
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Goal: {$output['goal']}\n";
    echo "Summary: {$output['summary']}\n\n";
    echo "Tasks:\n";
    foreach ($output['tasks'] as $i => $task) {
        $indent = str_repeat('  ', $task['depth'] ?? 0);
        echo "{$indent}" . ($i + 1) . ". {$task['title']} ({$task['estimatedHours']}h)\n";
    }
    echo "\nStatistics:\n";
    echo "  Total Tasks: {$output['statistics']['totalTasks']}\n";
    echo "  Max Depth: {$output['statistics']['maxDepthReached']}\n";
}

// Test Case 2: Website Project
echo "\n\nTest 2: Website Project\n";
echo str_repeat('-', 60) . "\n";

$result2 = $engine->run([
    'goal' => 'Create a company website with blog',
    'projectContext' => [
        'language' => 'JavaScript',
        'framework' => 'React'
    ]
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Goal: {$output['goal']}\n";
    echo "Summary: {$output['summary']}\n\n";
    echo "Tasks:\n";
    foreach ($output['tasks'] as $i => $task) {
        echo ($i + 1) . ". {$task['title']}\n";
    }
}

// Test Case 3: Generic Project (no context provided)
echo "\n\nTest 3: Generic Project (No Context)\n";
echo str_repeat('-', 60) . "\n";

$result3 = $engine->run([
    'goal' => 'Automate the deployment pipeline',
    'projectContext' => []
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Goal: {$output['goal']}\n";
    echo "Summary: {$output['summary']}\n";
}

echo "\n\n=== Task Breakdown Workflow Complete ===\n";
