<?php
namespace Stein197\Util;

use Iterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase {

	#region function_track()

	public function function_track_should_work(): void {
		$f = function_track(fn (int $a, int $b): int => $a + $b);
		$this->assertEmpty($f->data());
		$f(1, 2);
		$this->assertEquals([['input' => [1, 2], 'output' => 3]], $f->data());
		$f(3, 4);
		$this->assertEquals([['input' => [1, 2], 'output' => 3], ['input' => [3, 4], 'output' => 7]], $f->data());
	}

	#endregion

	#region iterate()

	#[Test]
	public function iterate_when_is_string(): void {
		$this->assertEquals(['a', 'b', 'c'], [...iterate('abc')]);
	}

	#[Test]
	public function iterate_when_is_array(): void {
		$this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], [...iterate(['a' => 1, 'b' => 2, 'c' => 3])]);
	}

	#[Test]
	public function iterate_when_is_object(): void {
		$this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], [...iterate((object) ['a' => 1, 'b' => 2, 'c' => 3])]);
	}

	#[Test]
	public function iterate_when_is_iterable(): void {
		$result = [];
		foreach (iterate($this->getIterableForIterate(3)) as $k => $v)
			$result[$k] = $v;
		$this->assertEquals([0, 3 => 3, 6 => 6], $result);
	}

	#[Test]
	public function iterate_when_is_string_and_empty(): void {
		$this->assertEmpty([...iterate('')]);
	}

	#[Test]
	public function iterate_when_is_array_and_empty(): void {
		$this->assertEmpty([...iterate([])]);
	}

	#[Test]
	public function iterate_when_is_object_and_empty(): void {
		$this->assertEmpty([...iterate((object) [])]);
	}

	#[Test]
	public function iterate_when_is_iterable_and_empty(): void {
		$this->assertEmpty([...iterate($this->getIterableForIterate(0))]);
	}

	#endregion

	#region property_exists()

	#[Test]
	public function property_exists_should_return_true_when_is_array_and_property_exists(): void {
		$this->assertTrue(property_exists(['a' => 1], 'a'));
	}

	#[Test]
	public function property_exists_should_return_true_when_is_array_and_property_is_null(): void {
		$this->assertTrue(property_exists(['a' => null], 'a'));
	}

	#[Test]
	public function property_exists_should_return_true_when_is_object_and_property_exists(): void {
		$this->assertTrue(property_exists((object) ['a' => 1], 'a'));
	}

	#[Test]
	public function property_exists_should_return_true_when_is_object_and_property_is_null(): void {
		$this->assertTrue(property_exists((object) ['a' => null], 'a'));
	}

	#[Test]
	public function property_exists_should_return_true_when_is_object_and_property_is_dynamic(): void {
		$this->assertTrue(property_exists(new class (['a' => 1]) {
			public function __construct(private readonly array $props) {}
			public function __get(string $name): mixed {
				return @$this->props[$name];
			}
			public function __isset(string $name): bool {
				return isset($this->props[$name]);
			}
		}, 'a'));
	}

	#[Test]
	public function property_exists_should_return_false_when_is_array_and_property_does_not_exist(): void {
		$this->assertFalse(property_exists([], 'a'));
	}

	#[Test]
	public function property_exists_should_return_false_when_is_object_and_property_does_not_exist(): void {
		$this->assertFalse(property_exists((object) [], 'a'));
	}

	#[Test]
	public function property_exists_when_the_property_is_int(): void {
		$this->assertTrue(property_exists(['a', 'b', 'c'], 1));
	}

	#endregion

	#region property_get()

	#[Test]
	public function property_get_should_return_value_when_is_array_and_property_exists(): void {
		$var = ['a' => 1];
		$this->assertEquals(1, property_get($var, 'a'));
	}

	#[Test]
	public function property_get_should_return_value_when_is_object_and_property_exists(): void {
		$var = (object) ['a' => 1];
		$this->assertEquals(1, property_get($var, 'a'));
	}

	#[Test]
	public function property_get_should_return_null_when_is_array_and_property_does_not_exist(): void {
		$var = [];
		$this->assertNull(property_get($var, 'a'));
	}

	#[Test]
	public function property_get_should_return_null_when_is_object_and_property_does_not_exist(): void {
		$var = (object) [];
		$this->assertNull(property_get($var, 'a'));
	}

	#[Test]
	public function property_get_should_return_null_when_is_object_and_property_is_private(): void {
		$var = new class ('string') {
			public function __construct(private readonly string $a) {}
		};
		$this->assertNull(property_get($var, 'a'));
	}

	#[Test]
	public function property_get_when_the_property_is_int(): void {
		$var = ['a', 'b', 'c'];
		$this->assertEquals('b', property_get($var, 1));
	}

	#endregion

	#region property_list()

	#[Test]
	public function property_list_should_return_an_empty_array_when_the_array_is_empty(): void {
		$this->assertEmpty(property_list([]));
	}

	#[Test]
	public function property_list_should_return_an_empty_array_when_the_object_is_empty(): void {
		$this->assertEmpty(property_list((object) []));
	}

	#[Test]
	public function property_list_when_the_argument_is_an_array(): void {
		$this->assertEquals([0, 1, 2, 'a'], property_list(['a', 'b', 'c', 'a' => 1]));
	}

	#[Test]
	public function property_list_when_the_argument_is_an_object(): void {
		$this->assertEquals([0, 1, 2, 'a'], property_list((object) ['a', 'b', 'c', 'a' => 1]));
	}

	#endregion

	#region property_set()

	#[Test]
	public function property_set_should_set_when_is_array(): void {
		$var = [];
		$this->assertTrue(property_set($var, 'a', 1));
		$this->assertEquals(1, $var['a']);
	}

	#[Test]
	public function property_set_should_set_when_is_object(): void {
		$var = (object) [];
		$this->assertTrue(property_set($var, 'a', 1));
		$this->assertEquals(1, $var->a);
	}

	#[Test]
	public function property_set_should_not_set_when_is_object_and_property_is_readonly(): void {
		$var = new class ('string') {
			public function __construct(public readonly string $a) {}
		};
		$this->assertFalse(property_set($var, 'a', 'str'));
		$this->assertEquals('string', $var->a);
	}

	#[Test]
	public function property_set_should_not_set_when_is_object_and_property_is_private(): void {
		$var = new class ('string') {
			public function __construct(private string $a) {}
		};
		$this->assertFalse(property_set($var, 'a', 'str'));
		$this->assertNull(property_get($var, 'a'));
	}

	#[Test]
	public function property_set_should_not_set_when_is_object_and_property_cannot_be_overriden(): void {
		$var = new class ('string') {
			public function __construct() {}
			public function __get(string $name): mixed {
				return null;
			}
			public function __set(string $name, mixed $value): void {}
		};
		$this->assertFalse(property_set($var, 'a', 'str'));
		$this->assertNull($var->a);
	}

	#[Test]
	public function property_set_when_the_property_is_int(): void {
		$var = ['a', 'b', 'c'];
		$this->assertTrue(property_set($var, 1, 'B'));
		$this->assertEquals(['a', 'B', 'c'], $var);
	}

	#endregion
	
	#region property_unset()

	#[Test]
	public function property_unset_when_is_array(): void {
		$var = ['a' => 1];
		$this->assertTrue(property_unset($var, 'a'));
		$this->assertEmpty($var);
	}

	#[Test]
	public function property_unset_when_is_object(): void {
		$var = (object) ['a' => 1];
		$this->assertTrue(property_unset($var, 'a'));
		$this->assertNull(@$var->a);
	}

	#[Test]
	public function property_unset_should_do_nothing_when_is_object_and_not_possible_to_delete_property(): void {
		$var = new class () {
			private array $data;
			public function __get(string $name): mixed {
				return @$this->data[$name];
			}
			public function __set(string $name, mixed $value): void {
				$this->data[$name] = $value;
			}
			public function __unset(string $name): void {}
			public function __isset(string $name): bool {
				return isset($this->data[$name]);
			}
		};
		$var->a = 1;
		$this->assertFalse(property_unset($var, 'a'));
		$this->assertEquals(1, $var->a);
		$var = new class ('string') {
			public function __construct(private readonly string $a) {}
		};
		$this->assertFalse(property_unset($var, 'a'));
	}

	#[Test]
	public function property_unset_when_the_property_is_int(): void {
		$var = ['a', 'b', 'c'];
		$this->assertTrue(property_unset($var, 1));
		$this->assertEquals(['a', 2 => 'c'], $var);
	}

	#endregion

	#region to_array()

	#[Test]
	public function to_array_should_do_nothing_when_it_is_an_array(): void {
		$this->assertEquals(['a' => ['b' => ['c' => 3]]], to_array(['a' => ['b' => ['c' => 3]]]));
	}

	#[Test]
	public function to_array_should_deeply_convert_objects(): void {
		$this->assertEquals(['a' => ['b' => ['c' => 3]]], to_array((object) ['a' => (object) ['b' => (object) ['c' => 3]]]));
	}

	#[Test]
	public function to_array_should_stop_at_specified_depth(): void {
		$c = (object) ['c' => 3];
		$this->assertEquals(['a' => ['b' => $c]], to_array((object) ['a' => (object) ['b' => $c]], 2));
	}
	
	#[Test]
	public function to_array_should_set_depth_to_1_when_it_is_less_than_1(): void {
		$c = (object) ['c' => 3];
		$b = (object) ['b' => $c];
		$this->assertEquals(['a' => $b], to_array((object) ['a' => (object) ['b' => $c]], 0));
	}

	#endregion

	#region to_object()

	#[Test]
	public function to_object_should_do_nothing_when_it_is_an_object(): void {
		$c = (object) ['c' => 3];
		$b = (object) ['b' => $c];
		$a = (object) ['a' => $b];
		$this->assertEquals($a, to_object($a));
	}

	#[Test]
	public function to_object_should_deeply_convert_arrays(): void {
		$var = to_object(['a' => ['b' => ['c' => 3]]]);
		$this->assertEquals(3, $var->a->b->c);
	}

	#[Test]
	public function to_object_should_stop_at_specified_depth(): void {
		$this->assertEquals(3, to_object(['a' => ['b' => ['c' => 3]]], 2)->a->b['c']);
	}
	
	#[Test]
	public function to_object_should_set_depth_to_1_when_it_is_less_than_1(): void {
		$this->assertEquals(['b' => ['c' => 3]], to_object(['a' => ['b' => ['c' => 3]]], -1)->a);
	}

	#endregion

	private function getIterableForIterate(int $n): Iterator {
		return new class ($n) implements Iterator {
			private int $i = 0;
			public function __construct(private readonly int $steps) {}
			public function current(): mixed {
				return $this->i * $this->steps;
			}
			public function key(): mixed {
				return $this->current();
			}
			public function next(): void {
				$this->i++;
			}
			public function rewind(): void {
				$this->i = 0;
			}
			public function valid(): bool {
				return $this->i < $this->steps;
			}
		};
	}
}
