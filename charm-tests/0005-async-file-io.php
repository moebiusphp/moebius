<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\Coroutine as Co;

Co::run(function() {
    try {
        $last = Moebius\Loop::getTime();
        $lastTime = hrtime(true);

        // Function increases $errors and prints the reason, if $count does not increase
        // between calls
        $checker = function(string $reason) use (&$last, &$lastTime) {
            if ($last === Moebius\Loop::getTime()) {
                echo "FAILED    $reason\n          No coroutine switch triggered\n\n";
            } elseif ($lastTime + 5000000 < hrtime(true)) {
                echo "WARNING   $reason\n          Operation took more than 0.005 seconds and may have been blocking\n\n";
            } else {
                echo "OK        $reason\n";
            }
            Co::suspend();
            $last = Moebius\Loop::getTime();
            $lastTime = hrtime(true);
        };

        file_get_contents(__FILE__);
        $checker("file_get_contents() for existing file");

        $path = tempnam(sys_get_temp_dir(), 'moebius-test-0005');

        $fp = fopen($path, 'c+');

        fwrite($fp, "Hello World\n");
        $checker("fwrite()");

        rewind($fp);
        $checker("rewind()");

        $line = fgets($fp);
        $checker("fgets()");

        fseek($fp, 4);
        $checker("fseek()");

        fclose($fp);
        $checker("fclose()");

    } catch (\Throwable $e) {
        fwrite(STDERR, get_class($e).": ".$e->getMessage()." code=".$e->getCode()." in ".$e->getFile().":".$e->getLine()."\n");
    }
});
