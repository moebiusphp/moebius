<?php
namespace Moebius;

/**
 * This interface defines all the functionality needed to work
 * with an efficient event loop.
 */
interface LoopInterface {
    /**
     * Runs the event loop until the $doneCallback returns true.
     */
    public function drain(callable $doneCallback): void;

    /**
     * Is the loop currently draining?
     */
    public function isDraining(): bool;

    /**
     * Add this callback to the end of the event loop. If the
     * event loop has not been started, ensure it will start.
     */
    public function defer(callable $callback): void;

    /**
     * Run this callback when reading this stream will not
     * block.
     *
     * @param resource $stream          The stream to watch
     * @param callable $callback        The callback to invoke
     */
    public function addReadStream($stream, callable $callback): void;

    /**
     * Remove the on readable callback for a stream
     *
     * @param resource $stream          The stream to unwatch
     * @param callable $callback        The callback to invoke
     */
    public function removeReadStream($stream, callable $callback): void;

    /**
     * Run this callback when writing to this stream will not
     * block.
     *
     * @param resource $stream          The stream to watch
     * @param callable $callback        The callback to invoke
     */
    public function addWriteStream($stream, callable $callback): void;

    /**
     * Remove the on writable callback for a stream
     *
     * @param resource $stream          The stream to unwatch
     * @param callable $callback        The callback to invoke
     */
    public function removeWriteStream($stream, callable $callback): void;

    /**
     * Run this callback when the process receives a signal
     *
     * @param int $signal           The signal number to listen for
     * @param callable $callback    The callback to run
     */
    public function addSignal(int $signal, callable $callback);

    /**
     *
     * @param int $signal           The signal number to listen for
     * @param callable $callback    The callback to run
     * Remove a signal handler
     */
    public function removeSignal(int $signal, callable $callback);
}
