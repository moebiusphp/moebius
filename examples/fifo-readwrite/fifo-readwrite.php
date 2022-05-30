<?php
    require(__DIR__.'/../../vendor/autoload.php');

    use Moebius\Coroutine as Co;

    $fifoPath = sys_get_temp_dir().'/example.fifo-file';

echo "mkfifo\n";
    posix_mkfifo($fifoPath, 0600);

    /**
     * Reading from a blocking fifo file would normally block
     * a PHP application - preventing the next coroutine from
     * starting.
     */
    $coroutine1 = Co::go(function() use ($fifoPath) {
        $counter = 0;
        $fp = fopen($fifoPath, 'r');
        while (!feof($fp)) {
            echo "Reading line ".(++$counter).": ".json_encode(fgets($fp))."\r";
        }
        echo "READ COROUTINE DONE\n";
    });

    /**
     * Only when the first writer can start writing will the
     * coroutine above be allowed to resume.
     */
    $coroutine2 = Co::go(function() use ($fifoPath) {
        $counter = 0;

        $fp = fopen($fifoPath, 'w');
        $timeLimit = microtime(true) + 2;
        while (microtime(true) < $timeLimit) {
            fwrite($fp, microtime(true)."\n");
        }
        fclose($fp);
        echo "\nWRITE COROUTINE DONE\n";
    });
    // Wait until the coroutine have finished
    Co::await($coroutine1);
    Co::await($coroutine2);

    unlink($fifoPath);

    echo "End of example\n";



?>

