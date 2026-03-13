<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ScheduleOutputBufferingTest' . DIRECTORY_SEPARATOR . 'TestableOutputBufferControlsSchedule.php';

/**
 * Tests for _startBufferToMessages() / _stopBufferToMessages() on Aoe_Scheduler_Model_Schedule.
 */
final class ScheduleOutputBufferingTest extends TestCase
{
    private null|array|string $originalEnableJobOutputBufferConfig;
    private int $originalObLevel;
    private TestableOutputBufferControlsSchedule $schedule;

    public static function setUpBeforeClass(): void
    {
        Mage::app();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Wrap each test in an extra layer of buffering so that output is not printed to test runner
        ob_start();

        $this->originalEnableJobOutputBufferConfig = self::getJobOutputBufferingConfig();
        $this->originalObLevel = ob_get_level();
        $this->schedule = new TestableOutputBufferControlsSchedule();
    }

    protected function tearDown(): void
    {
        self::setJobOutputBufferingConfig($this->originalEnableJobOutputBufferConfig);

        // Clean up any remaining output buffers, including from our setUp()
        while (ob_get_level() >= $this->originalObLevel) {
            if (!ob_end_clean()) {
                throw new RuntimeException();
            }
        }

        parent::tearDown();
    }

    private static function getJobOutputBufferingConfig(): null|array|string
    {
        return Mage::app()->getStore()->getConfig('system/cron/enableJobOutputBuffer');
    }

    private static function setJobOutputBufferingConfig(null|array|string $value): void
    {
        Mage::app()->getStore()->setConfig('system/cron/enableJobOutputBuffer', $value);
    }

    private static function enableJobOutputBuffering(): void
    {
        self::setJobOutputBufferingConfig('1');
    }

    private static function disableJobOutputBuffering(): void
    {
        self::setJobOutputBufferingConfig('0');
    }

    /**
     * @test
     */
    public function startIncreasesObLevelAndStopRestoresIt(): void
    {
        self::enableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        self::assertSame($this->originalObLevel + 1, ob_get_level());
        self::assertTrue($this->schedule->isBufferingOutput());
        self::assertFalse($this->schedule->isNotBufferingOutput());

        $this->schedule->_stopBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }

    /**
     * @test
     */
    public function outputDuringBufferingFlowsToMessages(): void
    {
        self::enableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        echo 'test output';
        $this->schedule->_stopBufferToMessages();

        self::assertStringContainsString('test output', $this->schedule->getMessages());
    }

    /**
     * @test
     */
    public function doubleStartDoesNotDoubleBuffer(): void
    {
        self::enableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        $this->schedule->_startBufferToMessages();
        self::assertSame($this->originalObLevel + 1, ob_get_level());
        self::assertTrue($this->schedule->isBufferingOutput());
        self::assertFalse($this->schedule->isNotBufferingOutput());

        $this->schedule->_stopBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }

    /**
     * @test
     */
    public function stopWithoutStartIsNoOp(): void
    {
        self::enableJobOutputBuffering();

        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());

        $this->schedule->_stopBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }

    /**
     * @test
     */
    public function startIsNoOpWhenConfigDisabled(): void
    {
        self::disableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }

    /**
     * @test
     */
    public function stopUnwindsBuffersPushedByOtherCode(): void
    {
        self::enableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        self::assertSame($this->originalObLevel + 1, ob_get_level());
        self::assertTrue($this->schedule->isBufferingOutput());
        self::assertFalse($this->schedule->isNotBufferingOutput());

        ob_start(); // extra buffer pushed by "other code"
        self::assertSame($this->originalObLevel + 2, ob_get_level());

        $this->schedule->_stopBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }

    /**
     * @test
     */
    public function stopHandlesBufferRemovedByOtherCode(): void
    {
        self::enableJobOutputBuffering();

        $this->schedule->_startBufferToMessages();
        self::assertSame($this->originalObLevel + 1, ob_get_level());
        self::assertTrue($this->schedule->isBufferingOutput());
        self::assertFalse($this->schedule->isNotBufferingOutput());

        ob_end_clean(); // externally remove our buffer
        self::assertSame($this->originalObLevel, ob_get_level());

        $this->schedule->_stopBufferToMessages();
        self::assertSame($this->originalObLevel, ob_get_level());
        self::assertFalse($this->schedule->isBufferingOutput());
        self::assertTrue($this->schedule->isNotBufferingOutput());
    }
}
