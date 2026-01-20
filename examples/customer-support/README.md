# Customer Support Example

An intelligent customer support workflow with intent classification and routing.

## Features

- **Intent Classification** - Routes to appropriate handler
- **Memory** - Remembers customer history
- **Approval Flows** - Refunds over threshold require approval
- **Escalation** - Complex issues routed to humans
- **Guardrails** - Ensures empathetic responses for complaints

## Intent Categories

| Intent | Handler | Tools |
|--------|---------|-------|
| Billing | BillingAgent | invoice lookup, payment status |
| Technical | TechnicalAgent | knowledge base, system status |
| Refund | RefundAgent | order lookup, policy check |
| Complaint | ComplaintAgent | (with empathy guardrails) |
| Sales | SalesAgent | product info |
| Other | GeneralAgent | general support |

## Workflow

```
LoadCustomerContext → ClassifyIntent → RouteByIntent
         ↓                                  ↓
    (load history)              ┌───────────┼───────────┐
                             Billing   Technical   Refund
                                ↓          ↓          ↓
                           CheckNeedsEscalation → SaveInteraction
                                ↓
                          EscalateToHuman (if needed)
```

## Usage

```php
$engine = WorkflowEngine::fromFile('workflow.asl.json', $registry);

$result = $engine->run([
    'customerId' => 'cust_123',
    'message' => 'I want to request a refund for my last order'
]);

echo $result->getOutput()['message'];
```

## Memory Persistence

The workflow saves customer interactions:

```json
{
  "Memory": {
    "Write": {
      "Key": "customer_history",
      "Merge": true,
      "TTL": "30d"
    }
  }
}
```

This enables personalized future interactions.
