<?php
namespace MA\Stream;

/**
 * Interface representing a mechanism for collecting elements into a mutable container
 * and applying a finishing transformation to produce a final result.
 */
interface Collector
{
	/**
	 * A function that creates and returns a new mutable result container.
	 *
	 * @return mixed
	 */
	function supplier():mixed;

	/**
	 * A function that folds a value into a mutable result container.
	 *
	 * @param mixed $u mutable result container
	 * @param mixed $t Value
	 */
	function accumulator(mixed &$u, mixed $t): void;

	/**
	 * Perform the final transformation from the intermediate accumulation type A to the final result type R.
	 *
	 * @param mixed $a
	 * @return mixed
	 */
	function finisher(mixed &$a): mixed;

}