<?php
namespace Moebius;

use Moebius;
use function stream_wrapper_register, stream_wrapper_unregister, stream_wrapper_restore;

/**
 * Used https://github.com/Nimut/testing-framework/blob/main/src/TestingFramework/File/FileStreamWrapper.php
 * as a reference.
 */
class FileStreamWrapper {

    private static bool $registered = false;

    /**
     * Function to assist with development early stage
     */
    private static function log(string $message): void {
        //fwrite(STDERR, "FSWrapper: ".trim($message)."\n");
    }

    public static function register(): void {
        if (self::$registered) {
            throw new \Exception("Already registered");
        }
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
        self::log("registered");
    }

    public static function unregister(): void {
        if (!self::$registered) {
            throw new \Exception("No registered");
        }
        stream_wrapper_unregister('file');
        stream_wrapper_restore('file');
        self::log("unregistered");
    }

    private static function wrap(callable $callback, mixed ...$args): mixed {
        stream_wrapper_unregister('file');
        stream_wrapper_restore('file');
        $result = $callback(...$args);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
        return $result;
    }

    /**
     * Rewrap the callback (for when we need to call "userspace" from inside an internal
     * function).
     */
    private static function rewrap(callable $callback, mixed ...$args): mixed {
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
        $result = $callback(...$args);
        stream_wrapper_unregister('file');
        stream_wrapper_restore('file');
        return $result;
    }

    private $dirHandle = null;
    private $fileHandle = null;
    private bool $fakeBlocking = false;

    public function dir_closedir(): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(closedir(...));
    }

    public function dir_opendir(string $path, int $options=0): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return !!($this->dirHandle = self::wrap(opendir(...), $path, $options));
    }

    public function dir_readdir(): string|false {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(readdir(...), $this->dirHandle);
    }

    public function dir_rewinddir(): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(rewinddir(...), $this->dirHandle);
    }

    public function mkdir(string $path, $mode, int $options=0): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(mkdir(...), $path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
    }

    public function rename(string $pathFrom, $pathTo): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(rename(...), $pathFrom, $pathTo);
    }

    public function rmdir(string $path): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(rmdir(...), $path);
    }

    public function stream_cast(string $path) {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        if ($this->fileHandle !== null && $castAs & STREAM_CAST_AS_STREAM) {
            return $this->fileHandle;
        }

        return false;
    }

    public function stream_close(): void {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        self::wrap(fclose(...), $this->fileHandle);
        $this->fileHandle = null;
    }

    public function stream_eof(): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(feof(...), $this->fileHandle);
    }

    public function stream_flush(): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(fflush(...), $this->fileHandle);
    }

    public function stream_lock($operation): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");

        if ($operation & LOCK_NB) {
            // this call is non-blocking, so we'll just allow it to go
            return self::wrap(flock(...), $this->fileHandle, $operation);
        } else {
            // this call is blocking, so let's simulate that
            while (!($result = self::wrap(flock(...), $this->fileHandle, $operation | LOCK_NB))) {
                self::log(__FUNCTION__." - unable to lock, retrying\n");
                Moebius::yield();
            }
            return $result;
        }
    }

    public function stream_metadata(string $path, int $options, $value): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(function() use ($path, $options, $value) {
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
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(function() use ($path, $mode, $options, &$opened_path) {
            /**
             * If the calling PHP code opened this file in blocking mode,
             * or if it is not being called from inside a coroutine we 
             * should ensure that the coroutine will block, even if we
             * manipulate the fopen()
             */
            $isNonBlocking = strpos($mode, 'n') !== false;
            $isCoroutine = !!Coroutine::getCurrent();

            if ($isNonBlocking || !$isCoroutine) {
                return $this->fileHandle = fopen($path, $mode, (bool) ($options & STREAM_USE_PATH));
            }

            /**
             * Simulating a blocking fopen call
             */
            $this->fileHandle = fopen($path, $mode.'n', (bool) ($options & STREAM_USE_PATH));
            $metadata = stream_get_meta_data($this->fileHandle);
            echo json_encode($metadata, JSON_PRETTY_PRINT);

            return !!$this->fileHandle;
        });
    }

    public function stream_read($length): string|false {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        Moebius::readable($this->fileHandle);
        return self::wrap(fread(...), $this->fileHandle, $length);
    }

    public function stream_seek($offset, $whence = SEEK_SET): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(fseek(...), $offset, $whence);
    }

    public function stream_set_option($option, $arg1, $arg2): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return false;
    }

    public function stream_stat(): array|false {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(fstat(...), $this->fileHandle);
    }

    public function stream_tell(): int {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(ftell(...), $this->fileHandle);
    }

    public function stream_truncate($size): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(ftruncate(...), $this->fileHandle, $size);
    }

    public function stream_write($data): int {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        Moebius::writable($this->fileHandle);
        return self::wrap(fwrite(...), $this->fileHandle, $data);
    }

    public function unlink(string $path): bool {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        return self::wrap(unlink(...), $path);
    }

    public function url_stat(string $path, $flags): array|false {
        self::log(__FUNCTION__."(".implode(" ", func_get_args()).")");
        if ($flags & STREAM_URL_STAT_LINK) {
            return self::wrap(lstat(...), $path);
        } else {
            return self::wrap(stat(...), $path);
        }
    }
}
