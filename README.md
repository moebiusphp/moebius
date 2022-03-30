Moebius
=======

Completely transparent coroutines thanks to PHP 8.1 Fibers. No complex
nested callbacks or promise trees. Just plain old-school PHP code running
asynchronously, like in GoLang.

> The easiest way to bring your codebase up to speed with high performance
> event based concurrency. It's like Swoole, but without the extension.


Works with your existing code
-----------------------------

You can immediately use asynchronous code anywhere in your existing codebase,
no need to change the entire structure of your request handler:

```
<?php
    require('vendor/autoload.php');

    use function M\{go, await, sleep};

    function some_existing_function() {
        /**
         * Let's use two coroutines to count from
         * 0 to 110 in 10 seconds, without exiting
         * the function.
         */
        $total = 0;

        /**
         * The await function takes any number of
         * coroutines and blocks until they are
         * finished.
         */
        await(
            // First coroutine counting every second
            go(function() use (&$total) {
                for ($i = 0; $i < 10; $i++) {
                    $total++;
                    sleep(1);
                }
            }),
            // Second coroutine counting every 0.1 seconds.
            go(function() use (&$total) {
                for ($i = 0; $i < 100; $i++) {
                    $total++;
                    sleep(0.1);
                }
            })
        );

        /**
         * After 10 seconds, we are here
         */
        echo "$counter\n"; // outputs 110
    }

    /**
     * You don't have to change the way you call asynchronous
     * code.
     */
    some_existing_function();
```

Asynchronous IO
---------------

When you work with the file system, a special stream wrapper
will transparently convert your blocking file operations into
concurrency fiendly event-based code.

```
<?php

function my_old_function() {
    $fp = fopen("some-file.txt", 'r');          // HERE
    while (!feof($fp)) {
        $bytes .= fread($fp, 4096);             // HERE
    }
    fclose($fp);                                // HERE
}
```

 * Other IO, such as database connections and http requests
   are work in progress.


Like GoLang, not like JavaScript
--------------------------------

The main thing that makes PHP a very productive language, is
that you can do much with very simple, single-threaded code.

With Moebius, you don't have to change your coding style.


### Old-school javascript

```
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

```
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

```
    use M\{go, sleep};

    // Do something 10 times, once every second
    go(function() {
        for ($i = 0; $i < 10; $i++) {
            echo "Every second\n";
            sleep(1);
        }
    });
```

