<?php
namespace Moebius;

class PromiseRejectedError extends PromiseException {
    public readonly mixed $reason;
    public function __construct($reason) {
        if (is_string($reason) || $reason instanceof \Stringable || is_numeric($reason)) {
            parent::__construct($reason);
        } else {
            parent::__construct("Promise was rejected with a value of type ".get_debug_type($reason));
        }
        $this->reason = $reason;
    }
}
