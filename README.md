# php streams
Lazy streams implementation, similar to Java streams, using generators

## Examples

### Example 1

- Take an integer stream `[1..10]` 
- filter even numbers: `[2,4,6,8,10]`
- add 1 to each number: `[3,5,7,9,11]`
- reduce:
  - 0
  - 2*0+3 = 3
  - 3*2+5 = 11
  - 11*2+7 = 29
  - 29*2+9 = 67
  - 67*2+11 = 145

```php
$s = Stream::intRangeClosed(1, 10)
	->filter(fn($x) => $x % 2 == 0)
	->map(fn($x) => $x + 1)
	->reduce(0, fn($x, $y) => 2 * $x + $y);
```

### Example 2: prime numbers

```php
/**
 * Returns a stream of prime numbers
 * 
 * @param int $max primes up to max value
 * @return Stream
 */
function primes(int $max): Stream
{
	$primes = Stream::intRangeClosed(2, $max);
	$sieve = fn($n) => fn($i) => $i === $n || $i % $n !== 0;

	$primes = $primes->filter($sieve(2));

	for ($i = 3; $i * $i <= $max; $i += 2) {
		$primes = $primes->filter($sieve($i));
	}

	return $primes;
}

echo json_encode(primes(100)->toArray()), PHP_EOL;
// [2,3,5,7,11,13,17,19,23,29,31,37,41,43,47,53,59,61,67,71,73,79,83,89,97]

echo json_encode(primes(100)->skip(10)->toArray()), PHP_EOL;
// [31,37,41,43,47,53,59,61,67,71,73,79,83,89,97]

echo primes(100)->sum(), PHP_EOL;
// 1060
```

### Example 3: infinite random numbers

Take the first 5 random numbers from an infinite stream:

```php
$top5 = Stream::generate(fn()=>random_int(1,100))->limit(5)->toArray();
```

### Example 4: group by

Example using a predefined collector:

```php
$map = Stream::intRangeClosed(1,10)->collect(Collectors::groupingBy(fn($x) => $x%2));
// [1 => [1,3,5,7,9], 0 => [2,4,6,8,10]]
```

## Documentation

Documentation produced using phpDocumentor:

- ` podman run --rm -v ${PWD}:/data phpdoc/phpdoc:3 -d ./src -t ./docs/api`