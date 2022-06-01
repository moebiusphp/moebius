<?php
use Moebius\Coroutine as Co;

Co::sleep(0.1);

$test = Co::go(function() {

    echo "1: This should happen first\n";
    $thisFile = file_get_contents(__FILE__);
    echo "3: This should happen last\n";

});

echo "2: This should happen before file_get_contents() completes\n";

