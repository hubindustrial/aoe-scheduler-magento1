<?php

declare(strict_types=1);

/**
 * Simple in-memory implementation of MessagesStorage for testing.
 */
final class ArrayMessagesStorage implements Aoe_Scheduler_Model_MessagesStorage
{
    private string $messages = '';
    private int $saveCount = 0;

    public function getSaveCount(): int
    {
        return $this->saveCount;
    }

    public function getMessages(): string
    {
        return $this->messages;
    }

    public function setMessages(string $value): void
    {
        $this->messages = $value;
    }

    public function saveMessages(): void
    {
        $this->saveCount++;
    }
}
