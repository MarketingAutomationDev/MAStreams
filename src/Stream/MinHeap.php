<?php

namespace RDA\Stream;

use Traversable;

class MinHeap implements \IteratorAggregate
{
	private array $arr = [];

	private function parent(int $i): int
	{
		return ($i - 1) >> 1;
	}

	private function left_child(int $i): int
	{
		return ($i << 1) + 1;
	}

	/**
	 * Equivalent to 1+left_child($i)
	 * @param int $i
	 * @return int
	 */
	private function right_child(int $i): int
	{
		return ($i + 1) << 1;
	}

	public function get_min()
	{
		return $this->arr[0] ?? null;
	}

	public function extract()
	{

		if (count($this->arr) > 1) {
			$min = $this->arr[0];
			$last_element = array_pop($this->arr);
			$this->arr[0] = $last_element;

			$this->heapify_top_bottom(0);
		} else {
			$min = array_shift($this->arr);
		}
		return $min;
	}

	public function insert($elem)
	{
		$this->arr[] = $elem;
		$this->heapify_bottom_top(count($this->arr) - 1);
	}

	public function extract_and_insert($elem)
	{
		if (count($this->arr) > 0) {
			$min = $this->arr[0];
			$this->arr[0] = $elem;
			if ($elem > $min) {
				$this->heapify_top_bottom(0);
			}
			return $min;
		} else {
			$this->insert($elem);
			return null;
		}
	}

	private function heapify_bottom_top(int $curr)
	{
		$parent = $this->parent($curr);

		while ($curr > 0 && $this->arr[$parent] > $this->arr[$curr]) {
			[$this->arr[$parent], $this->arr[$curr]] = [$this->arr[$curr], $this->arr[$parent]];
			$curr = $parent;
			$parent = $this->parent($curr);
		}
	}

	private function heapify_top_bottom(int $index)
	{
		$size = count($this->arr);

		if ($size <= 1) {
			return;
		}

		$smallest = $index;

		while (true) {
			$left = $this->left_child($smallest);
			$right = $left + 1; // equivalent to $this->right_child($smallest)

			if ($left < $size && $this->arr[$left] < $this->arr[$index]) {
				$smallest = $left;
			}

			if ($right < $size && $this->arr[$right] < $this->arr[$smallest]) {
				$smallest = $right;
			}

			if ($smallest !== $index) {
				[$this->arr[$index], $this->arr[$smallest]] = [$this->arr[$smallest], $this->arr[$index]];
				$index = $smallest;
			} else {
				break;
			}
		}
	}

	public function count()
	{
		return count($this->arr);
	}

	public function getIterator(): Traversable
	{
		sort($this->arr);
		return new \ArrayIterator($this->arr);
	}
}
