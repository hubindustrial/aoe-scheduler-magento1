<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'GracefulDeadTest' . DIRECTORY_SEPARATOR . 'FakeJob.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'GracefulDeadTest' . DIRECTORY_SEPARATOR . 'FakeSchedule.php';

/**
 * Process-level tests proving that GracefulDead properly handles POSIX signals
 * and calls to exit/die.
 */
final class GracefulDeadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pcntl')) {
            self::markTestSkipped('ext-pcntl is required for signal delivery tests.');
        }
    }

    /**
     * This mostly tests our own test code, but it's still potentially helpful
     *
     * @test
     */
    public function childCompletionDoesNotSetDieStatus(): void
    {
        $schedule = self::createScheduledJob(function () {
            // no op
        });

        try {
            $pid = self::runScheduleInChildProcess($schedule);

            pcntl_waitpid($pid, $status);
            $schedule->load($schedule->getId());

            self::assertSame(Aoe_Scheduler_Model_Schedule::STATUS_SUCCESS, $schedule->getStatus());
        } finally {
            $schedule->delete();
        }
    }

    /**
     * @test
     */
    public function sigtermMarksScheduleAsDied(): void
    {
        $schedule = self::createScheduledJob(function () {
            sleep(30);
        });

        try {
            $pid = self::runScheduleInChildProcess($schedule);

            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            $schedule->load($schedule->getId());

            self::assertSame(Aoe_Scheduler_Model_Schedule::STATUS_DIED, $schedule->getStatus());
        } finally {
            $schedule->delete();
        }
    }

    /**
     * @test
     */
    public function sigintMarksScheduleAsDied(): void
    {
        $schedule = self::createScheduledJob(function () {
            sleep(30);
        });

        try {
            $pid = self::runScheduleInChildProcess($schedule);

            posix_kill($pid, SIGINT);
            pcntl_waitpid($pid, $status);
            $schedule->load($schedule->getId());

            self::assertSame(Aoe_Scheduler_Model_Schedule::STATUS_DIED, $schedule->getStatus());
        } finally {
            $schedule->delete();
        }
    }

    /**
     * When a job callback calls exit() directly, the shutdown function
     * should detect the still-registered schedule and mark it as died.
     *
     * @test
     */
    public function exitInJobCallbackMarksScheduleAsDied(): void
    {
        $schedule = self::createScheduledJob(function () {
            // Simulate a job callback that calls exit() directly.
            exit(1);
        });

        try {
            $pid = self::runScheduleInChildProcess($schedule);

            pcntl_waitpid($pid, $status);
            $schedule->load($schedule->getId());

            self::assertSame(1, pcntl_wexitstatus($status));
            self::assertSame(Aoe_Scheduler_Model_Schedule::STATUS_DIED, $schedule->getStatus());
        } finally {
            $schedule->delete();
        }
    }

    private static function createScheduledJob(callable $callback): Aoe_Scheduler_Model_Schedule
    {
        static $jobId = 0;
        $schedule = (new FakeSchedule())
            ->setJob(
                (new FakeJob())
                    ->setId((string)$jobId++)
                    ->setCallback($callback),
            )
            ->save();

        if ($schedule->getStatus() !== Aoe_Scheduler_Model_Schedule::STATUS_PENDING) {
            self::fail('Newly created schedule should have PENDING status');
        }

        return $schedule;
    }

    /**
     * Fork a child process, run the given schedule in the child process and return the
     * child PID to the parent.
     *
     * @return int child PID
     */
    private static function runScheduleInChildProcess(Aoe_Scheduler_Model_Schedule $schedule): int
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('pcntl_fork() failed');
        } elseif ($pid === 0) {
            // Child process. Run the work and exit cleanly.
            $schedule->runNow(tryLockJob: false, forceRun: true);
            exit;
        } else {
            // Parent process. Wait for schedule to change status, then return the child PID.
            self::waitForNonPendingStatus($schedule);
            return $pid;
        }
    }

    private static function waitForNonPendingStatus(Aoe_Scheduler_Model_Schedule $schedule, int $timeout = 30): void
    {
        $sleepMicros = 10_000;
        $maxAttempts = $timeout * 1_000_000 / $sleepMicros;

        for ($nAttempts = 0; $nAttempts < $maxAttempts; $nAttempts++) {
            $schedule->load($schedule->getId());
            if ($schedule->getStatus() !== Aoe_Scheduler_Model_Schedule::STATUS_PENDING) {
                return;
            }
            usleep($sleepMicros);
        }

        self::fail('Timeout');
    }
}
