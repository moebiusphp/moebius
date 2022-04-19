<?php
use Moebius\LoopInterface;
use Moebius\NativeEventLoop;
use Moebius\Promise;
use Moebius\PromiseRejectedError;
use Moebius\PromiseCancelledError;
use Moebius\Coroutine;
use Moebius\ThenableExpectedError;
use Moebius\FileStreamWrapper;
use Moebius\HttpStreamWrapper;
use Moebius\HttpsStreamWrapper;

/**
 * Utility functions for working with asynchronous, non-blocking PHP code - without having
 * to think about it at all.
 */
final class Moebius {

    /**
     * Launch a coroutine which will run asynchronously. The function can be combined
     * with multiple coroutines using the {$see Moebius::await} function.
     *
     * @returns Promise
     */
    public static function go(callable $callback, mixed ...$args): Promise {
        return Coroutine::create($callback, ...$args);
    }

    /**
     * Wait until the coroutines have finished running. This can be combined with
     * the {@see Moebius::go} function.
     *
     * @param $promise A 'Thenable' object
     * @return mixed The resolved value from the promise
     * @throws Throwable
     */
    public static function await(object ...$thenable): mixed {
        // Micro-optimization. Shouldn't have any side-effects.
        if (count($thenable) === 1) {
            $promise = Promise::cast($thenable[0]);
        } else {
            $promise = Promise::all($thenable);
        }

        do {
            self::yield();
        } while ($promise->status() === Promise::PENDING);

        switch ($promise->status()) {
            case Promise::REJECTED:
                if ($promise->reason() instanceof \Throwable) {
                    throw $promise->reason();
                } else {
                    throw new PromiseRejectedError($promise->reason());
                }
                break;
            case Promise::FULFILLED:
                return $promise->value();
                break;
            case Promise::CANCELLED:
                throw new PromiseCancelledError();
                break;
        }
        throw new MoebiusException("Promise is neither fulfilled, rejected or cancelled");
    }

    /**
     * Provide an opportunity for Moebius to pause your busy PHP code and allow
     * the rest of the application to continue.
     *
     * It should normally not be neccesary to invoke this function.
     */
    public static function check(): void {
        Coroutine::check();
    }

    /**
     * Sleep until the stream resource becomes readable while allowing other
     * coroutines to make progress in the meantime.
     *
     * @param resource $stream The stream resource 
     */
    public static function writable($stream): void {
        $p = new Promise();
        self::$loop->addWriteStream($stream, $p->resolve(...));
        self::await($p);
        self::$loop->removeWriteStream($stream, $p->resolve(...));
    }

    /**
     * Sleep until the stream resource becomes writable while allowing other
     * coroutines to make progress in the meantime.
     *
     * @param resource $stream The stream resource 
     */
    public static function readable($stream): void {
        $p = new Promise();
        self::$loop->addReadStream($stream, $p->resolve(...));
        self::await($p);
        self::$loop->removeReadStream($stream, $p->resolve(...));
    }

    /**
     * Sleep for a number of seconds while allowing other coroutines to
     * make progress in the meantime.
     *
     * @param float $time=0 Number of seconds to sleep.
     */
    public static function sleep(float $time=0): void {
        // @TODO Could reduce CPU usage a bit more with better integration with the event loop
        $until = microtime(true) + $time;
        // ensure at least one yield is performed
        self::yield();
        while (microtime(true) < $until) {
            self::yield();
        }
    }

    /**
     * Advanced usage. Schedule a function call to run in the event loop, similar to
     * the queueMicrotask() function in javascript. It is generally better to launch
     * a coroutine using {see Moebius::go()}.
     *
     * This function exists to enable integration with other event loop
     * implementations.
     *
     * @param callable $callback The callback to enqueue
     */
    public static function defer(callable $callback): void {
        self::$loop->defer($callback);
    }

    /**
     * Advanced usage. This function will let the event loop run one iteration.
     * In general, there should be no need to run this function yourself and it
     * might be removed in the future.
     *
     * @internal
     */
    public static function yield(): void {
        if (self::$loop->isDraining()) {
            if (Coroutine::getCurrent()) {
                Coroutine::suspend();
            } elseif (Fiber::getCurrent()) {
                Fiber::suspend();
            } else {
                // Yield some time to the operating system, don't want to busy-wait
                usleep(10);
            }
        } elseif (Coroutine::getCurrent()) {
            Coroutine::suspend();
        } else {
            self::$loop->drain(function() { return true; });
        }
    }

    /**
     * Handle the exception which was thrown in a coroutine or on the event-loop.
     * This function exists to allow future hooking into the exception handler
     * to customize error logging.
     *
     * @internal
     * @param \Throwable $e The exception to log
     */
    public static function logException(\Throwable $e): void {
        fwrite(STDERR, gmdate('Y-m-d H:i:s')." ".$e->getMessage()." in ".$e->getFile().":".$e->getLine()."\n".$e->getTraceAsString()."\n");
    }


    /**
     * Moebius can't be constructed. Use the static methods.
     */
    private function __construct() {}

    /**
     * This function exists because PHP does not allow static initialization of properties.
     * Since multiple instances of Moebius can't run simultaneously, we are using static
     * properties.
     */
    public static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;
        self::$loop = new NativeEventLoop();
        Coroutine::bootstrap();
        class_exists(Promise::class); // ensure Promise is loaded before FileStreamWrapper starts
// Work in progress
//        HttpStreamWrapper::register();
//        HttpsStreamWrapper::register();
        FileStreamWrapper::register();
    }
    private static bool $bootstrapped = false;
    private static ?LoopInterface $loop = null;
    private static ?string $globalsTemplate = null;
}

/**
 * Prepare the Moebius loop. This call has no external side-effects.
 */
Moebius::bootstrap();
