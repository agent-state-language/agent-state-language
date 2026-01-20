<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown when budget limits are exceeded.
 */
class BudgetExceededException extends ASLException
{
    private float $budgetLimit;
    private float $currentUsage;

    public function __construct(
        string $message,
        float $budgetLimit,
        float $currentUsage,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->budgetLimit = $budgetLimit;
        $this->currentUsage = $currentUsage;
        parent::__construct($message, 'States.BudgetExceeded', $code, $previous);
    }

    public function getBudgetLimit(): float
    {
        return $this->budgetLimit;
    }

    public function getCurrentUsage(): float
    {
        return $this->currentUsage;
    }
}
