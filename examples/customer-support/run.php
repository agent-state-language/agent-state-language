<?php

/**
 * Customer Support Example Runner
 * 
 * This script demonstrates how to run the customer support workflow.
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
 * Loads customer context from memory
 */
class ContextLoaderAgent implements AgentInterface
{
    private static array $customerData = [
        'cust_123' => [
            'name' => 'John Smith',
            'tier' => 'premium',
            'since' => '2023-01-15',
            'totalOrders' => 47
        ],
        'cust_456' => [
            'name' => 'Jane Doe',
            'tier' => 'standard',
            'since' => '2024-06-01',
            'totalOrders' => 3
        ]
    ];

    public function execute(array $parameters): array
    {
        $customerId = $parameters['customerId'] ?? '';
        
        if (isset(self::$customerData[$customerId])) {
            return [
                'isNew' => false,
                'customer' => self::$customerData[$customerId],
                'loadedAt' => date('c')
            ];
        }
        
        return [
            'isNew' => true,
            'customer' => null,
            'loadedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ContextLoader';
    }
}

/**
 * Classifies customer intent from message
 */
class IntentClassifierAgent implements AgentInterface
{
    private array $keywords = [
        'billing' => ['invoice', 'bill', 'charge', 'payment', 'receipt'],
        'technical' => ['error', 'bug', 'crash', 'not working', 'broken', 'help with'],
        'refund' => ['refund', 'money back', 'return', 'cancel order'],
        'complaint' => ['unhappy', 'terrible', 'awful', 'complaint', 'frustrated', 'angry'],
        'sales' => ['buy', 'purchase', 'pricing', 'discount', 'upgrade', 'plan']
    ];

    public function execute(array $parameters): array
    {
        $message = strtolower($parameters['message'] ?? '');
        $context = $parameters['customerContext'] ?? [];
        
        $category = 'general';
        $confidence = 0.5;
        
        foreach ($this->keywords as $intent => $words) {
            foreach ($words as $word) {
                if (strpos($message, $word) !== false) {
                    $category = $intent;
                    $confidence = 0.85;
                    break 2;
                }
            }
        }
        
        return [
            'category' => $category,
            'confidence' => $confidence,
            'originalMessage' => $parameters['message'] ?? '',
            'classifiedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'IntentClassifier';
    }
}

/**
 * Handles billing inquiries
 */
class BillingAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $message = $parameters['message'] ?? '';
        $customerId = $parameters['customerId'] ?? '';
        
        return [
            'message' => "I've looked up your billing information. Your last invoice was for $99.00 on January 15th. All payments are up to date. Is there anything specific about your billing you'd like to know?",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'needsHuman' => false,
            'category' => 'billing'
        ];
    }

    public function getName(): string
    {
        return 'BillingAgent';
    }
}

/**
 * Handles technical support
 */
class TechnicalAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $message = $parameters['message'] ?? '';
        
        $needsHuman = stripos($message, 'crash') !== false || 
                      stripos($message, 'data loss') !== false;
        
        return [
            'message' => "I understand you're experiencing a technical issue. I've searched our knowledge base and found some relevant troubleshooting steps. Have you tried clearing your cache and restarting the application?",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'needsHuman' => $needsHuman,
            'category' => 'technical'
        ];
    }

    public function getName(): string
    {
        return 'TechnicalAgent';
    }
}

/**
 * Handles refund requests
 */
class RefundAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $message = $parameters['message'] ?? '';
        $customerId = $parameters['customerId'] ?? '';
        
        // Simulate looking up order
        $amount = rand(25, 150);
        $autoApprove = $amount <= 50;
        
        return [
            'orderId' => 'ORD-' . strtoupper(substr(md5($message), 0, 8)),
            'amount' => $amount,
            'autoApprove' => $autoApprove,
            'reason' => 'Customer requested refund',
            'eligible' => true,
            'policyCheck' => 'passed'
        ];
    }

    public function getName(): string
    {
        return 'RefundAgent';
    }
}

/**
 * Processes refunds
 */
class RefundProcessorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $customerId = $parameters['customerId'] ?? '';
        $amount = $parameters['amount'] ?? 0;
        
        return [
            'message' => "Your refund of \${$amount} has been processed successfully. You should see the funds in your account within 5-7 business days.",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'refundId' => 'REF-' . strtoupper(uniqid()),
            'amount' => $amount,
            'needsHuman' => false
        ];
    }

    public function getName(): string
    {
        return 'RefundProcessor';
    }
}

/**
 * Handles complaints
 */
class ComplaintAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $message = $parameters['message'] ?? '';
        
        // Determine severity
        $severity = 'low';
        if (stripos($message, 'legal') !== false || stripos($message, 'lawyer') !== false) {
            $severity = 'high';
        } elseif (stripos($message, 'terrible') !== false || stripos($message, 'worst') !== false) {
            $severity = 'medium';
        }
        
        return [
            'message' => "I'm truly sorry to hear about your experience. Your feedback is incredibly important to us, and I want to make sure we address your concerns properly. Let me look into this for you right away.",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'severity' => $severity,
            'needsHuman' => $severity === 'high'
        ];
    }

    public function getName(): string
    {
        return 'ComplaintAgent';
    }
}

/**
 * Handles sales inquiries
 */
class SalesAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        return [
            'message' => "Great question about our products! We have several plans available: Basic ($29/mo), Professional ($79/mo), and Enterprise (custom pricing). Would you like me to explain the features of each plan?",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'needsHuman' => false,
            'category' => 'sales'
        ];
    }

    public function getName(): string
    {
        return 'SalesAgent';
    }
}

/**
 * Handles general inquiries
 */
class GeneralAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        return [
            'message' => "Thank you for reaching out! I'm here to help. Could you please provide more details about what you need assistance with?",
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'needsHuman' => false,
            'category' => 'general'
        ];
    }

    public function getName(): string
    {
        return 'GeneralAgent';
    }
}

/**
 * Handles escalation to human agents
 */
class EscalationAgentAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $customerId = $parameters['customerId'] ?? '';
        
        return [
            'escalated' => true,
            'escalationId' => 'ESC-' . strtoupper(uniqid()),
            'message' => "I've escalated your case to a senior support specialist who will contact you within 2 hours. Your escalation ID is above for reference.",
            'priority' => 'high'
        ];
    }

    public function getName(): string
    {
        return 'EscalationAgent';
    }
}

/**
 * Generates templated responses
 */
class ResponseGeneratorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $template = $parameters['template'] ?? 'default';
        $reason = $parameters['reason'] ?? '';
        
        $messages = [
            'refund_denied' => "I apologize, but we're unable to process your refund request at this time. {$reason}. If you believe this decision should be reconsidered, please reply and we'll have a supervisor review your case.",
            'default' => "Thank you for contacting us. We'll get back to you shortly."
        ];
        
        return [
            'message' => $messages[$template] ?? $messages['default'],
            'ticketId' => 'TKT-' . strtoupper(uniqid()),
            'needsHuman' => false
        ];
    }

    public function getName(): string
    {
        return 'ResponseGenerator';
    }
}

/**
 * Logs interactions
 */
class InteractionLoggerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        return [
            'logged' => true,
            'interactionId' => 'INT-' . strtoupper(uniqid()),
            'loggedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'InteractionLogger';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

echo "=== Customer Support Workflow Example ===\n\n";

// Create and configure the agent registry
$registry = new AgentRegistry();
$registry->register('ContextLoader', new ContextLoaderAgent());
$registry->register('IntentClassifier', new IntentClassifierAgent());
$registry->register('BillingAgent', new BillingAgentAgent());
$registry->register('TechnicalAgent', new TechnicalAgentAgent());
$registry->register('RefundAgent', new RefundAgentAgent());
$registry->register('RefundProcessor', new RefundProcessorAgent());
$registry->register('ComplaintAgent', new ComplaintAgentAgent());
$registry->register('SalesAgent', new SalesAgentAgent());
$registry->register('GeneralAgent', new GeneralAgentAgent());
$registry->register('EscalationAgent', new EscalationAgentAgent());
$registry->register('ResponseGenerator', new ResponseGeneratorAgent());
$registry->register('InteractionLogger', new InteractionLoggerAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

// Test Case 1: Billing inquiry
echo "Test 1: Billing Inquiry\n";
echo str_repeat('-', 50) . "\n";

$result1 = $engine->run([
    'customerId' => 'cust_123',
    'message' => 'Can you send me my latest invoice?'
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Response: " . substr($output['message'] ?? 'N/A', 0, 80) . "...\n";
    echo "Ticket: " . ($output['ticketId'] ?? 'N/A') . "\n";
}

// Test Case 2: Refund request (small amount - auto-approved)
echo "\n\nTest 2: Refund Request\n";
echo str_repeat('-', 50) . "\n";

$result2 = $engine->run([
    'customerId' => 'cust_123',
    'message' => 'I want a refund for my last order'
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Response: " . substr($output['message'] ?? 'N/A', 0, 80) . "...\n";
    echo "Ticket: " . ($output['ticketId'] ?? 'N/A') . "\n";
}

// Test Case 3: Technical issue
echo "\n\nTest 3: Technical Support\n";
echo str_repeat('-', 50) . "\n";

$result3 = $engine->run([
    'customerId' => 'cust_456',
    'message' => 'The application is not working and shows an error'
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Response: " . substr($output['message'] ?? 'N/A', 0, 80) . "...\n";
    echo "Escalated: " . (($output['escalated'] ?? false) ? 'Yes' : 'No') . "\n";
}

// Test Case 4: Complaint (high severity)
echo "\n\nTest 4: Complaint Handling\n";
echo str_repeat('-', 50) . "\n";

$result4 = $engine->run([
    'customerId' => 'cust_123',
    'message' => 'This is terrible service! I am very frustrated and considering legal action!'
]);

if ($result4->isSuccess()) {
    $output = $result4->getOutput();
    echo "Response: " . substr($output['message'] ?? 'N/A', 0, 80) . "...\n";
    echo "Escalated: " . (($output['escalated'] ?? false) ? 'Yes' : 'No') . "\n";
}

// Test Case 5: Sales inquiry
echo "\n\nTest 5: Sales Inquiry\n";
echo str_repeat('-', 50) . "\n";

$result5 = $engine->run([
    'customerId' => 'cust_456',
    'message' => 'What are your pricing options? I want to upgrade'
]);

if ($result5->isSuccess()) {
    $output = $result5->getOutput();
    echo "Response: " . substr($output['message'] ?? 'N/A', 0, 80) . "...\n";
}

echo "\n\n=== Customer Support Workflow Complete ===\n";
