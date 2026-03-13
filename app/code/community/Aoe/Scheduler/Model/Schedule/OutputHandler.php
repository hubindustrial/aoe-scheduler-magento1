<?php

declare(strict_types=1);

/**
 * @template T of Aoe_Scheduler_Model_MessagesStorage
 */
class Aoe_Scheduler_Model_Schedule_OutputHandler
{
    public const PHP_OUTPUT_HANDLER_FLAGS =
        PHP_OUTPUT_HANDLER_CLEANABLE |
        PHP_OUTPUT_HANDLER_FLUSHABLE |
        PHP_OUTPUT_HANDLER_REMOVABLE;

    /** @var null|T */
    protected null|Aoe_Scheduler_Model_MessagesStorage $messagesStorage;

    public function __construct(null|Aoe_Scheduler_Model_MessagesStorage $messagesStorage = null)
    {
        $this->messagesStorage = $messagesStorage;
    }

    /**
     * @see static::handleOutput()
     */
    public function __invoke(string $buffer, int $phase): string
    {
        return $this->handleOutput($buffer, $phase);
    }

    public function getFlags(): int
    {
        return static::PHP_OUTPUT_HANDLER_FLAGS;
    }

    /**
     * @return null|T
     */
    public function getMessagesStorage(): null|Aoe_Scheduler_Model_MessagesStorage
    {
        return $this->messagesStorage;
    }

    /**
     * @param null|T $messagesStorage
     * @return void
     */
    public function setMessagesStorage(null|Aoe_Scheduler_Model_MessagesStorage $messagesStorage): void
    {
        $this->messagesStorage = $messagesStorage;
    }

    /**
     * @see https://www.php.net/manual/en/outcontrol.user-level-output-buffers.php
     * @see https://www.php.net/manual/en/outcontrol.output-handlers.php
     * @see https://www.php.net/manual/en/outcontrol.working-with-output-handlers.php
     * @see https://www.php.net/manual/en/outcontrol.flags-passed-to-output-handlers.php
     * @see ob_start()
     */
    public function handleOutput(string $buffer, int $phase): string
    {
        if (($messageStorage = $this->messagesStorage) !== null) {
            $messages = $messageStorage->getMessages();
            $nextMessage = '';

            if ($phase & PHP_OUTPUT_HANDLER_START) {
                if ($messages === '') {
                    $nextMessage .= '---START---' . PHP_EOL;
                } else {
                    $nextMessage .= PHP_EOL . '---START---' . PHP_EOL;
                }
            }

            $nextMessage .= $buffer;

            if ($phase & PHP_OUTPUT_HANDLER_END) {
                if ($messages === '') {
                    // Should never happen, but code is cheap, so let's cover it anyway. ;)
                    $nextMessage .= '---END---' . PHP_EOL;
                } else {
                    $nextMessage .= PHP_EOL . '---END---' . PHP_EOL;
                }
            }

            if ($nextMessage !== '') {
                /*
                 * TODO: Should we handle exceptions on save?
                 */
                $messageStorage->setMessages($messages . $nextMessage);
                $messageStorage->saveMessages();
            }
        }

        return $buffer;
    }
}