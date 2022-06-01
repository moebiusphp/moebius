<?php
use Moebius\Coroutine as Co;

function test($time) {
    return Co::run(function() use ($time) {
        $run = true;
        Co::go(function() use (&$run) {
            $step = 0;
            while ($run) {
                Co::suspend();
            }
        });
        $t = hrtime(true);
        Co::sleep($time);
        $timeTaken = (hrtime(true) - $t) / 1000000000;
        $run = false;
        Co::suspend();
        return $timeTaken + 0.01 > $time && $timeTaken - 0.01 < $time;
    });
}

assert(test(0) === true);
assert(test(0.1) === true);
assert(test(0.7) === true);
assert(test(0.02) === true);
