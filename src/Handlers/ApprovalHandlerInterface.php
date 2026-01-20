<?php

declare(strict_types=1);

namespace AgentStateLanguage\Handlers;

/**
 * Interface for handling approval requests in human-in-the-loop workflows.
 *
 * Implementations can integrate with external systems (queues, notifications, etc.)
 * to request and wait for human approval decisions.
 */
interface ApprovalHandlerInterface
{
    /**
     * Request approval from a human.
     *
     * @param array{
     *     prompt: string,
     *     options: array<string>,
     *     state: string,
     *     timeout?: string,
     *     editable?: array<string>,
     *     input: array<string, mixed>
     * } $request The approval request details
     *
     * @return array{
     *     approval: string,
     *     approver?: string,
     *     timestamp?: string,
     *     comment?: string,
     *     edited_content?: array<string, mixed>
     * }|null The approval decision, or null to pause execution and wait
     */
    public function requestApproval(array $request): ?array;
}
