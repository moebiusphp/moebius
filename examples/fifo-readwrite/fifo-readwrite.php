<?php
    require(__DIR__.'/../../vendor/autoload.php');

    $FIFO_PATH = sys_get_temp_dir().'/example.fifo-file';

    posix_mkfifo($FIFO_PATH, 0600);

    /**
     * Reading from a blocking fifo file would normally block
     * a PHP application - preventing the next coroutine from
     * starting.
     */
    $coroutine1 = Moebius::go(function() use ($FIFO_PATH) {

        $counter = 0;
        $fp = fopen($FIFO_PATH, 'r');
        while (!feof($fp)) {
            echo "Reading line ".(++$counter).": ".json_encode(fgets($fp))."\n";
        }
        echo "READ COROUTINE DONE\n";
    });

    /**
     * Only when the first writer can start writing will the
     * coroutine above be allowed to resume.
     */
    $coroutine2 = Moebius::go(function() use ($FIFO_PATH) {
        $counter = 0;

        $fp = fopen($FIFO_PATH, 'w');
        $timeLimit = microtime(true) + 2;
        while (microtime(true) < $timeLimit) {
            fwrite($fp, microtime(true)."\n");
        }
        fclose($fp);
        echo "\nWRITE COROUTINE DONE\n";
    });

    // Wait until the coroutine have finished
    Moebius::await($coroutine1, $coroutine2);

    unlink($FIFO_PATH);

    echo "End of example\n";



?>

