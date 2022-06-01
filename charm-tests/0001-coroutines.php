<?php
use Moebius\Coroutine as Co;


function test($data) {
    $checkNumber = 1;

        /* This coroutine will run when the test script exist */
    $result = Co::go(function() use (&$checkNumber) {
//            echo "This happens early\n";
        $checkNumber *= 3;
    });

    Co::await(
            /* These coroutines should happen in order */
        Co::go(function() use (&$checkNumber) {
//                echo "First coroutine\n";
            $checkNumber *= 7;
        })
    );
    Co::await(
        Co::go(function() use (&$checkNumber) {
//                echo "Second coroutine\n";
            $checkNumber *= 9;
        })
    );

        /* This coroutine will run when the test script exits */
    $result = Co::go(function() use (&$checkNumber) {
        Co::suspend();
//            echo "This happens after the script terminates\n";
        $checkNumber *= 11;
    });

    $checkNumber *= 13;

    return $checkNumber;
}

assert(test(0) === 2457);


//,               // the test function
//    [ 0, 2457 ],                                               // an array with input and output data
// ];
