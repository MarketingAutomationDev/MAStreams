<?php

use RDA\Stream\Collector;
use RDA\Stream\Collectors;
use RDA\Stream\Stream;


require __DIR__ . '/vendor/autoload.php';

function eurofxref(): Generator
{
    if (($handle = fopen("/home/delraa/Scaricati/eurofxref-hist.csv", "r")) !== false) {
        $keys = fgetcsv($handle, 10000, ",");
        while (($data = fgetcsv($handle, 10000, ",")) !== false) {
            yield array_combine($keys, $data);
        }
        fclose($handle);
    }
}

echo Stream::of(eurofxref())->filter(fn($e) => $e['Date'] === '2023-06-01')->map(fn($e) => $e['USD'])->sum(), PHP_EOL;
echo json_encode(Stream::of(eurofxref())->map(fn($e) => $e['USD'])->collect(Collectors::statistics())), PHP_EOL;

exit;
$t = microtime(true);

// fibonacci

$result = Stream::intRange(1, 30000)
    ->collect(
        Collectors::of(
            fn() => [0, 1],
            fn(&$u) => [$u[0], $u[1]] = [$u[1], bcadd($u[0], $u[1])],
            fn(&$a) => $a[1]
        )
    );
echo $result, PHP_EOL;
$t = microtime(true) - $t;

echo 'bench time: ', $t, PHP_EOL;
exit;

$t = microtime(true);
$total = 1505;
//$prices = [215, 275, 335, 355, 420, 580, 200];
//$prices = [215, 275, 335, 355, 420, 580, 200, 100, 105];
$prices = [215, 275, 335, 355, 420, 580, 200, 100, 105, 43, 42, 41, 40];

class S
{
    public function __construct(public int $curTot, public array $amounts)
    {
    }

    public function result(int $count): string
    {
        return json_encode(array_merge($this->amounts, array_fill(0, $count - count($this->amounts), 0)));
    }
}

$r = Stream::of([new S(0, [])]);
for ($j = 0; $j < count($prices); $j++)
    $r = $r->flatMap(
        fn(S $s) => Stream::intRangeClosed(0, (int)(($total - $s->curTot) / $prices[count($s->amounts)]))
            ->map(fn(int $i) => new S($s->curTot + $i * $prices[count($s->amounts)], array_merge($s->amounts, [$i])))
    );

$r = $r->filter(fn(S $s) => $s->curTot === $total)
    ->toArray();

$t = microtime(true) - $t;

/** @var S $s */
foreach ($r as $s)
    echo $s->result(count($prices)), PHP_EOL;
echo count($r), PHP_EOL;

echo 'bench time: ', $t, PHP_EOL;
exit;
// -dxdebug.mode=off oppure xdebug.max_nesting_level=512
// sum is 9160579439
$result = primes(10000000)->limit(40000)->sum();
echo 'result: ', json_encode($result), PHP_EOL;
$t = microtime(true) - $t;
echo 'bench time: ', $t, PHP_EOL;

function pprimes(int $max)
{
    $primes = function (int $min, int $max) {
        for ($i = $min; $i <= $max; ++$i) yield $i;
    };

    $filter = function (Generator $gen, Closure $filter) {
        foreach ($gen as $g)
            if ($filter($g)) yield $g;
    };

    $sieve = fn($n) => fn($i) => $i <= $n || $i % $n !== 0;

    $primes = $filter($primes(2, $max), $sieve(2));

    for ($i = 3; $i * $i <= $max; $i += 2) {
        $primes = $filter($primes, $sieve($i));
    }

    return $primes;
}

function limit(Generator $gen, int $limit): Generator
{
    foreach ($gen as $g) {
        if ($limit-- > 0)
            yield $g;
        else
            break;
    }
}

function sum(Generator $gen): int
{
    $tot = 0;
    foreach ($gen as $g) $tot += $g;
    return $tot;
}

$t = microtime(true);
$result = sum(limit(pprimes(10000000), 40000));
$t = microtime(true) - $t;
echo 'result: ', json_encode($result), PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;
exit;

function gen($a, $b)
{
    for ($i = $a; $i <= $b; $i++)
        yield $i;
}

echo json_encode(
    Stream::of([[1, 2, 3], [4, 5, 6], [7], [8, 9, 10]])
        ->flatMap(fn($x) => $x)
        ->toArray()
), PHP_EOL;

echo json_encode(
    Stream::of([[1, 2, 3], [4, 5, 6], [7], [8, 9, 10]])
        ->flatMap(function ($x) {
            return Stream::of($x)->map(fn($e) => $e + 1)->filter(fn($e) => $e < 10);
        })
        ->toArray()
), PHP_EOL;

echo json_encode(
    Stream::of([1, [2, 3], 4, [5, [6, [7], 8], 9], 10])
        ->flatten()
        ->toArray()
), PHP_EOL;
exit;


$t = microtime(true);
$maxIter = 1000000;
$result = Stream::of(gen(1, $maxIter))
    ->skip($maxIter - 10)
    ->count();
$t = microtime(true) - $t;
echo 'result: ', $result, PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;
exit;

$t = microtime(true);
$result = Stream::of(gen(1, 100000))
    ->map(fn($x) => 2 * $x + 1)
    ->map(fn($x) => $x - 1)
    ->filter(fn($x) => $x % 3 === 0)
    ->count();
$t = microtime(true) - $t;
echo 'result: ', $result, PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;

Stream::of(gen(1, 10))
    ->map(fn($x) => 2 * $x + 1)
    ->forEach(function ($e) {
        echo $e, PHP_EOL;
    });
exit;

// double s = DoubleStream.iterate(0, (f)->f<b, (f)->{return f + 0.0000001;}).map(f->Math.sin(f)).sum();
function arange($start, $end, $step)
{
    while ($start < $end) {
        yield $start;
        $start += $step;
    }
}

$step = 0.000001;

$t = microtime(true);
$result = Stream::of(arange(0.0, 2 * pi(), $step))->map(fn($x) => sin($x))->sum();
$t = microtime(true) - $t;
echo 'result: ', $result, " sum", PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;

$t = microtime(true);
$result = Stream::of(arange(0.0, 2 * pi(), $step))->map(fn($x) => sin($x))->sumInaccurate();
$t = microtime(true) - $t;
echo 'result: ', $result, " sumInaccurate", PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;

$t = microtime(true);

$a = 0.0;
$b = 2 * pi();
$s = 0.0;
while ($a < $b) {
    $s += sin($a);
    $a += $step;
}
$t = microtime(true) - $t;
echo 'result: ', $s, " while", PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;

$t = microtime(true);
$s = 0.0;
foreach (arange(0.0, 2 * pi(), $step) as $a) {
    $s += sin($a);
}
$t = microtime(true) - $t;
echo 'result: ', $s, "foreach arange", PHP_EOL;
echo 'bench time: ', $t, PHP_EOL;

exit;

$n = 1000000;
$k = 20;
$a = [];
for ($i = 0; $i < $n; ++$i) {
    $a[] = $i & 1 ? $i : -$i;
}//random_int(1, 10*$n);
/*
$a = Stream::intRangeClosed(1, $n)
	->map(fn($x) => random_int(1, 10*$n))
	//->distinct()
	->toArray();
*/
$b = $a;
$t = microtime(true);
sort($b);
$r = array_slice($b, -$k);
$t = microtime(true) - $t;
echo json_encode($r), PHP_EOL;
echo ' sort time: ', $t, PHP_EOL;

$t = microtime(true);
$r = Stream::of($a)->collect(Collectors::top_n($k));
$t = microtime(true) - $t;
//echo json_encode($r), PHP_EOL;
echo 'top_n time: ', $t, PHP_EOL;

$t = microtime(true);
$h = new SplMinHeap();
$b = false;
$m = null;
foreach ($a as $v) {
    if ($b) {
        if ($m < $v) {
            $m = $h->extract();
            $h->insert($v);
        }
    } else {
        $h->insert($v);
        if ($h->count() === $k) {
            $b = true;
            $m = $v;
        }
    }
}
$r = iterator_to_array($h, false);
$t = microtime(true) - $t;
echo json_encode($r), PHP_EOL;
echo '  spl time: ', $t, PHP_EOL;

// my heap
$t = microtime(true);
$h = new \RDA\Stream\MinHeap();
$b = false;
$m = null;
foreach ($a as $v) {
    if ($b) {
        if ($m < $v) {
            $m = $h->extract();
            $h->insert($v);
        }
    } else {
        $h->insert($v);
        if ($h->count() === $k) {
            $b = true;
            $m = $v;
        }
    }
}
$r = iterator_to_array($h, false);
$t = microtime(true) - $t;
echo json_encode($r), PHP_EOL;
echo ' heap time: ', $t, PHP_EOL;

// my heap2
$t = microtime(true);
$h = new \RDA\Stream\MinHeap();
$b = false;
$m = null;
foreach ($a as $v) {
    $h->insert($v);
    if ($h->count() > $k) {
        $h->extract();
    }
}
$r = iterator_to_array($h, false);
$t = microtime(true) - $t;
echo json_encode($r), PHP_EOL;
echo 'heap2 time: ', $t, PHP_EOL;

// my heap3
$t = microtime(true);
$h = new \RDA\Stream\MinHeap();
$b = false;
$m = null;
foreach ($a as $v) {
    if ($h->count() >= $k) {
        $h->extract_and_insert($v);
    } else {
        $h->insert($v);
    }
}
$r = iterator_to_array($h, false);
$t = microtime(true) - $t;
echo json_encode($r), PHP_EOL;
echo 'heap3 time: ', $t, PHP_EOL;
exit;

// SEE: https://en.wikipedia.org/wiki/Binary_heap#Heap_implementation
// https://en.wikipedia.org/wiki/Pairing_heap

$r = Stream::intRangeClosed(1, 100)
    ->map(fn($x) => random_int(1, 1000))
    ->distinct()
    ->collect(Collectors::top_n(10));
echo json_encode($r), PHP_EOL;
exit;

// median: https://www.geeksforgeeks.org/median-of-stream-of-running-integers-using-stl/

// https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance
// https://math.stackexchange.com/questions/198336/how-to-calculate-standard-deviation-with-streaming-inputs
// avg2: \mu_n = \mu_{n-1} + \frac{1}{n}(x_n - \mu_{n-1}), from https://diego.assencio.com/?index=c34d06f4f4de2375658ed41f70177d59

$res = Stream::of(sieve3(10))->map(fn($x) => 1 / $x)->collect(new class() implements Collector {
    public function supplier(): mixed
    {
        return [
            'sum' => 0,
            'sumsq' => 0,
            'count' => 0,
            'avg' => 0,
            'avg2' => 0,
            'min' => INF,
            'max' => -INF
        ];
    }

    public function accumulator(mixed &$u, mixed $t): void
    {
        $u['sum'] += $t;
        $u['sumsq'] += $t ** 2;
        $u['count']++;
        if ($t < $u['min']) {
            $u['min'] = $t;
        }
        if ($t > $u['max']) {
            $u['max'] = $t;
        }

        $u['avg2'] += ($t - $u['avg2']) / $u['count'];
    }

    public function finisher(mixed &$a): mixed
    {
        if ($a['count'] > 0) {
            $a['avg'] = $a['sum'] / (float)$a['count'];
            $a['variance'] = ($a['sumsq'] - ($a['sum'] ** 2) / $a['count']) / $a['count'];
        } else {
            $a['min'] = $a['max'] = null;
        }
        return $a;
    }

});
echo json_encode($res), PHP_EOL;
echo '--', PHP_EOL;
$res = Stream::of(sieve3(10))->map(fn($x) => 1 / $x)->collect(Collectors::statistics());
echo json_encode($res), PHP_EOL;
$res = Stream::of(sieve3(10))->map(fn($x) => 1 / $x)->toArray();
echo json_encode($res), PHP_EOL;
exit;

$s = Stream::intRangeClosed(1, 10)
    ->filter(fn($x) => $x % 2 == 0)
    ->map(fn($x) => $x + 1)
    ->reduce(0, fn($x, $y) => 2 * $x + $y);

print_r($s);
echo PHP_EOL;

/**
 * Returns a stream of prime numbers
 *
 * @param int $max primes up to max value
 * @return Stream
 */
function primes(int $max): Stream
{
    $primes = Stream::intRangeClosed(2, $max);
    $sieve = fn($n) => fn($i) => $i <= $n || $i % $n !== 0;

    $primes = $primes->filter($sieve(2));

    for ($i = 3; $i * $i <= $max; $i += 2) {
        $primes = $primes->filter($sieve($i));
    }

    return $primes;
}

echo json_encode(primes(100)->toArray()), PHP_EOL;
echo json_encode(primes(100)->skip(10)->toArray()), PHP_EOL;
echo primes(100)->sum(), PHP_EOL;

print_r(Stream::empty()->toArray());

$c = Stream::concat(primes(20), Stream::intRangeClosed(1, 10));
echo json_encode($c->toArray()), PHP_EOL;

$c = Stream::concat(primes(20), Stream::intRangeClosed(1, 10));
echo json_encode($c->distinct()->toArray()), PHP_EOL;

echo json_encode(primes(200000)->limit(10)->toArray()), PHP_EOL;

// sieve standard
$t = microtime(true);

$n = 1000000;
$ps = array_fill_keys(range(2, $n), true);
for ($i = 2; $i <= (int)sqrt($n); $i++) {
    if ($ps[$i] === true) {
        for ($j = $i ** 2; $j <= $n; $j += $i) {
            $ps[$j] = false;
        }
    }
}
$ps = array_keys($ps, true);

$t = microtime(true) - $t;
echo count($ps), ' in ', $t, PHP_EOL;

// sieve stream
$t = microtime(true);
function sieve2(int $n): Generator
{
    $ps = array_fill_keys(range(2, $n), true);
    $sqrt = (int)sqrt($n);
    for ($i = 2; $i <= $sqrt; $i++) {
        if ($ps[$i] === true) {
            yield $i;
            for ($j = $i ** 2; $j <= $n; $j += $i) {
                $ps[$j] = false;
            }
        }
    }

    for ($i = $sqrt + 1; $i < $n; $i++) {
        if ($ps[$i]) {
            yield $i;
        }
    }
}

$ps = Stream::of(sieve2($n))->toArray();
$t = microtime(true) - $t;
echo count($ps), ' in ', $t, PHP_EOL;

// with foreach
$t = microtime(true);
$ps = [];
foreach (sieve2($n) as $p) {
    $ps[] = $p;
}
$t = microtime(true) - $t;
echo count($ps), ' in ', $t, PHP_EOL;

// sieve stream revisited
$t = microtime(true);
function sieve3(int $n): Generator
{
    yield 2;
    $ps = [2];
    for ($i = 3; $i <= $n; $i++) {
        $res = true;
        $sqrt = (int)sqrt($i);
        for ($j = 0; $ps[$j] <= $sqrt; $j++) {
            if ($i % $ps[$j] === 0) {
                $res = false;
                break;
            }
        }
        if ($res) {
            yield $i;
            $ps[] = $i;
        }
    }
}

$ps = Stream::of(sieve3($n))->toArray();
$t = microtime(true) - $t;
echo count($ps), ' in ', $t, PHP_EOL;

exit;
/*
	primes = sieve [2..]
	  where
		sieve (p:xs) = p : sieve [x|x <- xs, x `mod` p > 0]
 */
function sieve(Generator $xs)
{
    $p = $xs->current();
    yield $p;

    $f = function () use ($p, $xs) {
        foreach ($xs as $x) {
            if ($x % $p !== 0) {
                yield $x;
            }
        }
    };
    $gen = $f();
    yield from sieve($gen);
}

function ints(): Generator
{
    $i = 2;
    while ($i) {
        yield $i++;
    }
}

$primes = Stream::of(sieve(ints()));
echo json_encode($primes->limit(10)->toArray()), PHP_EOL;

echo 'iterate', PHP_EOL;
echo json_encode(Stream::iterate(1, fn($x) => 2 * $x + 1)->limit(10)->toArray()), PHP_EOL;

echo json_encode(Stream::of(sieve(ints()))->limit(10)->peek(function ($e) {
    echo ">$e<", PHP_EOL;
})->toArray()), PHP_EOL;

echo 'flatMap', PHP_EOL;
echo json_encode(Stream::of([1, 2, 3, 4])->flatMap(fn($x) => [$x, 2 * $x + 1])->toArray()), PHP_EOL;

echo 'generate', PHP_EOL;
echo json_encode(Stream::generate(fn() => random_int(1, 100))->limit(5)->toArray()), PHP_EOL;
echo json_encode(Stream::generate(fn() => random_int(1, 100))->takeWhile(fn($x) => $x < 80)->toArray()), PHP_EOL;
echo '####', PHP_EOL;
echo json_encode(Stream::generate(fn() => random_int(1, 100))
    ->peek(function ($e) {
        echo ">$e<", PHP_EOL;
    })
    ->dropWhile(fn($x) => $x < 80)->limit(10)->toArray()), PHP_EOL;

echo 'takeWhile/dropWhile', PHP_EOL;
echo json_encode(primes(1000)->takeWhile(fn($x) => $x < 100)->dropWhile(fn($x) => $x < 50)->toArray()), PHP_EOL;

echo json_encode(Stream::generate(fn() => random_int(1, 100))->limit(5)->collect(Collectors::toList())), PHP_EOL;
echo json_encode(Stream::intRangeClosed(1, 10)->collect(Collectors::groupingBy(fn($x) => $x % 2))), PHP_EOL;

echo 'toList/toSet', PHP_EOL;
echo json_encode(Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4])->collect(Collectors::toList())), PHP_EOL;
echo json_encode(Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4])->collect(Collectors::toSet())), PHP_EOL;

echo json_encode(Stream::of([1, 2, 3, 4])->collect(Collectors::averaging(fn($x) => $x))), PHP_EOL;
echo json_encode(Stream::of([1, 2, 3, 4])->collect(Collectors::summing(fn($x) => $x))), PHP_EOL;

$nums = [1000000000000.0, 3.141592653589793, 2.718281828459045];
echo $nums[0] + $nums[1] + $nums[2], ' <-> ';
echo array_sum($nums), ' <-> ';
echo json_encode(Stream::of($nums)->collect(Collectors::summing(fn($x) => $x))), PHP_EOL;

$nums = [1.0, 10e100, 1.0, -10e100];
echo array_sum($nums), ' <-> ', json_encode(Stream::of($nums)->collect(Collectors::summing())), PHP_EOL;

// max heap collector
echo json_encode(Stream::of([1, 1, 2, 1, 1, 3, 1, 1, 4, 1])
    ->collect(Collectors::of(fn() => new SplMaxHeap(), fn(SplMaxHeap &$u, $t) => $u->insert($t), fn(&$a) => $a))
    ->top()), PHP_EOL;


$a = [];
// [1, 1e100, 1, -1e100] * 10000
function nums(int $n)
{
    for ($i = 0; $i < $n; $i++) {
        foreach ([1, 1e100, 1, -1e100] as $v) {
            yield $v;
        }
    }
}


echo array_sum(iterator_to_array(nums(10000))), ' <-> ', json_encode(Stream::of(nums(10000))->collect(Collectors::summing())), PHP_EOL;
