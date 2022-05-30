<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\Coroutine as Co;

$parallel = 0;

$wg = new Moebius\Coroutine\WaitGroup();

foreach (glob(__DIR__."/../vendor/*/*/*/*.php") as $file) {
    if (!is_file($file)) continue;

    $wg->add(1);

    Co::go(function($file) use (&$parallel, &$peak, $wg) {
        ++$parallel;
        if ($parallel > $peak) {
            $peak = $parallel;
        }
echo ".";
        echo basename($file)." ".md5_file($file)."\n";
        --$parallel;

        $wg->done();
    }, $file);
}

$wg->wait();
echo "----\nPeak number of parallel reads: $peak\n";
assert($peak > 5);
