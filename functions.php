<?php
namespace M;

use Moebius as M;
use Moebius\Promise;

/**
 * Launch a coroutine.
 *
 * @param callable $func The function to run as in a coroutine.
 * @param mixed ...$args Any arguments to the coroutine function
 * @return Promise A promise that will be resolved or rejected
 */
function go(callable $func, mixed ...$args): Promise {
    return M::go($func, ...$args);
}

/**
 * Wait until a promise has been resolved
 *
 * @param object ...$promises Promises to synchronously wait for
 * @return Promise[] The resolved promises
 */
function await(object ...$promises): array {
    return M::await(...$promises);
}

/**
 * Launch a coroutine and wait for it to complete.
 *
 * @param callable $func The function to run as in a coroutine.
 * @param mixed ...$args Any arguments to the coroutine function
 */
function run(callable $func, mixed ...$args): mixed {
    return M::run($func, ...$args);
}

/**
 * Schedule a function to be invoked at a later time
 *
 * @param callable $func The function to enqueue
 */
function defer(callable $func): void {
    M::defer($func);
}

/**
 * Pause for a number of seconds - same as the built-in `sleep()`
 * function.
 */
function sleep(float $seconds): int {
    return M::sleep($seconds);
}

/**
 * Pause for a number of microseconds - not as precise as the built-in
 * `usleep()` function.
 */
function usleep(int $useconds): void {
    M::sleep($useconds / 1000000);
}
