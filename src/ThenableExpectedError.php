<?php
namespace Moebius;

class ThenableExpectedError extends \Exception {
    public readonly mixed $received;

    public function __construct($received) {
        parent::__construct("Expected a Promise-like object with a 'then(\$resolve, \$reject)' method, but received '".get_debug_type($received)."'");
        $this->received = $received;
    }
}
