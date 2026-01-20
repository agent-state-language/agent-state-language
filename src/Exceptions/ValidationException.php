<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown when workflow validation fails.
 */
class ValidationException extends ASLException
{
    /** @var array<string> */
    private array $errors;

    /**
     * @param array<string> $errors
     */
    public function __construct(
        string $message,
        array $errors = [],
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct($message, 'States.ValidationError', $code, $previous);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
