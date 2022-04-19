<?php
namespace Moebius;

final class FiberTask {
    private $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function run(): void {
        ($this->callback)();
        $this->callback = null;
    }
}
