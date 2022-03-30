<?php
namespace Funny;

require("vendor/autoload.php");

use function M\{
    await,          // await one or more coroutines (or Promise instances)
    go,             // start a coroutine
    usleep          // unless we're using an async-ready library, we must call usleep() to share CPU time
};

$startTime = microtime(true);

$winner = null;

await(
    go(function() use (&$winner) {
        echo "Coroutine 1 is ready!\n";
        usleep(500000);
        echo "Coroutine 1 is set!\n";
        usleep(500000);
        echo "Coroutine 1 is counting to 100!\n";
        for ($i = 0; $i < 100; $i++) {
            $t = microtime(true);
            usleep(10000);
            echo "1";
        }
        echo "\n";
        if ($winner === null) {
            $winner = "Coroutine 1";
        }
    }),
    go(function() use (&$winner) {
        echo "Coroutine 2 is ready!\n";
        usleep(500000);
        echo "Coroutine 2 is set!\n";
        usleep(500000);
        echo "Coroutine 2 is counting to 50!\n";
        for ($i = 0; $i < 50; $i++) {
            $t = microtime(true);
            usleep(20000);
            echo "2";
        }
        echo "\n";
        if ($winner === null) {
            $winner = "Coroutine 2";
        }
    })
);

echo "The winner is $winner\n";
echo "Total time for all coroutines: ".(microtime(true) - $startTime)."\n";
