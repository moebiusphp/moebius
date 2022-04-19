<?php
namespace Moebius;

use FFI, Fiber, WeakMap;
use const SIGALRM, SIG_DFL;
use function pcntl_signal, pcntl_async_signals;
use Moebius;

/**
 * Run a callback in a resumable way.
 */
class Coroutine {

    public static function configure(
        float $maxTime = 0.01
    ) {
        self::$maxTime = $maxTime;
    }


    /**
     * Configure the max time-slice a coroutine is allowed to run.
     */
    private static float $maxTime = 0.01;

    public static function create(callable $coroutine, mixed ...$args): Promise {
        $c = new self($coroutine, ...$args);
        $c->run();
//        Moebius::defer($c->run(...));
        return $c->promise;
    }

    /**
     * Get the current coroutine if one is running
     */
    public static function getCurrent(): ?self {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            return null;
        }
        if (!isset(self::$fibers[$fiber])) {
            return null;
        }
        return self::$fibers[$fiber];
    }

    /**
     * Call this function in busy loops to automatically suspend once the runtime has expired.
     * Has no effect outside of coroutines.
     */
    public static function check(): void {
        if (
            self::$deadline !== null &&
            self::$deadline < microtime(true)
        ) {
            self::suspend();
        }
    }
    private static $trickStream;

    /**
     * Suspend the current coroutine.
     */
    public static function suspend(): void {
        if (null !== ($c = self::getCurrent())) {
            Fiber::suspend($c);
        } else {
            throw new \Exception("No coroutine is running when trying to suspend.");
        }
    }

    /**
     * The fiber should yield within this time
     */
    private static ?float $deadline = null;

    private static int $head = 0;
    private static int $tail = 0;
    private static array $queue = [];

    private Promise $promise;
    private Fiber $fiber;
    private array $args;
    private mixed $lastResult;
    private float $runTime = 0;
    private ?\Throwable $exception = null;

    private function __construct(callable $coroutine, mixed ...$args) {
        $this->promise = new Promise();
        $this->fiber = new Fiber($coroutine);
        $this->args = $args;
        self::$fibers[$this->fiber] = $this;
    }

    /**
     * Start or resume the coroutine.
     *
     * @return bool TRUE if the coroutine has finished
     */
    public function run(): bool {
        if (Fiber::getCurrent() && self::getCurrent()) {
            throw new \Exception("Can't run coroutine from within another coroutine");
        }
        if ($this->fiber->isTerminated()) {
            throw new CoroutineException("Coroutine has terminated");
        }

        $startTime = microtime(true);

        self::$deadline = $startTime + self::$maxTime;

        $this->startContext();

        try {
            if ($this->fiber->isSuspended()) {
                $this->lastResult = $this->fiber->resume($this->lastResult);
            } elseif (!$this->fiber->isStarted()) {
                $this->lastResult = $this->fiber->start(...$this->args);
            } else {
                throw new CoroutineException("Coroutine is in an unexpected state");
            }
        } catch (\Throwable $e) {
            $this->logException($e);
            // @TODO Could be useful to see these exceptions in a PSR logger
            $this->exception = $e;
            $this->promise->reject($e);
        }

        switch ($this->promise->status()) {
            case Promise::FULFILLED:
                unset(self::$fibers[$this->fiber]);
                return true;
            case Promise::REJECTED:
                unset(self::$fibers[$this->fiber]);
                return true;
        }
        if ($this->fiber->isTerminated()) {
            unset(self::$fibers[$this->fiber]); // helps garbage collection a little
            $returnValue = $this->fiber->getReturn();
            $this->promise->resolve($returnValue);
            return true;
        } else {
            $this->stopContext();
            $this->runTime += microtime(true) - $startTime;
            Moebius::defer($this->run(...));
            return false;
        }
    }

    /**
     * Context switching: setup the context for the
     * coroutine.
     */
    private function startContext(): void {
        // TODO
    }

    /**
     * Context switching: save the context for the
     * coroutine.
     */
    private function stopContext(): void {
        // TODO
    }

    protected function logException(\Throwable $e): void {
        Moebius::logException($e);
    }

    /**
     * Allow us to get the current Coroutine based on the current
     * Fiber.
     */
    private static WeakMap $fibers;

    /**
     * Map from Fiber instance to Coroutine instances.
     */
    private static bool $bootstrapped = false;

    /**
     * Setup the coroutine class support environment
     */
    public static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        self::$fibers = new WeakMap();
        self::$bootstrapped = true;
    }


    /**
     * Schedule an SIGALRM signal to occur in $usec microseconds,
     * up to 1 second.
     *
    /
    /*
    private static function preempt(int $usec): void {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('long ualarm(long uscs, long intrval);');
        }
        $usec = min($usec, 999999);
        self::$ffi->ualarm($usec, 0);
        pcntl_signal(SIGALRM, function() use ($handler) {
            // Disable the alarm
            self::$ffi->ualarm(0, 0);
            // Remove signal handler
            pcntl_signal(SIGALRM, SIG_DFL);
            // Ensure self::onTick() is run as soon as possible after the signal handler.
            // TODO Find a way to run self::onTick() outside of the signal handler
        });
    }
    private static ?FFI $ffi = null;
    */
    /*
    private static function stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max) {
        switch($notification_code) {
            case STREAM_NOTIFY_RESOLVE:
            case STREAM_NOTIFY_AUTH_REQUIRED:
            case STREAM_NOTIFY_COMPLETED:
            case STREAM_NOTIFY_FAILURE:
            case STREAM_NOTIFY_AUTH_RESULT:
                var_dump($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max);
                break;
            case STREAM_NOTIFY_REDIRECTED:
                echo "Being redirected to: ", $message;
                break;
            case STREAM_NOTIFY_CONNECT:
                echo "Connected...";
                break;
            case STREAM_NOTIFY_FILE_SIZE_IS:
                echo "Got the filesize: ", $bytes_max;
                break;
            case STREAM_NOTIFY_MIME_TYPE_IS:
                echo "Found the mime-type: ", $message;
                break;
            case STREAM_NOTIFY_PROGRESS:
                echo "Made some progress, downloaded ", $bytes_transferred, " so far";
                break;
        }
        echo "\n";
    }
    */
}

/**
 * Need to setup static properties. No other side-effects.
 */
Coroutine::bootstrap();


