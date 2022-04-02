<?php
namespace Moebius;

use Moebius;
use function stream_wrapper_register, stream_wrapper_unregister, stream_wrapper_restore;

abstract class GenericAsyncStreamWrapper {

    /**
     * Return 'file', 'http' or some other protocol name
     */
    abstract protected static function getProtocolName(): string;

    protected function readable(): void {
        $metadata = stream_get_meta_data($this->fileHandle);
print_r($metadata);
        echo "UNREAD: ".$metadata['unread_bytes']."\n";
        if ($metadata['unread_bytes'] === 0) {
            echo "Injecting a sleep\n";
            Moebius::sleep(0.1);
        } else {
            Moebius::sleep(0);
        }
    }

    protected function writable(): void {
        Moebius::sleep(0);
    }


    private static bool $registered = false;

    /**
     * Function to assist with development early stage
     */
    private static function log(string $message): void {
        static $previous = 0;
        if ($previous === 0) {
            $previous = microtime(true);
        }
        $t = microtime(true);
        $diff = $t - $previous;
        $previous = $t;
        fwrite(STDOUT, number_format($diff, 4)." ".static::getProtocolName()."-wrapper: ".trim($message)."\n");
    }

    public static function register(): void {
        if (static::$registered) {
            throw new \Exception("Already registered");
        }
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_register(static::getProtocolName(), static::class);
        static::log("registered");
    }

    public static function unregister(): void {
        if (!static::$registered) {
            throw new \Exception("No registered");
        }
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_restore(static::getProtocolName());
        static::log("unregistered");
    }

    private static function wrap(callable $callback, mixed ...$args): mixed {
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_restore(static::getProtocolName());
        $result = $callback(...$args);
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_register(static::getProtocolName(), static::class);
        return $result;
    }

    /**
     * Rewrap the callback (for when we need to call "userspace" from inside an internal
     * function).
     */
    private static function rewrap(callable $callback, mixed ...$args): mixed {
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_register(static::getProtocolName(), static::class);
        $result = $callback(...$args);
        stream_wrapper_unregister(static::getProtocolName());
        stream_wrapper_restore(static::getProtocolName());
        return $result;
    }

    private $dirHandle = null;
    private $fileHandle = null;
    private bool $fakeBlocking = false;

    public function dir_closedir(): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(closedir(...));
    }

    public function dir_opendir(string $path, int $options=0): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return !!($this->dirHandle = static::wrap(opendir(...), $path, $options));
    }

    public function dir_readdir(): string|false {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(readdir(...), $this->dirHandle);
    }

    public function dir_rewinddir(): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(rewinddir(...), $this->dirHandle);
    }

    public function mkdir(string $path, $mode, int $options=0): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(mkdir(...), $path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
    }

    public function rename(string $pathFrom, $pathTo): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(rename(...), $pathFrom, $pathTo);
    }

    public function rmdir(string $path): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(rmdir(...), $path);
    }

    public function stream_cast(int $castAs) {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return $this->fileHandle;
        if ($this->fileHandle !== null && $castAs & STREAM_CAST_AS_STREAM) {
            return $this->fileHandle;
        }

        return false;
    }

    public function stream_close(): void {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        static::wrap(fclose(...), $this->fileHandle);
        $this->fileHandle = null;
    }

    public function stream_eof(): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        $this->readable();
        $result = static::wrap(feof(...), $this->fileHandle);
        static::log("- done");
        return $result;
    }

    public function stream_flush(): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(fflush(...), $this->fileHandle);
    }

    public function stream_lock($operation): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");

        if ($operation & LOCK_NB) {
            // this call is non-blocking, so we'll just allow it to go
            return static::wrap(flock(...), $this->fileHandle, $operation);
        } else {
            // this call is blocking, so let's simulate that
            while (!($result = static::wrap(flock(...), $this->fileHandle, $operation | LOCK_NB))) {
                static::log(__FUNCTION__." - unable to lock, retrying\n");
                Moebius::yield();
            }
            return $result;
        }
    }

    public function stream_metadata(string $path, int $options, $value): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(function() use ($path, $options, $value) {
            switch ($options) {
                case STREAM_META_TOUCH:
                    if (!empty($value)) {
                        $success = touch($path, $value[0], $value[1]);
                    } else {
                        $success = touch($path);
                    }
                    break;
                case STREAM_META_OWNER_NAME:
                    // fall through
                case STREAM_META_OWNER:
                    $success = chown($path, $value);
                    break;
                case STREAM_META_GROUP_NAME:
                    // fall through
                case STREAM_META_GROUP:
                    $success = chgrp($path, $value);
                    break;
                case STREAM_META_ACCESS:
                    $success = chmod($path, $value);
                    break;
                default:
                    $success = false;
            }
            return $success;
        });
    }

    public function stream_open(string $path, $mode, int $options, &$opened_path): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        /**
         * If the calling PHP code opened this file in blocking mode,
         * or if it is not being called from inside a coroutine we 
         * should ensure that the coroutine will block, even if we
         * manipulate the fopen()
         */
        $isNonBlocking = strpos($mode, 'n') !== false;
        $isCoroutine = !!Coroutine::getCurrent();

        static::log("- calling fopen");
        $this->fileHandle = static::wrap(fopen(...), $path, $mode, (bool) ($options & STREAM_USE_PATH));
        static::log("- fopen finished");
        return !!$this->fileHandle;
    }

    public function stream_read($length): string|false {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        $this->readable();
        return static::wrap(fread(...), $this->fileHandle, $length);
    }

    public function stream_seek($offset, $whence = SEEK_SET): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(fseek(...), $offset, $whence);
    }

    public function stream_set_option($option, $arg1, $arg2): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return false;
    }

    public function stream_stat(): array|false {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        $this->readable();
        return static::wrap(fstat(...), $this->fileHandle);
    }

    public function stream_tell(): int {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(ftell(...), $this->fileHandle);
    }

    public function stream_truncate($size): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(ftruncate(...), $this->fileHandle, $size);
    }

    public function stream_write($data): int {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        $this->writable();
        return static::wrap(fwrite(...), $this->fileHandle, $data);
    }

    public function unlink(string $path): bool {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return static::wrap(unlink(...), $path);
    }

    public function url_stat(string $path, $flags): array|false {
        static::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        if ($flags & STREAM_URL_STAT_LINK) {
            return static::wrap(lstat(...), $path);
        } else {
            return static::wrap(stat(...), $path);
        }
    }
}
