Moebius
=======

Pure coroutines for PHP 8.1. To promises and callbacks needed. Just pure parallel
PHP code inside coroutines.

*Moebius Band: A loop with only one surface*

![Möbius Loop](docs/assets/wikipedia-mobius-strip.png)

---

What is this?
-------------

Completely transparent coroutines thanks to PHP 8.1 Fibers. No complex
nested callbacks or promise trees. Just plain old-school PHP code running
asynchronously, like in GoLang.

> The easiest way to bring your codebase up to speed with high performance
> event based concurrency. It's like Swoole, but without the extension.


## Plain old PHP

```php
<?php
    // Track when all files have been listed
    foreach (glob(__DIR__.'/*') as $file) {
        if (!is_file($file)) continue;

        echo basename($file)." ".md5_file($file)."\n";
    }

```


## Moebius example

Even though it looks very much like synchronous code, this is actually using
asynchronous IO.

```php
<?php
    require("vendor/autoload.php");

    use Moebius\Coroutine as Co;

    foreach (glob(__DIR__.'/*') as $file) {
        if (!is_file($file)) continue;

        Co::go(function($file) {
            echo basename($file)." ".md5_file($file)."\n";
        }, $file);
    }
```


## React PHP example

This example try to print md5 checksums for all files in the current directory
concurrently using `react/filesystem`. This example will load each file into
memory, probably many of them at the same time.

```php
<?php
    require("vendor/autoload.php");

    use React\Filesystem\Factory;
    use React\Filesystem\Node\FileInterface;

    use function React\Promise\all;

    $filesystem = Factory::create();

    $filesystem->directory(__DIR__)->ls()
    ->then(function($files) {
        foreach ($files as $file) {
            if ($file instanceof FileInterface) {
                $file->getContents()
                ->then(function($contents) use ($file) {
                    echo $file->name()." ".md5($contents)."\n";
                });
            }
        }
    });

    $loop->run();
```


## Amp

Amp has a pretty nice syntax. The challenge is to keep track of all the functions
that require using a `yield` keyword. This example will load each file into
memory, probably many of them at the same time.

```php
<?php
    require("vendor/autoload.php");

    use function Amp\File\{
        listFiles,
        isFile,
        read
    };

    Amp\call(function () {
        foreach (yield listFiles(__DIR__) as $file) {
            Amp\call(function() use ($file) {
                if (!yield isFile($file)) {
                    echo basename($file)." ".md5(yield read($file))."\n";
                }
            });
        }
    });
```


Asynchronous IO
---------------

Moebius will transparently switch between your coroutines whenever you
are reading or writing to disk - so you don't need to worry about special
async versions of common commands like `file_get_contents` and such.


Like GoLang, not like JavaScript
--------------------------------

The main thing that makes PHP a very productive language, is
that you can do much with very simple, single-threaded code.

With Moebius, you don't have to change your coding style.


### Old-school javascript

```javascript
    // Do something 10 times, once every second
    let counter = 0;
    let i = setInterval(() => {
        console.log("Every second");
        if (counter === 10) {
            clearInterval(i);
        }
    }, 1000);
```


### "Cool" javascript

Ecmascript has introduced the async/await keywords, but
you can't use them wherever you want. Not good.

```javascript
    // need to make a sleep() function
    function sleep(time) {
        return new Promise((resolve) => {
            setTimeout(resolve, time * 1000);
        }
    }

    async function count() {
        for (let i = 0; i < 10; i++) {
            console.log("Every second");
            await sleep(1);
        }
    }

    // You CAN'T use the `await` function everywhere
```


### Very cool PHP 8.1

Just call your function with `go()` (globally asynchronously), or
with `await(go())` (locally asynchronously).

```php
    use M\{go, sleep};

    // Do something 10 times, once every second
    go(function() {
        for ($i = 0; $i < 10; $i++) {
            echo "Every second\n";
            sleep(1);
        }
    });
```

