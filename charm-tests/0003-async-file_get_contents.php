<?php
require(__DIR__.'/../vendor/autoload.php');

use function M\{await, go, sleep};

$test = go(function() {

    echo "1: This should happen first\n";
    $thisFile = file_get_contents(__FILE__);
    echo "3: This should happen last\n";

});

echo "2: This should happen before file_get_contents() completes\n";

