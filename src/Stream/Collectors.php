<?php

namespace RDA\Stream;

use Closure;

class Collectors
{
    public static function of(
        Closure $supplier,
        Closure $accumulator,
        Closure $finisher
    ): Collector {
        return new class($supplier, $accumulator, $finisher) implements Collector {

            public function __construct(
                protected readonly Closure $_supplier,
                protected readonly Closure $_accumulator,
                protected readonly Closure $_finisher
            ) {
            }

            public function supplier(): mixed
            {
                return ($this->_supplier)();
            }

            public function accumulator(mixed &$u, mixed $t): void
            {
                ($this->_accumulator)($u, $t);
            }

            public function finisher(mixed &$a): mixed
            {
                return ($this->_finisher)($a);
            }

        };
    }

    /**
     * Returns a Collector implementing a "group by" operation on input elements,
     * grouping elements according to a classification function, and returning the results in an associative array.
     *
     * @param Closure $classifier Classification function
     * @return Collector
     */
    public static function groupingBy(Closure $classifier): Collector
    {
        return self::of(
            fn() => [],
            fn(&$t, $m) => $t[$classifier($m)][] = $m,
            fn(&$list) => $list
        );
    }

    /**
     * Returns a Collector that accumulates the input elements into a new Set
     *
     * @return Collector
     */
    public static function toSet(): Collector
    {
        return self::of(
            fn() => [],
            fn(&$t, $m) => $t[$m] = true,
            fn(&$list) => array_keys($list)
        );
    }

    /**
     * Returns a Collector that accumulates the input elements into a new List.
     *
     * @return Collector
     */
    public static function toList(): Collector
    {
        return self::of(
            fn() => [],
            fn(&$u, $t) => $u[] = $t,
            fn(&$list) => $list // dovrebbe controllare se è array o traversable
        );
    }

    /**
     * Incorporate a new double value using Kahan-Babushka-Klein summation algorithm.
     * High-order bits of the sum are in intermediateSum[0], low-order bits of the sum are in intermediateSum[1],
     * any additional elements are application-specific.
     *
     * @see https://en.wikipedia.org/wiki/Kahan_summation_algorithm
     *
     * @param array     $intermediateSum [sum, cs, ccs]
     * @param int|float $value           value to be added
     * @return void
     * @ignore
     */
    private static function sumWithCompensation(array &$intermediateSum, int|float $value): void
    {
        $t = $intermediateSum[0] + $value;
        if (abs($intermediateSum[0]) >= abs($value)) {
            $c = ($intermediateSum[0] - $t) + $value;
        } else {
            $c = ($value - $t) + $intermediateSum[0];
        }
        $intermediateSum[0] = $t;
        $t = $intermediateSum[1] + $c;
        if (abs($intermediateSum[1]) >= abs($c)) {
            $cc = ($intermediateSum[1] - $t) + $c;
        } else {
            $cc = ($c - $t) + $intermediateSum[1];
        }
        $intermediateSum[1] = $t;
        $intermediateSum[2] += $cc;
    }

    /**
     * If the compensated sum is spuriously NaN from accumulating one
     * or more same-signed infinite values, return the
     * correctly-signed infinity stored in the simple sum.
     *
     * @param array $summands
     * @return mixed
     * @ignore
     */
    private static function computeFinalSum(array $summands): mixed
    {
        // Final sum with better error bounds subtract second summand as it is negated
        $tmp = $summands[0] + $summands[1] + $summands[2];
        $simpleSum = $summands[array_key_last($summands)];
        return is_nan($tmp) && is_infinite($simpleSum) ? $simpleSum : $tmp;
    }

    /**
     * Returns a Collector that produces the arithmetic mean of a numeric-valued function applied to the input elements.
     * If no elements are present, the result is 0.
     *
     * The average returned can vary depending upon the order in which values are recorded,
     * due to accumulated rounding error in addition of values of differing magnitudes.
     * Values sorted by increasing absolute magnitude tend to yield more accurate results.
     *
     * The collector make use of the Kahan-Babushka-Klein summation algorithm.
     *
     * @see https://en.wikipedia.org/wiki/Kahan_summation_algorithm
     *
     * @param Closure|null $mapper a function extracting the property to be averaged (default: identity)
     * @return Collector A Collector that produces the average of a derived property.
     */
    public static function averaging(?Closure $mapper = null): Collector
    {
        return self::of(
            fn() => [0, 0, 0, 0],
            function (&$a, $val) use ($mapper) {
                if ($mapper !== null) {
                    $val = $mapper($val);
                }
                self::sumWithCompensation($a, $val);
                $a[3]++;
            },
            fn($a) => $a[3] == 0 ? 0 : (self::computeFinalSum($a) / $a[3]),
        );
    }

    /**
     * Returns a Collector that produces the sum of a double-valued function applied to the input elements.
     * If no elements are present, the result is 0.
     *
     * The sum returned can vary depending upon the order in which values are recorded,
     * due to accumulated rounding error in addition of values of differing magnitudes.
     * Values sorted by increasing absolute magnitude tend to yield more accurate results.
     * If any recorded value is a NaN or the sum is at any point a NaN then the sum will be NaN.
     *
     * The collector make use of the Kahan-Babushka-Klein summation algorithm.
     *
     * @see https://en.wikipedia.org/wiki/Kahan_summation_algorithm
     *
     * @param Closure|null $mapper A function extracting the property to be summed (default: identity)
     * @return Collector A Collector that produces the sum of a derived property.
     */
    public static function summing(?Closure $mapper = null): Collector
    {
        if ($mapper === null) {
            $accumulator = Collectors::_summing_accumulator(...);
        } else {
            $accumulator = function (&$a, $val) use ($mapper) {
                $val = $mapper($val);
                self::sumWithCompensation($a, $val);
                $a[3] += $val;
            };
        }

        return self::of(
            fn() => [0, 0, 0, 0],
            $accumulator,
            fn($a) => self::computeFinalSum($a),
        );
    }

    /**
     * @ignore
     */
    private static function _summing_accumulator(&$a, $val): void
    {
        self::sumWithCompensation($a, $val);
        $a[3] += $val;
    }

    /**
     * Returns a Collector that produces simple statistics of the input elements.
     *
     * The statistics are: sum, count, average, min and max
     *
     * @param Closure|null $mapper a function extracting the property to be summed (default: identity)
     * @return Collector
     */
    public static function statistics(?Closure $mapper = null): Collector
    {
        return self::of(
            fn() => [
                'sum' => [0, 0, 0, 0],
                'count' => 0,
                'avg' => 0,
                'min' => INF,
                'max' => -INF
            ],
            function (&$a, $val) use ($mapper) {
                if ($mapper !== null) {
                    $val = $mapper($val);
                }
                self::sumWithCompensation($a['sum'], $val);
                $a['sum'][3] += $val;

                $a['count']++;

                if ($val < $a['min']) {
                    $a['min'] = $val;
                }
                if ($val > $a['max']) {
                    $a['max'] = $val;
                }
            },
            function (&$a) {
                $a['sum'] = self::computeFinalSum($a['sum']);

                if ($a['count'] > 0) {
                    $a['avg'] = $a['sum'] / (float)$a['count'];
                } else {
                    $a['min'] = $a['max'] = null;
                }
                return $a;
            }
        );
    }

    /**
     * Returns a Collector that produces the top N elements from the input.
     *
     * Computational complexity: O(n*log k), where n=number of input elements and k=number of output elements
     *
     * @param int          $maxElements how many elements
     * @param Closure|null $comparator  optional comparator (default: identity)
     * @return Collector
     */
    public static function top_n(int $maxElements, ?Closure $comparator = null): Collector
    {
        return self::of(
        /* [bool, mixed, SplHeap ] */
            fn() => $comparator === null
                ? [false, null, new \SplMinHeap()]
                : [
                    false,
                    null,
                    new class($comparator) extends \SplHeap {
                        public function __construct(
                            protected readonly Closure $_comparator,
                        ) {
                        }

                        protected function compare(mixed $value1, mixed $value2): int
                        {
                            return -($this->_comparator)($value1, $value2);
                        }
                    }
                ],
            function (array &$heap, $elem) use ($maxElements) {
                if ($heap[0]) {
                    if ($heap[1] < $elem) {
                        $heap[1] = $heap[2]->extract();
                        $heap[2]->insert($elem);
                    }
                } else {
                    $heap[2]->insert($elem);
                    if ($heap[2]->count() === $maxElements) {
                        $heap[0] = true;
                        $heap[1] = $elem;
                    }
                }
            },
            fn(array &$heap) => iterator_to_array($heap[2], false)
        );
    }
    /*
     * Non optimized version:

    public static function top_n_pure(int $maxElements, ?Closure $comparator = null): Collector
    {
        return self::of(
            fn() => $comparator === null
                ? new \SplMinHeap()
                : new class($comparator) extends \SplHeap {
                    public function __construct(
                        protected readonly Closure $_comparator,
                    ) {
                    }

                    protected function compare(mixed $value1, mixed $value2): int
                    {
                        return -($this->_comparator)($value1, $value2);
                    }
                },
            function (\SplHeap &$heap, $elem) use ($maxElements) {
                $heap->insert($elem);
                if ($heap->count() > $maxElements) {
                    $heap->extract();
                }
            },
            fn(\SplHeap &$heap) => iterator_to_array($heap, false)
        );
    }
     */
}
