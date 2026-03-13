<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'OutputHandlerTest' . DIRECTORY_SEPARATOR . 'ArrayMessagesStorage.php';

final class OutputHandlerTest extends TestCase
{
    private ArrayMessagesStorage $storage;
    private Aoe_Scheduler_Model_Schedule_OutputHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new ArrayMessagesStorage();
        $this->handler = new Aoe_Scheduler_Model_Schedule_OutputHandler($this->storage);
    }

    /**
     * @test
     */
    public function handleOutputReturnsBufferUnchanged(): void
    {
        $buffer = "Hello, world!\nLine 2.";

        self::assertSame($buffer, $this->handler->handleOutput($buffer, PHP_OUTPUT_HANDLER_START));
        self::assertSame($buffer, $this->handler->handleOutput($buffer, PHP_OUTPUT_HANDLER_END));
        self::assertSame($buffer, $this->handler->handleOutput($buffer, PHP_OUTPUT_HANDLER_WRITE));
        self::assertSame($buffer, $this->handler->handleOutput($buffer, 0));
        self::assertSame('', $this->handler->handleOutput('', PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_END));
    }

    /**
     * @test
     */
    public function messagesAccumulateAcrossMultipleCalls(): void
    {
        $this->handler->handleOutput('first', PHP_OUTPUT_HANDLER_START);
        $this->handler->handleOutput('second', PHP_OUTPUT_HANDLER_CONT);
        $this->handler->handleOutput('third', PHP_OUTPUT_HANDLER_END);

        $messages = $this->storage->getMessages();
        self::assertStringContainsString('first', $messages);
        self::assertStringContainsString('second', $messages);
        self::assertStringContainsString('third', $messages);

        // Order is preserved: "first" appears before "second" before "third"
        self::assertLessThan(
            strpos($messages, 'second'),
            strpos($messages, 'first'),
        );
        self::assertLessThan(
            strpos($messages, 'third'),
            strpos($messages, 'second'),
        );
    }

    /**
     * @test
     */
    public function saveMessagesCalledOncePerHandleOutputCall(): void
    {
        self::assertSame(0, $this->storage->getSaveCount());

        $this->handler->handleOutput('chunk1', PHP_OUTPUT_HANDLER_START);
        self::assertSame(1, $this->storage->getSaveCount());

        $this->handler->handleOutput('chunk2', 0);
        self::assertSame(2, $this->storage->getSaveCount());

        $this->handler->handleOutput('chunk3', PHP_OUTPUT_HANDLER_END);
        self::assertSame(3, $this->storage->getSaveCount());
    }

    /**
     * @test
     */
    public function nullStorageReturnsBufferUnmodified(): void
    {
        $this->handler->setMessagesStorage(null);

        self::assertSame('hello', $this->handler->handleOutput('hello', PHP_OUTPUT_HANDLER_START));
        self::assertSame('world', $this->handler->handleOutput('world', PHP_OUTPUT_HANDLER_END));
    }

    /**
     * @test
     */
    public function worksAsObStartCallback(): void
    {
        $originalLevel = ob_get_level();

        self::assertTrue(ob_start($this->handler, flags: $this->handler->getFlags()));

        self::assertSame($originalLevel + 1, ob_get_level());

        echo 'buffered output';

        ob_end_clean();

        self::assertSame($originalLevel, ob_get_level());
        self::assertStringContainsString('buffered output', $this->storage->getMessages());
        self::assertGreaterThan(0, $this->storage->getSaveCount());
    }
}
