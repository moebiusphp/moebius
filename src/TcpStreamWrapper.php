<?php
namespace Moebius;

use Moebius;
use function stream_wrapper_register, stream_wrapper_unregister, stream_wrapper_restore;

class TcpStreamWrapper {

    private static bool $registered = false;

    /**
     * Function to assist with development early stage
     */
    private static function log(string $message): void {
        fwrite(STDERR, "TCPWrapper: ".trim($message)."\n");
    }

    public static function register(): void {
        if (self::$registered) {
            throw new \Exception("Already registered");
        }

        $ctx = stream_context_get_default();

        var_dump(stream_context_get_options($ctx));
        var_dump(stream_context_get_params($ctx));


        self::log("registered");
    }

    public static function unregister(): void {
        if (!self::$registered) {
            throw new \Exception("No registered");
        }
        stream_wrapper_unregister('tcp');
//        stream_wrapper_restore('tcp');
        self::log("unregistered");
    }

}
