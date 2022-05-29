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

        @file_get_contents("nonexistent.file");
        $checker("file_get_contents() for non-existent file");

        $dir = opendir(__DIR__);
        $checker("opendir() for existing dir");

        closedir($dir);
        $checker("closedir()");

        $dir = @opendir(__DIR__.'/nonexistent.dir');
        $checker("opendir() for non-existent dir");

        $path = sys_get_temp_dir().'/'.hrtime(true).'.'.mt_rand(0, 9999999);

        mkdir($path);
        $checker("mkdir()");

        rename($path, $path."-temp");
        $checker("rename() (1)");

        rename($path."-temp", $path);
        $checker("rename() (2)");

        rmdir($path);
        $checker("rmdir()");

        $fp = fopen($path, 'c+');
        $checker("fopen('c+')");

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

        unlink($path);
        $checker("unlink()");

    } catch (\Throwable $e) {
        fwrite(STDERR, get_class($e).": ".$e->getMessage()." code=".$e->getCode()." in ".$e->getFile().":".$e->getLine()."\n");
    }
});
