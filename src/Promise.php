<?php
namespace Moebius;

use function method_exists;
use ReflectionFunction, ReflectionNamedType, ReflectionUnionType;

class Promise {

    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';
    const CANCELLED = 'cancelled';

    /**
     * Cast a Thenable class into a Promise
     */
    public static function cast(object $thenable): self {
        if ($thenable instanceof Promise) {
            return $thenable;
        }

        static::assertThenable($thenable);

        $promise = new static(function($resolve, $reject) use ($thenable) {
            $thenable->then($resolve, $reject);
        });
        $promise->fromThenable = true;
    }

    /**
     * When all promises have fulfilled, or if one promise rejects
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     */
    public static function all(iterable $promises): Promise {
        $promise = new static();
        $results = [];
        $offset = 0;
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $theirPromise = static::cast($theirPromise);
            $theirPromise->then(function(mixed $result) use (&$results, $offset, &$counter, $promise) {
                $results[$offset] = $result;
                if (--$counter === 0) {
                    $promise->resolve($results);
                }
            }, function(mixed $reason) use ($promise) {
                $promise->reject($reason);
            });
            $offset++;
        }
        return $promise;
    }

    /**
     * When all promises have settled, provides an array of settled promises.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/allSettled
     */
    public static function allSettled(iterable $promises): Promise {
        $promise = new static();
        $results = [];
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $results[] = $theirPromise = static::cast($theirPromise);
            $theirPromise->then(function(mixed $result) use (&$counter, $promise, &$results) {
                if (--$counter === 0) {
                    $promise->resolve($results);
                }
            });
        }
        return $promise;
    }

    /**
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/any
     */
    public static function any(iterable $promises): Promise {
        $promise = new static();
        $errors = [];
        $counter = count($promises);
        foreach ($promises as $offset => $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->resolve($result);
            }, function($reason) use ($promise, &$counter, $offset, &$errors) {
                $errors[$offset] = $reason;
                if (--$counter === 0) {
                    $promise->reject($errors);
                }
            });
        }
        return $promise;
    }

    /**
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/race
     */
    public static function race(iterable $promises): Promise {
        $promise = new static();
        foreach ($promises as $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->resolve($result);
            }, function($reason) use ($promise) {
                $promise->reject($reason);
            });
        }
        return $promise;
    }

    private mixed $result = null;
    private string $status = self::PENDING;
    private ?array $resolvers = [];
    private ?array $rejectors = [];
    private $canceller;
    private ?array $childPromises = [];
    private bool $fromThenable = false;

    public function __construct(callable $resolver=null, callable $canceller=null) {
        if ($resolver !== null) {
            $resolver($this->resolve(...), $this->reject(...));
        }
        $this->canceller = $canceller;
    }

    /**
     * Return 'pending', 'fulfilled', 'rejected' or 'cancelled'
     */
    public function status(): string {
        return $this->status;
    }

    /**
     * Return value if promise is fulfilled
     */
    public function value(): mixed {
        if ($this->status !== self::FULFILLED) {
            throw new \Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    /**
     * Return reason if promise is rejected
     */
    public function reason(): mixed {
        if ($this->status !== self::REJECTED) {
            throw new \Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    public function then(callable $onFulfilled=null, callable $onRejected=null): Promise {
        if ($this->status === self::PENDING) {
            if (null !== $onFulfilled) {
                $this->resolvers[] = $onFulfilled;
            }
            if (null !== $onRejected) {
                $this->rejectors[] = $onRejected;
            }
            $resolverIndex = count($this->resolvers);
            $rejectorIndex = count($this->rejectors);
            $nextPromise = new static(function(callable $resolver, callable $rejector) use ($resolverIndex, $rejectorIndex) {
                $this->resolvers[$resolverIndex] = $resolver;
                $this->rejectors[$rejectorIndex] = $rejector;
            }, function() use ($resolverIndex, $rejectorIndex) {
                // if the returned promise is cancelled, we must ensure the resolve or reject function is not invoked
                unset($this->resolvers[$resolverIndex]);
                unset($this->rejectors[$rejectorIndex]);
            });
            if ($this->canceller) {
                $this->childPromises[] = $nextPromise;
            }
            return $nextPromise;
        } elseif ($this->status === self::FULFILLED) {
            if (null !== $onFulfilled) {
                $onFulfilled($this->result);
            }
        } elseif ($this->status === self::REJECTED) {
            if (null !== $onRejected) {
                $onRejected($this->result);
            }
        } elseif ($this->status === self::CANCELLED) {
            // We'll return this same promise just to be API compliant
            return $this;
        }
        $nextPromise = new static();
        $nextPromise->status = $this->status;
        $nextPromise->result = $this->result;
        if ($this->canceller) {
            $this->childPromises[] = $nextPromise;
        }
        return $nextPromise;
    }

    public function otherwise(callable $onRejected): Promise {
        return $this->then(null, $onRejected);
    }

    public function cancel(): void {
        if ($this->status !== self::PENDING) {
            return;
        }
        $canceller = $this->canceller;
        $this->canceller = null;
        $this->status = self::CANCELLED;
        $this->result = null;
        $this->resolvers = nul;
        $this->rejectors = null;
        foreach ($this->childPromises as $childPromise) {
            $childPromise->cancel();
        }
        $this->childPromises = null;
        if ($canceller) {
            $canceller();
        }
    }

    /**
     * Get the current status of the promise (for compatability with other
     * promise implementations).
     */
    public function getState(): string {
        return $this->status;
    }

    /**
     * Resolve the promise with a value
     */
    public function resolve(mixed $result=null): void {
        if ($this->fromThenable) {
            throw new PromiseException("Promise was cast from Thenable and can't be externally resolved");
        }
        if ($this->status !== self::PENDING) {
            return;
        }
        $this->status = self::FULFILLED;
        $this->result = $result;
        $this->canceller = null;

        foreach ($this->resolvers as $resolver) {
            $resolver($result);
        }
        $this->resolvers = null;
        $this->rejectors = null;
        $this->childPromises = null;
    }

    /**
     * Reject the promise with a reason
     */
    public function reject(mixed $reason=null): void {
        if ($this->fromThenable) {
            throw new PromiseException("Promise was cast from Thenable and can't be externally rejected");
        }
        if ($this->status !== self::PENDING) {
            return;
        }
        $this->status = self::REJECTED;
        $this->result = $reason;
        $this->canceller = null;

        foreach ($this->rejectors as $rejector) {
            $rejector($reason);
        }
        $this->resolvers = null;
        $this->rejectors = null;
        $this->childPromises = null;
    }

    /**
     * Check that the entire array contains only objects with a then-method
     */
    private static function assertOnlyPromises(iterable $promises): void {
        foreach ($promises as $promise) {
            static::assertThenable($promise);
        }
    }

    private static function assertThenable(object $thenable): void {
        if ($thenable instanceof Promise) {
            return;
        }
        if (!method_exists($thenable, 'then')) {
            throw new ThenableExpectedError($thenable);
        }
        $rf = new ReflectionFunction($thenable->then(...));
        if ($rf->getNumberOfParameters() < 2) {
            throw new ThenableExpectedError($thenable);
        }
        $rp = $rf->getParameters();
        foreach ([0, 1] as $p) {
            if (!$rp[$p]->hasType()) {
                continue;
            }
            $rt = $rp[$p]->getType();

            if ($rt instanceof ReflectionNamedType && $rt->getName() === 'callable') {
                continue;
            } elseif ($rt instanceof ReflectionUnionType) {
                foreach ($rt->getTypes() as $rst) {
                    if ($rt->getName() === 'callable') {
                        continue 2;
                    }
                }
            }
            throw new ThenableExpectedError($thenable, $thenable::class.'::then() argument '.(1+$p).' has an unsupported type');
        }
    }

}
