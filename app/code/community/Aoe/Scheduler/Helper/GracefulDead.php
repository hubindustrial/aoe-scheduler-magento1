<?php

declare(strict_types=1);

/**
 * Graceful Dead Helper
 *
 * @author Fabrizio Branca
 * @since 2015-07-02
 */
class Aoe_Scheduler_Helper_GracefulDead
{

    /**
     * Configure graceful dead
     *
     * Registers a shutdown function and signal handlers for best-effort
     * cleanup, including graceful termination of the currently running
     * schedule.
     *
     * NOTES:
     * - Shutdown functions run:
     *   - On normal script completion
     *   - After calls to `exit` or `die`
     *   - When PHP encounters a fatal error (E_ERROR), such as a type error
     *   - When a thrown {@link Throwable} propagates to the root execution
     *      context
     *   - When a previous shutdown function throws a {@link Throwable} or
     *      triggers a fatal error
     * - Shutdown functions do NOT run:
     *   - If a previous shutdown function calls `exit` or `die`
     *   - If the process was terminated externally (e.g., via SIGTERM signal)
     * - Signal handlers:
     *   - Do NOT terminate script execution
     *   - Can occur ANYWHERE with {@link pcntl_async_signals(true)}; even
     *      interrupting calls such as {@link sleep()}
     *   - Can NOT handle/catch SIGKILL
     *   - Can call `exit` or `die`, which WILL run shutdown functions as
     *      normal
     *   - Suppress the default signal handling (process termination), so
     *      if `exit` or `die` are NOT called by the signal handler, execution
     *      will resume exactly where it was when the signal was received
     */
    public static function configure(): void
    {
        static $configured = false;
        if (!$configured) {
            register_shutdown_function([self::class, 'beforeDyingShutdown']);
            if (
                extension_loaded('pcntl') &&
                function_exists('pcntl_async_signals') &&
                function_exists('pcntl_signal')
            ) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, [self::class, 'beforeDyingSigint']); // CTRL + C
                pcntl_signal(SIGTERM, [self::class, 'beforeDyingSigterm']); // kill <pid>
            }
            $configured = true;
        }
    }

    /**
     * @template T of bool
     * @param null|string $message
     * @param bool $exit **Deprecated:** Control flow should be handled locally
     * @psalm-return (T is true ? never : void)
     */
    public static function beforeDying($message = null, $exit = false): void
    {
        /* @var null|Aoe_Scheduler_Model_Schedule $schedule */
        $schedule = Mage::registry('currently_running_schedule');
        if ($schedule !== null) {
            $addedMessages = PHP_EOL . '---DIE---' . PHP_EOL;
            if ($message) {
                $addedMessages .= $message . PHP_EOL;
            }
            $schedule->addMessages($addedMessages);
            $schedule->die();
            Mage::unregister('currently_running_schedule');
        }

        if ($exit) {
            exit;
        }
    }

    /**
     * Callback
     */
    public static function beforeDyingShutdown(): void
    {
        $message = 'TRIGGER: shutdown function';

        $lastError = error_get_last();
        if ($lastError !== null &&
            $lastError['type'] & (
                E_ERROR |
                E_PARSE |
                E_CORE_ERROR |
                E_COMPILE_ERROR |
                E_USER_ERROR |
                E_RECOVERABLE_ERROR
            )
        ) {
            $message .= PHP_EOL
                . 'Last error: ' . PHP_EOL
                . print_r($lastError, true);
        }

        self::beforeDying($message);
    }

    /**
     * Callback
     */
    public static function beforeDyingSigint(): void
    {
        self::beforeDying('TRIGGER: Signal SIGINT');
        die;
    }

    /**
     * Callback
     */
    public static function beforeDyingSigterm(): void
    {
        self::beforeDying('TRIGGER: Signal SIGTERM');
        die;
    }
}
