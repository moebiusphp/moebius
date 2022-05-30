<?php
namespace Funny;

require(__DIR__."/../vendor/autoload.php");

use Moebius\Coroutine as Co;


$startTime = microtime(true);

$winner = null;

$a = Co::go(function() use (&$winner) {
    echo "Coroutine 1 is ready!\n";
    Co::sleep(0.5);
    echo "Coroutine 1 is set!\n";
    Co::sleep(0.5);
    echo "Coroutine 1 is counting to 100!\n";
    for ($i = 0; $i < 100; $i++) {
        $t = microtime(true);
        Co::sleep(0.01);
        echo "1";
    }
    echo "\n";
    if ($winner === null) {
        $winner = "Coroutine 1";
    }
});
$b = Co::go(function() use (&$winner) {
    echo "Coroutine 2 is ready!\n";
    Co::sleep(0.5);
    echo "Coroutine 2 is set!\n";
    Co::sleep(0.5);
    echo "Coroutine 2 is counting to 50!\n";
    for ($i = 0; $i < 50; $i++) {
        $t = microtime(true);
        Co::sleep(0.02);
        echo "2";
    }
    echo "\n";
    if ($winner === null) {
        $winner = "Coroutine 2";
    }
});

Co::await($a);
Co::await($b);
echo "The winner is $winner\n";
$t = microtime(true) - $startTime;
echo "Total time for all coroutines: ".$t."\n";
assert($t>2 && $t<2.1);
