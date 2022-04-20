Moebius
=======

Pure coroutines for PHP 8.1. To promises and callbacks needed. Just pure parallel
PHP code inside coroutines.

*Moebius Band: A loop with only one surface*

![Möbuus Loop](docs/assets/wikipedia-mobius-strip.png)

---

What is this?
-------------

Completely transparent coroutines thanks to PHP 8.1 Fibers. No complex
nested callbacks or promise trees. Just plain old-school PHP code running
asynchronously, like in GoLang.

> The easiest way to bring your codebase up to speed with high performance
> event based concurrency. It's like Swoole, but without the extension.


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

