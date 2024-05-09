<?php

use PHPUnit\Framework\TestCase;
use MA\Stream\Collectors;
use MA\Stream\Stream;

final class StreamTest extends TestCase
{
    public function testEmptyStream(): void
    {
        $this->assertIsArray(Stream::empty()->toArray());
        $this->assertEquals([], Stream::empty()->toArray());
    }

    public function testStreamFromArray(): void
    {
        $this->assertIsArray(
            Stream::of([])->toArray()
        );
        $this->assertEquals(
            [1, 1, 2, 3, 5, 8, 13, 21],
            Stream::of([1, 1, 2, 3, 5, 8, 13, 21])->toArray()
        );
    }

    public function testRangeMapReduce(): void
    {
        $this->assertEquals(
            145,
            Stream::intRangeClosed(1, 10)
                ->filter(fn($x) => $x % 2 == 0)
                ->map(fn($x) => $x + 1)
                ->reduce(0, fn($x, $y) => 2 * $x + $y)
        );
    }

    private function primesStream(int $max): Stream
    {
        $primes = Stream::intRangeClosed(2, $max);
        $sieve = fn($n) => fn($i) => $i === $n || $i % $n !== 0;

        $primes = $primes->filter($sieve(2));

        for ($i = 3; $i * $i <= $max; $i += 2) {
            $primes = $primes->filter($sieve($i));
        }

        return $primes;
    }

    public function testPrimes(): void
    {
        $this->assertEquals(
            [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97],
            $this->primesStream(100)->toArray()
        );
    }

    public function testLimit(): void
    {
        $this->assertEquals(
            [2, 3, 5, 7, 11, 13, 17, 19, 23, 29],
            $this->primesStream(200000)->limit(10)->toArray()
        );
    }

    public function testSkip(): void
    {
        $this->assertEquals(
            [31, 37, 41, 43, 47, 53, 59, 61, 67, 71],
            $this->primesStream(200000)->skip(10)->limit(10)->toArray()
        );
    }

    public function testFlatten(): void
    {
        $this->assertEquals(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            Stream::of([1, [2, 3], 4, [5, [6, [7], 8], 9], 10])->flatten()->toArray());
    }

    public function testConcat(): void
    {
        $this->assertEquals(
            [2, 3, 5, 7, 11, 13, 17, 19, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            Stream::concat($this->primesStream(20), Stream::intRangeClosed(1, 10))->toArray()
        );
    }

    public function testSorted(): void
    {
        $this->assertEquals(
            [1, 2, 3, 4],
            Stream::of([4, 3, 2, 1])->sorted()->toArray()
        );

        // reverse sort
        $this->assertEquals(
            [4, 3, 2, 1],
            Stream::of([1, 2, 3, 4])->sorted(fn($a, $b) => $b - $a)->toArray()
        );
    }

    public function testAllMatch(): void
    {
        $this->assertEquals(
            true,
            $this->primesStream(2000)
                ->dropWhile(fn($x) => $x < 3) // skip primes less than 3
                ->allMatch(fn($x) => $x % 2 === 1) // are all odd numbers?
        );
    }

    public function testAnyMatch(): void
    {
        $this->assertEquals(
            true,
            $this->primesStream(2000)
                ->anyMatch(fn($x) => $x % 2 === 0) // does exist an even number?
        );
    }

    public function testNoneMatch(): void
    {
        $this->assertEquals(
            true,
            $this->primesStream(2000)
                ->dropWhile(fn($x) => $x < 3) // skip primes less than 3
                ->noneMatch(fn($x) => $x % 2 === 0) // is it true that none is even?
        );
    }

    public function testMinMax(): void
    {
        $this->assertNull(Stream::empty()->min());
        $this->assertNull(Stream::empty()->max());

        $this->assertEquals(
            2,
            $this->primesStream(2000)
                ->min()
        );
        $this->assertEquals(
            1999,
            $this->primesStream(2000)
                ->max()
        );
    }

    public function testFlatMap(): void
    {
        $this->assertEquals(
            [1, 3, 2, 5, 3, 7, 4, 9],
            Stream::of([1, 2, 3, 4])->flatMap(fn($x) => [$x, 2 * $x + 1])->toArray()
        );

        $this->assertEquals(
            [2, 3, 4, 5, 6, 7, 8, 9],
            Stream::of([[1, 2, 3], [4, 5, 6], [7], [8, 9, 10]])
                ->flatMap(function ($x) {
                    return Stream::of($x)->map(fn($e) => $e + 1)->filter(fn($e) => $e < 10);
                })
                ->toArray()
        );
    }

    public function testConcatDistinct(): void
    {
        $this->assertEquals(
            [2, 3, 5, 7, 11, 13, 17, 19, 1, 4, 6, 8, 9, 10],
            Stream::concat($this->primesStream(20), Stream::intRangeClosed(1, 10))->distinct()->toArray()
        );
    }

    public function testTakeWhileDropWhile(): void
    {
        $this->assertEquals(
            [53, 59, 61, 67, 71, 73, 79, 83, 89, 97],
            $this->primesStream(1000)
                ->takeWhile(fn($x) => $x < 100) // take primes while are less than 100, then stop taking
                ->dropWhile(fn($x) => $x < 50) // drop primes while are smaller than 50, then stop dropping
                ->toArray()
        );

        $this->assertEquals(
            [53, 59, 61, 67, 71, 73, 79, 83, 89, 97],
            $this->primesStream(1000)
                ->dropWhile(fn($x) => $x < 50) // drop primes while are smaller than 50, then stop dropping
                ->takeWhile(fn($x) => $x < 100) // take primes while are less than 100, then stop taking
                ->toArray()
        );
    }

    private function numsStream(int $n)
    {
        for ($i = 0; $i < $n; $i++) {
            foreach ([1, 1e100, 1, -1e100] as $v) {
                yield $v;
            }
        }
    }

    public function testSumming(): void
    {
        $this->assertEquals(
            2.0,
            Stream::of([1.0, 10e100, 1.0, -10e100])->sum()
        );
        $this->assertEquals(
            2.0,
            Stream::of([1.0, 10e100, 1.0, -10e100])->collect(Collectors::summing())
        );
        $this->assertEquals(
            20000.0,
            Stream::of($this->numsStream(10000))->collect(Collectors::summing())
        );
    }

    public function testAveraging(): void
    {
        $this->assertEquals(
            2.5,
            Stream::of([1, 2, 3, 4])->collect(Collectors::averaging())
        );

        $this->assertEquals(
            0.5,
            Stream::of([1.0, 10e100, 1.0, -10e100])->collect(Collectors::averaging())
        );

        $this->assertEquals(
            0.5,
            Stream::of($this->numsStream(10000))->collect(Collectors::averaging())
        );
    }

    public function testIterate(): void
    {
        $this->assertEquals(
            [1, 3, 7, 15, 31, 63, 127, 255, 511, 1023],
            Stream::iterate(1, fn($x) => 2 * $x + 1)->limit(10)->toArray()
        );
    }

    public function testGenerate(): void
    {
        $this->assertEquals(
            5,
            Stream::generate(fn() => random_int(1, 100))->limit(5)->count()
        );
    }

    public function testGroupingBy(): void
    {
        $this->assertEquals(
            [0 => [2, 4, 6, 8, 10], 1 => [1, 3, 5, 7, 9]],
            Stream::intRangeClosed(1, 10)->collect(Collectors::groupingBy(fn($x) => $x % 2))
        );
    }

    public function testToListToSet(): void
    {
        $this->assertEquals(
            [1, 1, 2, 1, 1, 3, 1, 1, 4],
            Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4])->collect(Collectors::toList())
        );
        $this->assertEquals(
            [1, 2, 3, 4],
            Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4])->collect(Collectors::toSet())
        );
    }

    public function testMaxHeapCollector(): void
    {
        $this->assertEquals(
            4,
            Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4, 1])
                ->collect(Collectors::of(fn() => new SplMaxHeap(), fn(SplMaxHeap &$u, $t) => $u->insert($t),
                    fn(&$a) => $a))
                ->top()
        );
    }

    public function testFindFirst(): void
    {
        $this->assertEquals(
            20,
            Stream::of([10, 20, 30])
                ->skip(1)
                ->findFirst()
        );
        $this->assertEquals(
            null,
            Stream::of([10, 20, 30])
                ->skip(4)
                ->findFirst()
        );
    }

    public function testChunk(): void
    {
        $this->assertEquals(
            [[1, 2, 3], [4, 5, 6], [7, 8]],
            Stream::of([1, 2, 3, 4, 5, 6, 7, 8])->chunk(3)->toArray()
        );
    }
}
