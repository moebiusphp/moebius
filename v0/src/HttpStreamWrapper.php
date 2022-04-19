<?php
namespace Moebius;

use Moebius;
use function stream_wrapper_register, stream_wrapper_unregister, stream_wrapper_restore;

class HttpStreamWrapper extends GenericAsyncStreamWrapper {

    protected static function getProtocolName(): string {
        return 'http';
    }

}
