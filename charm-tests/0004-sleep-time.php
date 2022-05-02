<?php
use Moebius\Coroutine as Co;

return [
    "Testing sleep accuracy to be within 1 millisecond",
    function($time) {
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
    },
    [ 0, true ],
    [ 0.1, true ],
    [ 0.7, true ],
    [ 0.02, true ],
];
