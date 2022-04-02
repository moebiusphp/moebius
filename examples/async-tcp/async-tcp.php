<?php
require(__DIR__.'/../../vendor/autoload.php');


Moebius::go(function() {
    $t = microtime(true);
    $result = file_get_contents('https://download.blender.org/peach/bigbuckbunny_movies/BigBuckBunny_320x180.mp4');
    echo "Fetched ".strlen($result)." in ".(microtime(true)-$t)." seconds\n";
});
