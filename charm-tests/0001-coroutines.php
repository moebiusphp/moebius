<?php
use function M\{await, go};

return [
    "Testing coroutines job ordering",

    function($data) {
        $checkNumber = 1;

        /* This coroutine will run when the test script exist */
        $result = go(function() use (&$checkNumber) {
            echo "This happens early\n";
            $checkNumber *= 3;
        });

        await(
            /* These coroutines should happen in order */
            go(function() use (&$checkNumber) {
                echo "First coroutine\n";
                $checkNumber *= 7;
            }),
            go(function() use (&$checkNumber) {
                echo "Second coroutine\n";
                $checkNumber *= 9;
            })
        );

        /* This coroutine will run when the test script exist */
        $result = go(function() use (&$checkNumber) {
            echo "This happens after the script terminates\n";
            $checkNumber *= 11;
        });

        $checkNumber *= 13;

        return $checkNumber;
    },               // the test function
    [ 0, 27027 ],                                               // an array with input and output data
 ];
