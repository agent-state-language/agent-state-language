<?php

/**
 * Code Review Example Runner
 * 
 * This script demonstrates how to run the multi-agent code review workflow.
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
 * Loads code files and diffs for review
 */
class CodeLoaderAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $files = $parameters['files'] ?? [];
        $diff = $parameters['diff'] ?? '';
        
        return [
            'files' => $files,
            'fileCount' => count($files),
            'diff' => $diff,
            'linesAdded' => substr_count($diff, '+'),
            'linesRemoved' => substr_count($diff, '-'),
            'loadedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'CodeLoader';
    }
}

/**
 * Reviews code for security vulnerabilities
 */
class SecurityReviewerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $code = $parameters['code'] ?? [];
        $checkList = $parameters['checkList'] ?? [];
        
        // Simulate security findings based on code content
        $issues = [];
        $diff = $code['diff'] ?? '';
        
        if (stripos($diff, 'password') !== false) {
            $issues[] = [
                'type' => 'sensitive_data',
                'severity' => 'high',
                'message' => 'Potential sensitive data exposure detected'
            ];
        }
        
        if (stripos($diff, 'eval(') !== false) {
            $issues[] = [
                'type' => 'code_injection',
                'severity' => 'critical',
                'message' => 'Eval statement detected - potential code injection'
            ];
        }
        
        return [
            'category' => 'security',
            'issues' => $issues,
            'issueCount' => count($issues),
            'passed' => empty($issues),
            'checkedItems' => $checkList
        ];
    }

    public function getName(): string
    {
        return 'SecurityReviewer';
    }
}

/**
 * Reviews code for performance issues
 */
class PerformanceReviewerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $code = $parameters['code'] ?? [];
        $metrics = $parameters['metrics'] ?? [];
        
        $issues = [];
        $diff = $code['diff'] ?? '';
        
        if (stripos($diff, 'foreach') !== false && stripos($diff, 'query') !== false) {
            $issues[] = [
                'type' => 'n+1_query',
                'severity' => 'medium',
                'message' => 'Potential N+1 query pattern detected'
            ];
        }
        
        return [
            'category' => 'performance',
            'issues' => $issues,
            'issueCount' => count($issues),
            'passed' => empty($issues),
            'complexity' => 'low',
            'estimatedImpact' => 'minimal'
        ];
    }

    public function getName(): string
    {
        return 'PerformanceReviewer';
    }
}

/**
 * Reviews code for style compliance
 */
class StyleReviewerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $code = $parameters['code'] ?? [];
        $standards = $parameters['standards'] ?? 'PSR-12';
        
        $issues = [];
        $diff = $code['diff'] ?? '';
        
        if (preg_match('/\t/', $diff)) {
            $issues[] = [
                'type' => 'indentation',
                'severity' => 'low',
                'message' => 'Tab characters detected, spaces preferred'
            ];
        }
        
        return [
            'category' => 'style',
            'issues' => $issues,
            'issueCount' => count($issues),
            'passed' => empty($issues),
            'standard' => $standards
        ];
    }

    public function getName(): string
    {
        return 'StyleReviewer';
    }
}

/**
 * Reviews test coverage
 */
class TestReviewerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $code = $parameters['code'] ?? [];
        $files = $code['files'] ?? [];
        
        $hasTests = false;
        foreach ($files as $file) {
            if (stripos($file, 'test') !== false) {
                $hasTests = true;
                break;
            }
        }
        
        $issues = [];
        if (!$hasTests) {
            $issues[] = [
                'type' => 'missing_tests',
                'severity' => 'medium',
                'message' => 'No test files included in the changes'
            ];
        }
        
        return [
            'category' => 'tests',
            'issues' => $issues,
            'issueCount' => count($issues),
            'passed' => $hasTests,
            'coverageEstimate' => $hasTests ? 75 : 0
        ];
    }

    public function getName(): string
    {
        return 'TestReviewer';
    }
}

/**
 * Aggregates all review results
 */
class ReviewAggregatorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $security = $parameters['security'] ?? [];
        $performance = $parameters['performance'] ?? [];
        $style = $parameters['style'] ?? [];
        $tests = $parameters['tests'] ?? [];
        
        // Collect all issues
        $allIssues = array_merge(
            $security['issues'] ?? [],
            $performance['issues'] ?? [],
            $style['issues'] ?? [],
            $tests['issues'] ?? []
        );
        
        // Determine overall severity
        $severity = 'low';
        $criticalIssues = [];
        
        foreach ($allIssues as $issue) {
            if (($issue['severity'] ?? 'low') === 'critical') {
                $severity = 'critical';
                $criticalIssues[] = $issue;
            } elseif (($issue['severity'] ?? 'low') === 'high' && $severity !== 'critical') {
                $severity = 'high';
            } elseif (($issue['severity'] ?? 'low') === 'medium' && !in_array($severity, ['high', 'critical'])) {
                $severity = 'medium';
            }
        }
        
        $passesAllChecks = ($security['passed'] ?? false) && 
                          ($performance['passed'] ?? false) && 
                          ($style['passed'] ?? false) && 
                          ($tests['passed'] ?? false);
        
        return [
            'severity' => $severity,
            'totalIssues' => count($allIssues),
            'issues' => $allIssues,
            'criticalIssues' => $criticalIssues,
            'passesAllChecks' => $passesAllChecks,
            'summary' => sprintf(
                'Found %d issues: %d security, %d performance, %d style, %d test coverage',
                count($allIssues),
                count($security['issues'] ?? []),
                count($performance['issues'] ?? []),
                count($style['issues'] ?? []),
                count($tests['issues'] ?? [])
            ),
            'categories' => [
                'security' => $security['passed'] ?? false,
                'performance' => $performance['passed'] ?? false,
                'style' => $style['passed'] ?? false,
                'tests' => $tests['passed'] ?? false
            ]
        ];
    }

    public function getName(): string
    {
        return 'ReviewAggregator';
    }
}

/**
 * Finalizes the review and prepares output
 */
class ReviewFinalizerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $files = $parameters['files'] ?? [];
        $reviews = $parameters['reviews'] ?? [];
        $decision = $parameters['decision'] ?? [];
        
        return [
            'completed' => true,
            'filesReviewed' => $files,
            'decision' => $decision['decision'] ?? 'unknown',
            'issuesSummary' => $reviews['summary'] ?? '',
            'totalIssues' => $reviews['totalIssues'] ?? 0,
            'severity' => $reviews['severity'] ?? 'unknown',
            'completedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ReviewFinalizer';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

echo "=== Code Review Workflow Example ===\n\n";

// Create and configure the agent registry
$registry = new AgentRegistry();
$registry->register('CodeLoader', new CodeLoaderAgent());
$registry->register('SecurityReviewer', new SecurityReviewerAgent());
$registry->register('PerformanceReviewer', new PerformanceReviewerAgent());
$registry->register('StyleReviewer', new StyleReviewerAgent());
$registry->register('TestReviewer', new TestReviewerAgent());
$registry->register('ReviewAggregator', new ReviewAggregatorAgent());
$registry->register('ReviewFinalizer', new ReviewFinalizerAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

// Test Case 1: Clean code (auto-approve)
echo "Test 1: Clean Code (Auto-Approve)\n";
echo str_repeat('-', 40) . "\n";

$result1 = $engine->run([
    'files' => ['src/UserController.php', 'tests/UserControllerTest.php'],
    'diff' => '+public function index() { return view("users"); }'
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Decision: {$output['decision']}\n";
    echo "Severity: {$output['severity']}\n";
    echo "Issues: {$output['totalIssues']}\n";
}

// Test Case 2: Code with issues
echo "\n\nTest 2: Code with Security Issues\n";
echo str_repeat('-', 40) . "\n";

$result2 = $engine->run([
    'files' => ['src/AuthController.php'],
    'diff' => '+$password = $_POST["password"]; eval($code);'
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Decision: {$output['decision']}\n";
    echo "Severity: {$output['severity']}\n";
    echo "Issues: {$output['totalIssues']}\n";
    echo "Summary: {$output['issuesSummary']}\n";
}

// Test Case 3: Performance issues
echo "\n\nTest 3: Performance Issues\n";
echo str_repeat('-', 40) . "\n";

$result3 = $engine->run([
    'files' => ['src/ReportService.php', 'tests/ReportServiceTest.php'],
    'diff' => '+foreach ($users as $user) { $orders = $user->query()->orders(); }'
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Decision: {$output['decision']}\n";
    echo "Severity: {$output['severity']}\n";
    echo "Issues: {$output['totalIssues']}\n";
}

echo "\n\n=== Workflow Execution Complete ===\n";
