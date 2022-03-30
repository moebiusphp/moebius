<?php
use Moebius\LoopInterface;
use Moebius\NativeEventLoop;
use Moebius\Promise;
use Moebius\CoroutineError;
use Moebius\PromiseRejectedError;
use Moebius\ThenableExpectedError;
use Moebius\Coroutine;
use Moebius\FiberTask;

final class Moebius {

    public static function import(string $nsName): void {
        if (self::$globalsTemplate === null) {
            $templateLines = explode("\n", strtr(file_get_contents(__DIR__.'/moebius.import.php'), [
                '} namespace Moebius\Functions {' => "\n",
            ]));
            array_shift($templateLines);
            self::$globalsTemplate = implode("\n", $templateLines);
        }

        eval($code = 'namespace '.$nsName.' {'.self::$globalsTemplate);
    }

    /**
     * Yield some time to allow other coroutines to proceed.
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
        } else {
            self::$loop->drain(function() { return true; });
        }
    }

    /**
     * Schedule a callback to be run some time in the future
     */
    public static function defer(callable $callback): void {
        self::$loop->defer($callback);
    }

    /**
     * Run a coroutine and await the result
     */
    public static function run(callable $coroutine, mixed ...$args): mixed {
        return self::await(self::go($coroutine, ...$args));
    }

    /**
     * Await a result from a promise.
     *
     * @param $promise A 'Thenable' object
     * @return mixed The resolved value from the promise
     * @throws Throwable
     */
    public static function await(object ...$thenable): mixed {
        $promise = Promise::all($thenable);
//        $promise = Promise::cast($thenable);

        if (!self::$loop->isDraining()) {
            self::$loop->drain(function() use ($promise) {
                return $promise->status() !== Promise::PENDING;
            });
        } else {
            do {
                self::yield();
            } while ($promise->status() === Promise::PENDING);
        }

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
     * Run a function as coroutine. When this coroutine is paused for some reason, other
     * coroutines can start processing.
     */
    public static function go(callable $callback, mixed ...$args): Promise {
        return Coroutine::create($callback, ...$args);
    }

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
     * Moebius can't be constructed. Use the static methods.
     */
    private function __construct() {}

    /**
     * Bootstrapping the coroutine environment.
     */
    public static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;
        self::$loop = new NativeEventLoop();
        Coroutine::bootstrap();
    }
    private static bool $bootstrapped = false;
    private static ?LoopInterface $loop = null;
    private static ?string $globalsTemplate = null;
}

/**
 * Prepare the Moebius loop, no sideeffects.
 */
Moebius::bootstrap();
