<?php
namespace Moebius;

class SuspendException extends \Exception {
    public function __construct() {
        parent::__construct("Preemptively suspend fiber exception");
    }
}
