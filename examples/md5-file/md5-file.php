<?php
require(__DIR__.'/../../vendor/autoload.php');

use Moebius\Coroutine as Co;

foreach (glob("/var/log/*") as $file) {
    if (!is_file($file)) continue;

    Co::go(function($file) {
        echo basename($file)." ".md5_file($file)."\n";
    }, $file);
}
