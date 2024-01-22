<?php
namespace Stein197\Util;

/**
 * Class that holds all the input and output data for the passed to the constructor function.
 * ```php
 * $ct = new CallTracker(fn (int $a, int $b): int => $a + $b);
 * $ct(1, 2);
 * $ct(3, 4);
 * $ct->data(); // [['input' => [1, 2], 'output' => 3], ['input' => [3, 4], 'output' => 7]]
 * ```
 */
final class CallTracker {

	private array $data = [];

	public function __construct(private readonly mixed $f) {}

	public function __invoke(mixed ...$args): mixed {
		$record = [
			'input' => $args,
			'output' => ($this->f)(...$args)
		];
		$this->data[] = $record;
		return $record['output'];
	}

	/**
	 * Get the tracked data.
	 * @return array Tracked data.
	 */
	public function data(): array {
		return $this->data;
	}
}
