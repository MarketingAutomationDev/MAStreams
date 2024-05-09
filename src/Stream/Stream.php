<?php

namespace MA\Stream;

// https://docs.oracle.com/javase/8/docs/api/java/util/stream/Stream.html#flatMap-java.util.function.Function-
use Closure;
use Generator;
use Traversable;

/**
 * A sequence of elements supporting sequential aggregate operations.
 *
 * Example:
 *
 * ```php
 * Stream::of($generator)
 *   ->filter(fn($e) => $e->isReady())
 *   ->map(fn($e) => $e->getValue())
 *   ->sum();
 * ```
 *
 * In this example, $generator is a generator function (but could be an iterable too) that produces objects.
 * We create a stream of data via `Stream::of`, filter it to produce a stream containing only the ready ones,
 * and then transform it into a stream of numeric values. Then this stream is summed to produce a total.
 *
 * To perform a computation, stream operations are composed into a stream pipeline.
 * A stream pipeline consists of a source (which might be an iterable or a generator function),
 * zero or more intermediate operations (which transform a stream into another stream, such as filter(Predicate)),
 * and a terminal operation (which produces a result or side-effect, such as count() or forEach(Consumer)).
 *
 * Streams are lazy; computation on the source data is only performed when the terminal operation is initiated,
 * and source elements are consumed only as needed.
 *
 */
class Stream implements \IteratorAggregate
{
    /**
     * @param Generator $stream
     * @ignore
     */
    private function __construct(/** @ignore */ private Generator $stream)
    {
    }


    public function getIterator(): Traversable
    {
        return $this->stream;
    }

    public static function empty(): Stream
    {
        return Stream::of((function () {
            yield from [];
        })());
    }

    /**
     * @ignore
     */
    private static function traversable_to_generator(iterable $traversable): Generator
    {
        yield from $traversable;
    }

    public static function of(Generator|iterable $traversable): Stream
    {
        return new Stream(
            $traversable instanceof Generator
                ? $traversable
                : self::traversable_to_generator($traversable)
        );
    }

    /**
     * @ignore
     */
    private static function _ints(int $from, int $to, int $step = 1): Generator
    {
        if ($from === $to) {
            yield $from;
        } elseif (!(($from < $to && $step < 0)
            || ($from > $to && $step > 0)
            || $step === 0
        )) {
            if ($from <= $to) {
                for ($i = $from; $i <= $to; $i += $step) {
                    yield $i;
                }
            } else {
                for ($i = $from; $i >= $to; $i += $step) {
                    yield $i;
                }
            }
        }
    }

    /**
     * Returns a sequential ordered IntStream from startInclusive (inclusive) to endExclusive (exclusive) by the specified incremental step.
     *
     * @param int $startInclusive
     * @param int $endExclusive
     * @param int $step
     * @return Stream
     */
    public static function intRange(int $startInclusive, int $endExclusive, int $step = 1): Stream
    {
        if ($startInclusive === $endExclusive) {
            return Stream::empty();
        } elseif ($startInclusive < $endExclusive) {
            return new Stream(self::_ints($startInclusive, $endExclusive - 1, $step));
        } else {
            return new Stream(self::_ints($startInclusive, $endExclusive + 1, $step));
        }

    }

    /**
     * Returns a sequential ordered IntStream from startInclusive (inclusive) to endExclusive (exclusive) by the specified incremental step.
     *
     * @param int $startInclusive
     * @param int $endInclusive
     * @param int $step
     * @return Stream
     */
    public static function intRangeClosed(int $startInclusive, int $endInclusive, int $step = 1): Stream
    {
        return new Stream(self::_ints($startInclusive, $endInclusive, $step));
    }

    /**
     * @ignore
     */
    private static function _concat_closure(Stream $a, Stream $b): Generator
    {
        yield from $a->stream;
        yield from $b->stream;
    }

    /**
     * Creates a lazily concatenated stream whose elements are all the elements of the first stream
     * followed by all the elements of the second stream.
     *
     * @param Stream $a
     * @param Stream $b
     * @return Stream
     */
    public static function concat(Stream $a, Stream $b): Stream
    {
        return new Stream(Stream::_concat_closure($a, $b));
    }

    /**
     * @ignore
     */
    private static function _iterate_closure(mixed $seed, Closure $f): Generator
    {
        while (true) {
            yield $seed;
            $seed = $f($seed);
        }
    }

    /**
     * Returns an infinite sequential ordered Stream produced by iterative application of a function f
     * to an initial element seed, producing a Stream consisting of seed, f(seed), f(f(seed)), etc.
     *
     * @param mixed   $seed the initial element
     * @param Closure $f    a function to be applied to the previous element to produce a new element
     * @return Stream A new infinite stream
     */
    public static function iterate(mixed $seed, Closure $f): Stream
    {
        return new Stream(Stream::_iterate_closure($seed, $f));
    }

    /**
     * @ignore
     */
    private static function _generate_closure(Closure $supplier): Generator
    {
        while (true) {
            yield $supplier();
        }
    }

    /**
     * Returns an infinite sequential unordered stream where each element is generated by the provided Supplier.
     * This is suitable for generating constant streams, streams of random elements, etc.
     *
     * @param Closure $supplier the Supplier of generated elements
     * @return Stream A new infinite stream
     */
    public static function generate(Closure $supplier): Stream
    {
        return new Stream(Stream::_generate_closure($supplier));
    }

    //--------------------------------------

    /**
     * @ignore
     */
    private static function _map_filter(Generator $stream, callable $f): Generator
    {
        foreach ($stream as $item)
            if ($f($item)) yield $item;
    }

    public function filter(Closure $f): Stream
    {
        $this->stream = Stream::_map_filter($this->stream, $f);
        return $this;
    }

    /**
     * Helper function for map.
     *
     * @param Generator $stream
     * @param callable  $f
     * @return Generator
     * @ignore
     */
    private static function _map_closure(Generator $stream, callable $f): Generator
    {
        foreach ($stream as $item)
            yield $f($item);
    }

    public function map(callable $f): Stream
    {
        $this->stream = Stream::_map_closure($this->stream, $f);
        return $this;
    }

    public function reduce(mixed $identity, Closure $accumulator)
    {
        $result = $identity;
        foreach ($this->stream as $item) {
            $result = $accumulator($result, $item);
        }
        return $result;
    }

    /**
     * Returns the count of elements in this stream.
     *
     * This is a terminal operation.
     *
     * @return int
     */
    public function count(): int
    {
        return iterator_count($this->stream);
    }

    /**
     * Returns the sum of all elements into the stream.
     *
     * This method is a shortcut for `->collect(Collectors::summing())`.
     *
     * This is a short-circuiting terminal operation.
     *
     * @return float|int
     */
    public function sum(): float|int
    {
        return $this->collect(Collectors::summing());
    }

    /**
     * Returns the sum of all elements into the stream.
     *
     * This function is faster than `sum()` but less accurate.
     *
     * This is a short-circuiting terminal operation.
     *
     * @return float|int
     */
    public function sumInaccurate(): float|int
    {
        $tot = 0;

        if (!$this->stream->valid())
            return $tot;

        foreach ($this->stream as $v)
            $tot += $v;

        return $tot;
    }

    /**
     * Returns the minimum of all elements into the stream, or null for empty stream.
     *
     * This is a short-circuiting terminal operation.
     *
     * @return float|int|null
     */
    public function min(): float|int|null
    {
        if (!$this->stream->valid()) {
            return null;
        }

        $min = $this->stream->current();
        foreach ($this->stream as $item) {
            if ($item < $min) {
                $min = $item;
            }
        }
        return $min;
    }

    /**
     * Returns the minimum of all elements into the stream.
     *
     * This is a short-circuiting terminal operation.
     *
     * @return float|int|null
     */
    public function max(): float|int|null
    {
        if (!$this->stream->valid()) {
            return null;
        }

        $max = $this->stream->current();
        foreach ($this->stream as $item) {
            if ($item > $max) {
                $max = $item;
            }
        }
        return $max;
    }

    /**
     * Returns whether any elements of this stream match the provided predicate.
     * May not evaluate the predicate on all elements if not necessary for determining the result.
     * If the stream is empty then false is returned and the predicate is not evaluated.
     *
     * NOTE: This method evaluates the existential quantification of the predicate over the elements of the stream (for some x P(x)).
     *
     * This is a short-circuiting terminal operation.
     *
     * @param Closure $f
     * @return bool true if any elements of the stream match the provided predicate, otherwise false
     */
    public function anyMatch(Closure $f): bool
    {
        foreach ($this->stream as $item) {
            if ($f($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns whether all elements of this stream match the provided predicate.
     * May not evaluate the predicate on all elements if not necessary for determining the result.
     * If the stream is empty then true is returned and the predicate is not evaluated.
     *
     * NOTE: This method evaluates the universal quantification of the predicate over the elements of the stream (for all x P(x)).
     * If the stream is empty, the quantification is said to be vacuously satisfied and is always true (regardless of P(x))
     *
     * This is a short-circuiting terminal operation.
     *
     * @param Closure $f
     * @return bool true if either all elements of the stream match the provided predicate or the stream is empty, otherwise false
     */
    public function allMatch(Closure $f): bool
    {
        foreach ($this->stream as $item) {
            if (!$f($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns whether no elements of this stream match the provided predicate.
     * May not evaluate the predicate on all elements if not necessary for determining the result.
     * If the stream is empty then true is returned and the predicate is not evaluated.
     *
     * NOTE: This method evaluates the universal quantification of the negated predicate over the elements of the stream (for all x ~P(x)).
     * If the stream is empty, the quantification is said to be vacuously satisfied and is always true, regardless of P(x).
     *
     * This is a short-circuiting terminal operation.
     *
     * @param Closure $f
     * @return bool true if either no elements of the stream match the provided predicate or the stream is empty, otherwise false
     */
    public function noneMatch(Closure $f): bool
    {
        foreach ($this->stream as $item) {
            if ($f($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @ignore
     */
    private static function _distinct_closure(Generator $stream): Generator
    {
        $seen = [];
        foreach ($stream as $item) {
            if (!array_key_exists($item, $seen)) {
                $seen[$item] = true;
                yield $item;
            }
        }
        unset($seen);
    }

    /**
     * Returns a stream consisting of the distinct elements of this stream.
     *
     * This is a stateful intermediate operation.
     *
     * @return Stream the new stream
     */
    public function distinct(): Stream
    {
        $this->stream = Stream::_distinct_closure($this->stream);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _limit_closure(Generator $stream, int $maxSize): Generator
    {
        $count = 0;

        foreach ($stream as $item) {
            $count++;
            if ($count <= $maxSize) {
                yield $item;
            } else {
                break;
            }
        }
    }

    /**
     * Returns a stream consisting of the elements of this stream, truncated to be no longer than maxSize in length.
     *
     * @param int $maxSize
     * @return Stream
     */
    public function limit(int $maxSize): Stream
    {
        $this->stream = Stream::_limit_closure($this->stream, $maxSize);
        return $this;
    }


    /**
     * Returns a stream consisting of the elements of this stream,
     * sorted according to natural order or to the provided Comparator.
     *
     * This is a stateful intermediate operation.
     *
     * @param Closure|null $comparator
     * @return Stream
     */
    public function sorted(?Closure $comparator = null): Stream
    {
        $data = $this->toArray();
        if ($comparator === null) {
            sort($data);
        } else {
            usort($data, $comparator);
        }
        $this->stream = self::traversable_to_generator($data);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _peek_closure(Generator $stream, Closure $action): Generator
    {
        foreach ($stream as $item) {
            $action($item);
            yield $item;
        }
    }

    /**
     * Returns a stream consisting of the elements of this stream, additionally performing the provided action
     * on each element as elements are consumed from the resulting stream.
     *
     * This is an intermediate operation.
     *
     * @param Closure $action
     * @return Stream
     */
    public function peek(Closure $action): Stream
    {
        $this->stream = Stream::_peek_closure($this->stream, $action);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _flatMap_closure(Generator $stream, Closure $f): Generator
    {
        foreach ($stream as $item) {
            yield from $f($item);
        }
    }

    /**
     * Returns a stream consisting of the results of replacing each element of this stream
     * with the contents of a mapped stream produced by applying the provided mapping function to each element.
     *
     * This is an intermediate operation.
     *
     * @param Closure $f a non-interfering, stateless function to apply to each element which produces a stream of new values
     * @return Stream
     */
    public function flatMap(Closure $f): Stream
    {
        $this->stream = Stream::_flatMap_closure($this->stream, $f);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _flatten_closure(Generator $stream): Generator
    {
        foreach ($stream as $item) {
            if ($item instanceof \Traversable || is_array($item))
                yield from Stream::of($item)->flatten()->stream;
            else
                yield $item;
        }
    }

    /**
     * Returns a stream consisting of the results of replacing each Traversable or array element of this stream
     * with the contents of each element of the Traversable or array, recursively.
     *
     * This is an intermediate operation.
     *
     * @return Stream
     */
    public function flatten(): Stream
    {
        $this->stream = Stream::_flatten_closure($this->stream);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _skip_closure(Generator $stream, int $n): Generator
    {
        $count = 1;
        foreach ($stream as $item) {
            if ($count > $n)
                yield $item;
            else
                $count++;
        }
    }

    /**
     * Returns a stream consisting of the remaining elements of this stream after discarding the first n elements of the stream.
     * If this stream contains fewer than n elements then an empty stream will be returned.
     *
     * This is a stateful intermediate operation.
     *
     * @param int $n the number of leading elements to skip
     * @return Stream
     */
    public function skip(int $n): Stream
    {
        $this->stream = Stream::_skip_closure($this->stream, $n);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _takeWhile_closure(Generator $stream, Closure $predicate): Generator
    {
        foreach ($stream as $item) {
            if ($predicate($item)) {
                yield $item;
            } else {
                break;
            }
        }
    }

    /**
     * Returns a stream consisting of the longest prefix of elements taken from this stream
     * that match the given predicate.
     *
     * This is a short-circuiting stateful intermediate operation.
     *
     * @param Closure $predicate
     * @return Stream
     */
    public function takeWhile(Closure $predicate): Stream
    {
        $this->stream = Stream::_takeWhile_closure($this->stream, $predicate);
        return $this;
    }

    /**
     * @ignore
     */
    private static function _dropWhile_closure(Generator $stream, Closure $predicate): Generator
    {
        $skip = true;
        foreach ($stream as $item) {
            if (!($skip && $skip = $predicate($item))) {
                yield $item;
            }
        }
    }

    /**
     * Returns a stream consisting of the remaining elements of this stream
     * after dropping the longest prefix of elements that match the given predicate.
     *
     * This is a stateful intermediate operation.
     *
     * @param Closure $predicate
     * @return Stream
     */
    public function dropWhile(Closure $predicate): Stream
    {
        $this->stream = Stream::_dropWhile_closure($this->stream, $predicate);
        return $this;
    }

    /**
     * Returns a stream consisting of chunks with length elements.
     * The last chunk may contain less than length elements.
     * Each chunk is an array.
     *
     * This is a stateful intermediate operation.
     *
     * @param int $chunkSize
     * @return Stream
     */
    public function chunk(int $chunkSize): Stream
    {
        $closure = static function (Generator $stream) use ($chunkSize): Generator {
            $chunk = [];
            foreach ($stream as $item) {
                $chunk[] = $item;
                if (count($chunk) >= $chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }
            if (!empty($chunk))
                yield $chunk;
            unset($chunk);
        };

        $this->stream = $closure($this->stream);
        return $this;
    }

    /**
     * Performs an action for each element of this stream.
     *
     * This is a terminal operation.
     *
     * @param Closure $action a non-interfering action to perform on the elements
     * @return void
     */
    public function forEach(Closure $action): void
    {
        foreach ($this->stream as $item) {
            $action($item);
        }
    }

    /**
     * Returns the first element of this stream, or an null if the stream is empty.
     *
     * This is a short-circuiting terminal operation.
     *
     * @return mixed|null
     */
    public function findFirst(): mixed
    {
        return $this->stream->current();
    }

    public function collect(Collector $collector)
    {
        $container = $collector->supplier();
        foreach ($this->stream as $item)
            $collector->accumulator($container, $item);

        return $collector->finisher($container);
    }

    public function toArray(): array
    {
        return iterator_to_array($this->stream, false);
    }

    // of() varargs?
    // mapMulti
}