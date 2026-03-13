<?php

declare(strict_types=1);

interface Aoe_Scheduler_Model_MessagesStorage
{
    public function getMessages(): string;

    /**
     * @param string $value
     * @return void
     */
    public function setMessages(string $value);

    /**
     * @return void
     * @throws Throwable
     */
    public function saveMessages();
}