<?php
use Moebius\Coroutine as Co;

$theFiber = null;

$test = Co::go(function() {
    global $theFiber;
    $theFiber = Fiber::getCurrent();
    echo "1: Start\n";
    Co::sleep(0.1);
    echo "3: Follows step 1 in the code\n";
});


exit("2: exit() was called\n");


function fiberStatus(Fiber $fiber) {
    echo "FIBER ".spl_object_id($fiber)." started=".json_encode($fiber->isStarted())." suspended=".json_encode($fiber->isSuspended())." terminated=".json_encode($fiber->isTerminated())."\n";
}
