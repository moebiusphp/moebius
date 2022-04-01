<?php
namespace Moebius;

use Moebius;
use Countable;
use Fiber;
use const SIG_DFL, SIGTERM, SIGINT;
use function pcntl_signal, pcntl_async_signals, array_filter, count, stream_select;
use function register_shutdown_function;

/**
 * Abstract class providing the stream based functionality
 * for a simple event loop, used when there are no other
 * event loop implementation available.
 */
class NativeEventLoop implements LoopInterface, Countable {

    public function __construct() {
        $this->readableStreamListeners = [];
        $this->writableStreamListeners = [];
        $this->lastSleepTime = microtime(true);
        pcntl_async_signals(true);
        register_shutdown_function($this->onShutdown(...));
    }

    public function count(): int {
        return $this->queueHead - $this->queueTail;
    }

    /**
     * All callbacks scheduled to run
     */
    private array $queue = [];

    /**
     * Record the index of the next tick function in the queue,
     * much faster than using array_shift.
     */
    private int $queueTail = 0;

    /**
     * Record the index of the next unused tick function offset
     * in the queue, much faster than using array_shift.
     */
    private int $queueHead = 0;

    /**
     * Records the last time we allowed the OS to run some cycles
     */
    private int $lastSleepTime = 0;

    /**
     * Is the event loop going to run until it is empty?
     */
    private bool $draining = false;

    /**
     * If the event loop is terminating, we won't allow any new tasks
     * to be scheduled with the \register_shutdown_function().
     */
    private bool $terminating = false;

    /**
     * Runs the event loop until the $doneCallback returns true.
     */
    public function drain(callable $doneCallback): void {
        if ($this->isDraining()) {
            throw new \Exception("Loop is already draining, so you should not be trying to drain it... Fix your code!");
        }
        $this->draining = true;
        try {
            do {
                $this->tick();
            } while (!$doneCallback());
            $this->draining = false;
        } catch (\Throwable $e) {
            $this->draining = false;
            throw $e;
        }
    }

    public function isDraining(): bool {
        return $this->draining;
    }


    /**
     * Schedule a callback function to run at the first opportunity
     */
    public function defer(callable $callback): void {
        $this->queue[$this->queueHead++] = $callback;
    }

    /**
     * Run one iteration of the tick functions we currently have in the queue.
     */
    private function tick(): int {
        if (Fiber::getCurrent()) {
            throw new \Exception("Tick invoked from inside a fiber");
        }
        $counter = 0;
        $stopIndex = $this->queueHead;

        $startTime = microtime(true);
        while ($this->queueTail < $stopIndex) {
            $callback = $this->queue[$this->queueTail];
            unset($this->queue[$this->queueTail++]);
            $counter++;
            try {
                $callback();
            } catch (\Throwable $e) {
                $this->logException($e);
            }
        }
        $tickTime = (1000000 * (microtime(true) - $startTime)) | 0;
        if ($tickTime < 1000) {
            usleep(max(0, (1000 - $tickTime) - 100));
        }
        return $counter;
    }

    /**
     * Run just a signle callback function from the queue, ignoring any
     * signal dispatching and CPU throttling.
     */
    private function microTick(): int {
        if (Fiber::getCurrent()) {
            throw new \Exception("Tick invoked from inside a fiber");
        }
        if ($this->queueTail === $this->queueHead) {
            return 0;
        }
        $callback = $this->queue[$this->queueTail];
        unset($this->queue[$this->queueTail++]);
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->logException($e);
        }
        return 1;
    }

    /**
     * Clear the event loop, deleting all signal handlers, enqueued callbacks
     * and streams, allowing the loop to stop.
     */
    public function clear(): void {
        foreach ($this->signalHandlers as $signo => $callbacks) {
            pcntl_signal($signo, SIG_DFL);
        }
        $this->signalHandlers = [];
        $this->readableStreamListeners = [];
        $this->writableStreamListeners = [];
        $this->queue = [];
        $this->queueTail = 0;
        $this->queueHead = 0;
    }

    /**
     * Function is invoked when all standard PHP code has finished running.
     */
    private function onShutdown(): void {
        // The event loop self activates on shutdown
        $this->draining = true;

        $count = $this->tick();
        if ($count > 0) {
            register_shutdown_function($this->onShutdown(...));
        } else {
            $this->draining = false;
        }
    }

    /**
     * Function is invoked whenever we receive a signal which is monitored
     */
    private function onSignal(int $signo, mixed $siginfo): void {
        if (!isset($this->signalHandlers[$signo])) {
            return;
        }
        $handlers = $this->signalHandlers[$signo];
        foreach ($handlers as $handler) {
            try {
                $handler($signo, $siginfo);
            } catch (\Throwable $error) {
                $this->logException($error);
            }
        }
    }

    /**
     * Run this callback when reading this stream will not
     * block.
     *
     * @param resource $stream   The callback to invoke
     * @param callable $callback        The callback to invoke
     */
    public function addReadStream($stream, callable $callback): void {
        $streamId = (int) $stream;
        if (isset($this->readableStreamListeners[$streamId])) {
            $this->readableStreamListeners[$streamId][1][] = $callback;
        } else {
            $this->readableStreamListeners[$streamId] = [ $stream, [ $callback ] ];
        }
        $this->enableStreamSelect();
    }

    public function removeReadStream($stream, callable $callback): void {
        $streamId = (int) $stream;
        if (!isset($this->readableStreamListeners[$streamId])) {
            return;
        }
        $this->readableStreamListeners[$streamId][1] = array_filter($this->readableStreamListeners[$streamId][1], function($existing) use ($callback) {
            return $callback !== $existing;
        });
        if (count($this->readableStreamListeners[$streamId][1]) === 0) {
            unset($this->readableStreamListeners[$streamId]);
        }
    }

    /**
     * Run this callback when writing to this stream will not
     * block.
     *
     * @param resource $stream   The callback to invoke
     * @param callable $callback        The callback to invoke
     */
    public function addWriteStream($stream, callable $callback): void {
        $streamId = (int) $stream;
        if (isset($this->writableStreamListeners[$streamId])) {
            $this->writableStreamListeners[$streamId][1][] = $callback;
        } else {
            $this->writableStreamListeners[$streamId] = [ $stream, [ $callback ] ];
        }
        $this->enableStreamSelect();
    }

    public function removeWriteStream($stream, callable $callback): void {
        $streamId = (int) $stream;
        if (!isset($this->writableStreamListeners[$streamId])) {
            return;
        }
        $this->writableStreamListeners[$streamId][1] = array_filter($this->writableStreamListeners[$streamId][1], function($existing) use ($callback) {
            return $callback !== $existing;
        });
        if (count($this->writableStreamListeners[$streamId][1]) === 0) {
            unset($this->writableStreamListeners[$streamId]);
        }
    }

    /**
     * Run this callback when the process receives a signal
     *
     * @param int $signal           The signal number to listen for
     * @param callable $callback    The callback to run
     */
    public function addSignal(int $signal, callable $callback): void {
        if ($this->terminating) {
            return;
        }
        if (!isset($this->signalHandlers[$signal])) {
            pcntl_signal($signal, $this->onSignal(...));
        }
        $this->signalHandlers[$signal][] = $callback;
    }

    /**
     *
     * @param int $signal           The signal number to listen for
     * @param callable $callback    The callback to run
     * Remove a signal handler
     */
    public function removeSignal(int $signal, callable $callback): void {
        if (!isset($this->signalHandlers[$signal])) {
            return;
        }
        $this->signalHandlers[$signal] = array_filter($this->signalHandlers[$signal], function($existing) use ($callback) {
            return $existing !== $callback;
        });
        if (count($this->signalHandlers[$signal]) === 0) {
            pcntl_signal($signal, SIG_DFL);
        }
    }

    protected function enableStreamSelect(): void {
        if (!$this->isStreamSelectScheduled) {
            $this->isStreamSelectScheduled = true;
            $this->defer($this->doStreamSelect(...));
        }
    }

    /**
     * Function which is scheduled to run in the event loop as long as there are
     * streams that need to be monitored for events.
     */
    protected function doStreamSelect(): void {
        if (!$this->isStreamSelectScheduled) {
            // The stream select is no longer scheduled so we abort
            return;
        }
        $this->isStreamSelectScheduled = false;

        $readStreams = [];
        foreach ($this->readableStreamListeners as $stream => $info) {
            if (!is_resource($info[0])) {
                unset($this->readableStreamListeners[$stream]);
                continue;
            }
            $readStreams[] = $info[0];
        }

        $writeStreams = [];
        foreach ($this->writableStreamListeners as $stream => $info) {
            if (!is_resource($info[0])) {
                unset($this->writableStreamListeners[$stream]);
                continue;
            }
            $writeStreams[] = $info[0];
        }

        $exceptStreams = [];
        if (count($writeStreams) === 0 && count($readStreams) === 0) {
            return;
        }

        $matches = stream_select($readStreams, $writeStreams, $exceptStreams, 0, 50000);

        $this->lastSleepTime = microtime(true);

        foreach ($readStreams as $stream) {
            $streamId = (int) $stream;
            foreach ($this->readableStreamListeners[$streamId][1] as $callback) {
                $this->defer($callback);
            }
        }

        foreach ($writeStreams as $stream) {
            $streamId = (int) $stream;
            foreach ($this->writableStreamListeners[$streamId][1] as $callback) {
                $this->defer($callback);
            }
        }

        // re-enable stream select callback since we still have streams to monitor
        $this->enableStreamSelect();
    }

    /**
     * Do we currently have a stream_select() callback scheduled?
     */
    private bool $isStreamSelectScheduled = false;

    /**
     * Map of streams to callbacks that needs notification when the stream
     * becomes readable.
     *
     * @type SplObjectStorage<resource, array<callable>>
     */
    private array $readableStreamListeners;

    /**
     * Map of streams to callbacks that needs notification when the stream
     * becomes writable.
     *
     * @type SplObjectStorage<resource, array<callable>>
     */
    private array $writableStreamListeners;

    /**
     * Map of signals number to arrays of callbacks which will be invoked when
     * the process receives a signal.
     */
    private array $signalHandlers = [];

    /**
     * Invoked to log or notify about exceptions thrown in user provided callbacks
     */
    private function logException(\Throwable $e): void {
        Moebius::logException($e);
    }

    private static function addStreamCallback(array &$map, $stream, callable $callback): void {
        $streamId = (int) $stream;
echo "STREAM ID: $streamId ADDED\n";
        if (!isset($map[$streamId])) {
            $current = [$stream];
        } else {
            $current = $map[$streamId];
        }
        $current[] = $callback;
        $map[$streamId] = $current;
var_dump($map);
    }

    private static function removeStreamCallback(array &$map, $stream, callable $callback): void {
        $streamId = (int) $stream;
echo "STREAM ID: $streamId REMOVED\n";
        if (!isset($map[$streamId])) {
            return;
        }
        $current = array_filter($map[$streamId], function($existing) use ($callback) {
            return $existing !== $callback;
        });
        if (count($current) === 0) {
            unset($map[$stream]);
        } else {
            $map[$streamId] = $current;
        }
    }

}
