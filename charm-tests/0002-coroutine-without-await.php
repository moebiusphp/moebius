<?php
require(__DIR__.'/../vendor/autoload.php');

use function M\{await, go, sleep};

$theFiber = null;

$test = go(function() {
    global $theFiber;
    $theFiber = Fiber::getCurrent();
    echo "1: Start\n";
    sleep(0.1);
    echo "3: Follows step 1 in the code\n";
});


exit("2: exit() was called\n");


function fiberStatus(Fiber $fiber) {
    echo "FIBER ".spl_object_id($fiber)." started=".json_encode($fiber->isStarted())." suspended=".json_encode($fiber->isSuspended())." terminated=".json_encode($fiber->isTerminated())."\n";
}
