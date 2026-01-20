# Customer Support Example

An intelligent customer support workflow with intent classification, memory persistence, and escalation handling.

## Features

- **Intent Classification** - Automatically routes messages to appropriate handlers
- **Memory Persistence** - Remembers customer history across interactions
- **Approval Flows** - Refunds over threshold require human approval
- **Escalation** - Complex issues automatically routed to human agents
- **Guardrails** - Ensures empathetic responses for complaints

## Workflow Diagram

```
LoadCustomerContext → ClassifyIntent → RouteByIntent
         ↓                                  ↓
    (load history)              ┌───────────┼───────────┐
                             Billing   Technical   Refund   Complaint   Sales
                                ↓          ↓          ↓         ↓        ↓
                                └──────────┴──────────┴─────────┴────────┘
                                                      ↓
                                          CheckNeedsEscalation
                                                ↓         ↓
                                          SaveInteraction  EscalateToHuman
                                                ↓
                                          FormatResponse
```

## Quick Start

```bash
# From the examples/customer-support directory
php run.php
```

## Expected Output

```
=== Customer Support Workflow Example ===

Test 1: Billing Inquiry
--------------------------------------------------
Response: I've looked up your billing information. Your last invoice was for $99...
Ticket: TKT-ABC123

Test 2: Refund Request
--------------------------------------------------
Response: Your refund of $47 has been processed successfully. You should see t...
Ticket: TKT-DEF456

Test 3: Technical Support
--------------------------------------------------
Response: I understand you're experiencing a technical issue. I've searched our...
Escalated: No

Test 4: Complaint Handling
--------------------------------------------------
Response: I'm truly sorry to hear about your experience. Your feedback is incre...
Escalated: Yes

Test 5: Sales Inquiry
--------------------------------------------------
Response: Great question about our products! We have several plans available: B...

=== Customer Support Workflow Complete ===
```

## Using in Your Project

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

// Register your agents
$registry = new AgentRegistry();
$registry->register('ContextLoader', new YourContextLoaderAgent());
$registry->register('IntentClassifier', new YourIntentClassifierAgent());
$registry->register('BillingAgent', new YourBillingAgentAgent());
$registry->register('TechnicalAgent', new YourTechnicalAgentAgent());
$registry->register('RefundAgent', new YourRefundAgentAgent());
$registry->register('RefundProcessor', new YourRefundProcessorAgent());
$registry->register('ComplaintAgent', new YourComplaintAgentAgent());
$registry->register('SalesAgent', new YourSalesAgentAgent());
$registry->register('GeneralAgent', new YourGeneralAgentAgent());
$registry->register('EscalationAgent', new YourEscalationAgentAgent());
$registry->register('ResponseGenerator', new YourResponseGeneratorAgent());
$registry->register('InteractionLogger', new YourInteractionLoggerAgent());

// Load and run the workflow
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'customerId' => 'cust_123',
    'message' => 'I want to request a refund for my last order'
]);

if ($result->isSuccess()) {
    echo $result->getOutput()['message'];
}
```

## Intent Categories

| Intent | Handler | Tools | Description |
|--------|---------|-------|-------------|
| Billing | BillingAgent | `lookup_invoice`, `check_payment_status` | Invoice and payment inquiries |
| Technical | TechnicalAgent | `search_knowledge_base`, `check_system_status` | Technical issues and bugs |
| Refund | RefundAgent | `lookup_order`, `check_refund_policy` | Return and refund requests |
| Complaint | ComplaintAgent | - (with empathy guardrails) | Customer complaints |
| Sales | SalesAgent | - | Product and pricing questions |
| General | GeneralAgent | - | Everything else |

## Refund Flow

The refund process includes automatic and manual approval paths:

```
RefundRequest → CheckRefundApproval
                       ↓
         ┌─────────────┼─────────────┐
    Auto-Approve    >$100         Default
         ↓             ↓              ↓
   ProcessRefund  RefundApproval  ProcessRefund
                       ↓
              ProcessRefundDecision
              ┌────────┼────────┐
           Approve  Partial   Deny
              ↓        ↓        ↓
        ProcessRefund  ProcessPartialRefund  RefundDenied
```

## Memory Persistence

The workflow saves customer interactions for personalization:

```json
{
  "Memory": {
    "Backend": "redis",
    "DefaultTTL": "30d"
  }
}
```

### Loading Context

```json
{
  "Memory": {
    "Read": {
      "Keys": ["customer_history", "preferences"],
      "InjectAt": "$.customerContext",
      "Default": { "isNew": true }
    }
  }
}
```

### Saving Interactions

```json
{
  "Memory": {
    "Write": {
      "Key": "customer_history",
      "Value.$": "$.interaction",
      "Merge": true
    }
  }
}
```

## Guardrails

Complaint handling includes empathy guardrails:

```json
{
  "Guardrails": {
    "Output": {
      "Rules": [
        { "Type": "semantic", "Check": "empathetic_tone" }
      ]
    }
  }
}
```

## Escalation Triggers

Issues are escalated to human agents when:

| Condition | Example |
|-----------|---------|
| `response.needsHuman = true` | Complex technical issues |
| `response.severity = "high"` | Legal threats, severe complaints |
| Agent explicitly requests | Data loss, security issues |

## Agents Required

| Agent | Purpose | Output |
|-------|---------|--------|
| ContextLoader | Load customer history | `{ isNew, customer }` |
| IntentClassifier | Determine message intent | `{ category, confidence }` |
| BillingAgent | Handle billing questions | `{ message, ticketId }` |
| TechnicalAgent | Handle tech support | `{ message, needsHuman }` |
| RefundAgent | Analyze refund eligibility | `{ amount, autoApprove }` |
| RefundProcessor | Process refunds | `{ message, refundId }` |
| ComplaintAgent | Handle complaints | `{ message, severity }` |
| SalesAgent | Handle sales inquiries | `{ message }` |
| GeneralAgent | General support | `{ message }` |
| EscalationAgent | Escalate to humans | `{ escalated, escalationId }` |
| ResponseGenerator | Generate templated responses | `{ message }` |
| InteractionLogger | Log interactions | `{ logged }` |

## Files

- `workflow.asl.json` - The ASL workflow definition
- `run.php` - Example runner with mock agents
- `README.md` - This documentation

## Related

- [Tutorial 3: Conditional Logic](../../docs/tutorials/03-conditional-logic.md)
- [Tutorial 6: Memory and Context](../../docs/tutorials/06-memory-and-context.md)
- [Tutorial 8: Human Approval](../../docs/tutorials/08-human-approval.md)
