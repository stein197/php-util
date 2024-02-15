<?php
namespace Stein197\Util;

use Countable;
use Iterator;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_shift;
use function fopen;
use function fclose;
use function get_include_path;
use function get_resource_id;
use function get_resource_type;
use function join;
use function preg_quote;
use function set_include_path;
use function spl_object_id;
use const DIRECTORY_SEPARATOR;
use const PATH_SEPARATOR;
use const PHP_INT_MAX;

class UtilTest extends TestCase {

	private const INCLUDE_PATH = '.' . PATH_SEPARATOR . __DIR__;

	private static string $includePath = '';
	
	#[After]
	public function after(): void {
		set_include_path(self::INCLUDE_PATH);
	}
	
	#region dump()

	#[Test]
	public function dump_when_it_is_a_null(): void {
		$this->assertEquals("null\n", dump(null));
	}

	#[Test]
	public function dump_when_it_is_a_boolean(): void {
		$this->assertEquals("false\n", dump(false));
		$this->assertEquals("true\n", dump(true));
	}

	#[Test]
	public function dump_when_it_is_a_number(): void {
		$this->assertEquals("12\n", dump(12));
	}

	#[Test]
	public function dump_when_it_is_a_string(): void {
		$this->assertEquals('\'string\\\'"\\\\\'', dump('string\'"\\', ''));
	}

	#[Test]
	public function dump_when_it_is_a_resource(): void {
		$f = fopen(__FILE__, 'r');
		$this->assertEquals('resource#' . get_resource_id($f) . '(' . get_resource_type($f) . ')', dump($f, ''));
		fclose($f);
	}

	#[Test]
	public function dump_when_it_is_a_function(): void {
		$this->assertMatchesRegularExpression('/callable#' . spl_object_id(dump(...)) . '\\(' . preg_quote(__DIR__ . DIRECTORY_SEPARATOR) . 'index\\.php:\\d+\\)/', dump(dump(...), ''));
	}

	#[Test]
	public function dump_when_it_is_an_array_and_no_indent(): void {
		$this->assertEquals("[null, 12, 'string', 'array' => ['a' => []]]", dump([null, 12, 'string', 'array' => ['a' => []]], ''));
	}

	#[Test]
	public function dump_when_it_is_an_array_and_has_indent(): void {
		$this->assertEquals("[\n\tnull,\n\t12,\n\t'string',\n\t'array' => [\n\t\t'a' => []\n\t]\n]\n", dump([null, 12, 'string', 'array' => ['a' => []]], "\t"));
	}

	#[Test]
	public function dump_when_it_is_an_array_and_sparsed_and_no_indent(): void {
		$this->assertEquals('[\'a\', 2 => \'c\']', dump(['a', 2 => 'c'], ''));
	}

	public function dump_when_it_is_an_array_and_sparsed_and_has_indent(): void {
		$this->assertEquals("[\n\t'a',\n\t2 => 'c'\n]\n", dump(['a', 2 => 'c'], "\t"));
	}

	#[Test]
	public function dump_when_it_is_a_stdClass_and_no_indent(): void {
		$this->assertEquals('(object) [\'null\' => null, \'string\' => \'string\', \'object\' => (object) [\'a\' => (object) []]]', dump((object) ['null' => null, 'string' => 'string', 'object' => (object) ['a' => (object) []]], ''));
	}

	#[Test]
	public function dump_when_it_is_a_stdClass_and_has_indent(): void {
		$this->assertEquals("(object) [\n\t'null' => null,\n\t'string' => 'string',\n\t'object' => (object) [\n\t\t'a' => (object) []\n\t]\n]\n", dump((object) ['null' => null, 'string' => 'string', 'object' => (object) ['a' => (object) []]], "\t"));
	}

	#[Test]
	public function dump_when_it_is_a_dumpable_and_no_indent(): void {
		$this->assertEquals('-0', dump(new class implements Dumpable {
			public function dump(string $indent, int $depth): string {
				return $indent . '-' . $depth;
			}
		}, ''));
	}

	#[Test]
	public function dump_when_it_is_a_dumpable_and_has_indent(): void {
		$this->assertEquals("\t-0\n", dump(new class implements Dumpable {
			public function dump(string $indent, int $depth): string {
				return $indent . '-' . $depth;
			}
		}, "\t"));
	}

	#[Test]
	public function dump_when_it_is_an_object(): void {
		$o = new class {};
		$this->assertEquals('object#' . spl_object_id($o) . '(' . $o::class . ')', dump($o, ''));
	}

	#[Test]
	public function dump_when_complex_nested_structure(): void {
		$this->markTestIncomplete();
	}

	#endregion

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

	#region include_path_append()

	#[Test]
	public function include_path_append_when_path_does_not_exist(): void {
		$this->assertTrue(include_path_append('/new/path'));
		$this->assertEquals(self::INCLUDE_PATH . PATH_SEPARATOR . path_normalize('/new/path'), get_include_path());
	}

	#[Test]
	public function include_path_append_when_path_exists(): void {
		$this->assertTrue(include_path_append('.'));
		$this->assertEquals(self::INCLUDE_PATH, get_include_path());
	}

	#endregion

	#region include_path_delete()

	#[Test]
	public function include_path_delete_should_do_nothing_when_path_does_not_exist(): void {
		$this->assertTrue(include_path_delete('/path'));
		$this->assertEquals(self::INCLUDE_PATH, get_include_path());
	}

	#[Test]
	public function include_path_delete_should_delete_path_when_path_exists(): void {
		$list = include_path_list();
		$first = array_shift($list);
		$this->assertTrue(include_path_delete($first));
		$this->assertEquals(join(PATH_SEPARATOR, $list), get_include_path());
	}

	#endregion

	#region include_path_get()

	#[Test]
	public function include_path_get_should_return_null_when_index_does_not_exist(): void {
		$this->assertNull(include_path_get(10));
	}

	#[Test]
	public function include_path_get_should_return_path_when_index_exists(): void {
		$this->assertEquals('.', include_path_get(0));
	}

	#endregion

	#region include_path_has()

	#[Test]
	public function include_path_has_should_return_false_when_path_does_not_exist(): void {
		$this->assertFalse(include_path_has('/path'));
	}

	#[Test]
	public function include_path_has_should_return_true_when_path_exists(): void {
		$this->assertTrue(include_path_has('.'));
	}

	#endregion

	#region include_path_index

	#[Test]
	public function include_path_index_should_return_index_when_path_exists(): void {
		$this->assertEquals(1, include_path_index(__DIR__));
	}

	#[Test]
	public function include_path_index_should_return_negative_when_path_does_not_exist(): void {
		$this->assertEquals(-1, include_path_index('/path'));
	}

	#endregion

	#region include_path_list()

	#[Test]
	public function include_path_list(): void {
		$this->assertEquals([
			'.',
			__DIR__
		], include_path_list());
	}

	#endregion

	#region include_path_prepend()

	#[Test]
	public function include_path_prepend_should_do_nothing_when_path_exists(): void {
		$this->assertTrue(include_path_prepend('.'));
		$this->assertEquals(self::INCLUDE_PATH, get_include_path());
	}

	#[Test]
	public function include_path_prepend_should_prepend_when_path_does_not_exist(): void {
		$this->assertTrue(include_path_prepend('/path'));
		$this->assertEquals(DIRECTORY_SEPARATOR . 'path' . PATH_SEPARATOR . self::INCLUDE_PATH, get_include_path());
	}

	#endregion

	#region include_path_set()

	#[Test]
	public function include_path_set_should_override_when_index_exists(): void {
		$this->assertTrue(include_path_set(1, 'path'));
		$this->assertEquals('.' . PATH_SEPARATOR . 'path', get_include_path());
	}

	#[Test]
	public function include_path_set_should_do_nothing_when_index_does_not_exist(): void {
		$this->assertFalse(include_path_set(10, 'path'));
		$this->assertEquals(self::INCLUDE_PATH, get_include_path());
	}

	#[Test]
	public function include_path_set_should_delete_when_path_is_null(): void {
		$this->assertTrue(include_path_set(1, null));
		$this->assertEquals('.', get_include_path());
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
		$this->assertEquals([0, 3, 6], $result);
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

	#region length()

	#[Test]
	public function length_when_is_string(): void {
		$this->assertEquals(3, length('abc'));
	}

	#[Test]
	public function length_when_is_array(): void {
		$this->assertEquals(3, length(['a', 'b', 'c']));
	}

	#[Test]
	public function length_when_is_stdClass(): void {
		$this->assertEquals(3, length((object) ['a', 'b', 'c']));
	}

	#[Test]
	public function length_when_is_iterable(): void {
		$this->assertEquals(3, length($this->getIterableForIterate(3)));
	}

	#[Test]
	public function length_when_is_Countable(): void {
		$this->assertEquals(3, length(new class implements Countable {
			public function count(): int {
				return 3;
			}
		}));
	}

	#[Test]
	public function length_when_is_string_and_empty(): void {
		$this->assertEquals(0, length(''));
	}

	#[Test]
	public function length_when_is_array_and_empty(): void {
		$this->assertEquals(0, length([]));
	}

	#[Test]
	public function length_when_is_stdClass_and_empty(): void {
		$this->assertEquals(0, length((object) []));
	}

	#[Test]
	public function length_when_is_iterable_and_empty(): void {
		$this->assertEquals(0, length($this->getIterableForIterate(0)));
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

	#[Test]
	public function property_exists_should_return_false_when_property_is_path_and_single_and_does_not_exist(): void {
		$this->assertFalse(property_exists(['a', 'b', 'c'], [4]));
	}

	#[Test]
	public function property_exists_should_return_true_when_property_is_path_and_single_and_exists(): void {
		$this->assertTrue(property_exists(['a', 'b', 'c'], [0]));
	}

	#[Test]
	public function property_exists_should_return_false_when_property_is_path_and_path_does_not_exist(): void {
		$this->assertFalse(property_exists(['a', 'b', 'c'], [0, 'a']));
	}

	#[Test]
	public function property_exists_should_return_true_when_property_is_path_and_path_exists(): void {
		$this->assertTrue(property_exists(['a' => ['b' => ['c' => 3]]], ['a', 'b', 'c']));
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

	#[Test]
	public function property_get_should_return_a_reference(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$c = &property_get($var, ['a', 'b', 'c']);
		$c = 0;
		$this->assertEquals(['a' => ['b' => ['c' => 0]]], $var);
	}

	#[Test]
	public function property_get_when_property_is_an_array_and_single(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertEquals(['b' => ['c' => 3]], property_get($var, ['a']));
	}

	#[Test]
	public function property_get_when_property_is_an_array_and_exists(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertEquals(3, property_get($var, ['a', 'b', 'c']));
	}

	#[Test]
	public function property_get_when_property_is_an_array_and_does_not_exist(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertNull(property_get($var, ['a', 'B']));
	}

	#[Test]
	public function property_get_when_property_is_an_array_and_empty(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertEquals($var, property_get($var, []));
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

	#region property_list_flat()

	#[Test]
	public function property_list_flat_should_return_an_empty_array_when_the_struct_is_empty(): void {
		$this->assertEmpty(property_list_flat([]));
		$this->assertEmpty(property_list_flat((object) []));
	}

	#[Test]
	public function property_list_flat_when_the_struct_is_flat(): void {
		$this->assertEquals([
			[['a'], 1],
			[['b'], 2],
			[['c'], 3]
		], property_list_flat(['a' => 1, 'b' => 2, 'c' => 3]));
		$this->assertEquals([
			[['a'], 1],
			[['b'], 2],
			[['c'], 3]
		], property_list_flat((object) ['a' => 1, 'b' => 2, 'c' => 3]));
	}

	#[Test]
	public function property_list_flat_when_the_struct_is_nested(): void {
		$this->assertEquals([
			[['a', 'b0'], 0],
			[['a', 'b1', 'c'], 3],
			[['a', 'b2'], 2]
		], property_list_flat(['a' => ['b0' => 0, 'b1' => ['c' => 3], 'b2' => 2]]));
		$this->assertEquals([
			[['a', 'b0'], 0],
			[['a', 'b1', 'c'], 3],
			[['a', 'b2'], 2]
		], property_list_flat((object) ['a' => (object) ['b0' => 0, 'b1' => ['c' => 3], 'b2' => 2]]));
	}

	#[Test]
	public function property_list_flat_when_the_struct_is_an_arraylist(): void {
		$this->assertEquals([
			[[0], 'a'],
			[[1], 'b'],
			[[2, 0], 'c'],
			[[2, 1, 0], 'd']
		], property_list_flat(['a', 'b', ['c', ['d']]]));
	}

	#endregion

	#region property_list_unflat()

	#[Test]
	public function property_list_unflat_should_return_an_empty_array_when_the_list_is_empty(): void {
		$this->assertEmpty(property_list_unflat([]));
	}

	#[Test]
	public function property_list_unflat_should_return_an_array_when_the_list_is_flat_and_isArray_is_true(): void {
		$this->assertEquals([
			'a' => 1,
			'b' => 2,
			'c' => 3
		], property_list_unflat([
			[['a'], 1],
			[['b'], 2],
			[['c'], 3]
		], true));
	}

	#[Test]
	public function property_list_unflat_should_return_an_array_when_the_list_is_nested_and_isArray_is_true(): void {
		$this->assertEquals([
			'a' => [
				'b' => [
					'c' => 3
				],
				'd' => 4
			]
		], property_list_unflat([
			[['a', 'b', 'c'], 3],
			[['a', 'd'], 4]
		], true));
	}

	#[Test]
	public function property_list_unflat_should_return_an_array_when_the_list_contains_ints_and_isArray_is_true(): void {
		$this->assertEquals(['a', ['b', ['c']]], property_list_unflat([
			[[0], 'a'],
			[[1, 0], 'b'],
			[[1, 1, 0], 'c']
		], true));
	}

	#[Test]
	public function property_list_unflat_should_return_an_stdClass_when_the_list_is_flat_and_isArray_is_falst(): void {
		$var = property_list_unflat([
			[['a'], 1],
			[['b'], 2],
			[['c'], 3]
		], false);
		$this->assertEquals(1, $var->a);
		$this->assertEquals(2, $var->b);
		$this->assertEquals(3, $var->c);
	}

	#[Test]
	public function property_list_unflat_should_return_an_stdClass_when_the_list_is_nested_and_isArray_is_falst(): void {
		$var = property_list_unflat([
			[['a', 'b', 'c'], 3],
			[['a', 'd'], 4]
		], false);
		$this->assertEquals(3, $var->a->b->c);
		$this->assertEquals(4, $var->a->d);
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

	#[Test]
	public function property_set_when_property_is_path_and_single(): void {
		$var = ['a' => 1];
		$this->assertTrue(property_set($var, ['a'], 10));
		$this->assertEquals(10, $var['a']);
	}

	#[Test]
	public function property_set_when_property_is_path_and_exists(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertTrue(property_set($var, ['a', 'b', 'c'], 30));
		$this->assertEquals(30, $var['a']['b']['c']);
	}

	#[Test]
	public function property_set_should_create_items_when_property_is_path_and_does_not_exist_and_isArray_true(): void {
		$var = [];
		$this->assertTrue(property_set($var, ['a', 'b'], 2, true));
		$this->assertEquals(['a' => ['b' => 2]], $var);
		$var = new stdClass;
		$this->assertTrue(property_set($var, ['a', 'b'], 2, true));
		$this->assertEquals(['b' => 2], $var->a);
	}

	#[Test]
	public function property_set_should_create_items_when_property_is_path_and_does_not_exist_and_isArray_false(): void {
		$var = [];
		$this->assertTrue(property_set($var, ['a', 'b'], 2, false));
		$this->assertEquals(2, $var['a']->b);
		$var = new stdClass;
		$this->assertTrue(property_set($var, ['a', 'b'], 2, false));
		$this->assertEquals(2, $var->a->b);
	}

	#[Test]
	public function property_set_should_override_items_when_property_is_path_and_does_not_exist_and_isArray_true(): void {
		$var = ['a' => 'string'];
		$this->assertTrue(property_set($var, ['a', 'b'], 2, true));
		$this->assertEquals(['a' => ['b' => 2]], $var);
		$var = new stdClass;
		$var->a = 'string';
		$this->assertTrue(property_set($var, ['a', 'b'], 2, true));
		$this->assertEquals(['b' => 2], $var->a);
	}

	#[Test]
	public function property_set_should_override_items_when_property_is_path_and_does_not_exist_and_isArray_false(): void {
		$var = ['a' => 'string'];
		$this->assertTrue(property_set($var, ['a', 'b'], 2, false));
		$this->assertEquals(2, $var['a']->b);
		$var = new stdClass;
		$var->a = 'string';
		$this->assertTrue(property_set($var, ['a', 'b'], 2, false));
		$this->assertEquals(2, $var->a->b);
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

	#[Test]
	public function property_unset_should_do_nothing_when_property_is_path_and_not_exists(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertTrue(property_unset($var, ['a', 'b', 'd']));
		$this->assertEquals(['a' => ['b' => ['c' => 3]]], $var);
	}

	#[Test]
	public function property_unset_should_unset_when_property_is_path_and_single(): void {
		$var = ['a' => 1, 'b' => 2, 'c' => 3];
		$this->assertTrue(property_unset($var, ['a']));
		$this->assertEquals(['b' => 2, 'c' => 3], $var);
	}

	#[Test]
	public function property_unset_should_unset_when_property_is_path_and_exists(): void {
		$var = ['a' => ['b' => ['c' => 3]]];
		$this->assertTrue(property_unset($var, ['a', 'b', 'c']));
		$this->assertEquals(['a' => ['b' => []]], $var);
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

	#region var_clone()

	#[Test]
	public function var_clone_when_the_arg_is_primitive_and_depth_is_min(): void {
		$this->assertEquals(null, var_clone(null, 0));
		$this->assertEquals(true, var_clone(true, 0));
		$this->assertEquals(12, var_clone(12, 0));
		$this->assertEquals('string', var_clone('string', 0));
	}

	#[Test]
	public function var_clone_when_the_arg_is_primitive_and_depth_is_max(): void {
		$this->assertEquals(null, var_clone(null, PHP_INT_MAX));
		$this->assertEquals(true, var_clone(true, PHP_INT_MAX));
		$this->assertEquals(12, var_clone(12, PHP_INT_MAX));
		$this->assertEquals('string', var_clone('string', PHP_INT_MAX));
	}

	#[Test]
	public function var_clone_when_the_arg_is_array_and_depth_is_min(): void {
		$this->assertEquals(['a' => ['b' => ['c' => 3]]], var_clone(['a' => ['b' => ['c' => 3]]], 1));
	}

	#[Test]
	public function var_clone_when_the_arg_is_array_and_depth_is_max(): void {
		$this->assertEquals(['a' => ['b' => ['c' => 3]]], var_clone(['a' => ['b' => ['c' => 3]]], PHP_INT_MAX));
	}

	#[Test]
	public function var_clone_when_the_arg_is_stdClass_and_depth_is_min(): void {
		$o = to_object(['a' => ['b' => ['c' => 3]]]);
		$clone = var_clone($o, 1);
		$o->a->b = 20;
		$this->assertEquals(20, $o->a->b);
		$this->assertEquals(20, $clone->a->b);
	}

	#[Test]
	public function var_clone_when_the_arg_is_stdClass_and_depth_is_max(): void {
		$o = to_object(['a' => ['b' => ['c' => 3]]]);
		$clone = var_clone($o, PHP_INT_MAX);
		$o->a->b = 20;
		$this->assertEquals(20, $o->a->b);
		$this->assertEquals(3, $clone->a->b->c);
	}

	#[Test]
	public function var_clone_when_the_arg_is_cloneable(): void {
		$o = $this->getCloneable(0);
		$o0 = var_clone($o);
		$this->assertEquals(0, $o->i);
		$this->assertEquals(1, $o0->i);
	}

	#[Test]
	public function var_clone_when_depth_is_0(): void {
		$o = to_object(['a' => ['b' => ['c' => 3]]]);
		$clone = var_clone($o, 0);
		$this->assertTrue($o === $clone);
	}

	#[Test]
	public function var_clone_when_depth_is_negative(): void {
		$o = to_object(['a' => ['b' => ['c' => 3]]]);
		$clone = var_clone($o, -10);
		$this->assertTrue($o === $clone);
	}

	#endregion

	#region var_equals()

	#[Test]
	public function var_equals_when_the_arguments_are_primitive(): void {
		$this->assertTrue(var_equals(null, null));
		$this->assertTrue(var_equals(false, false));
		$this->assertTrue(var_equals(12, 12));
		$this->assertTrue(var_equals('string', 'string'));
		$this->assertFalse(var_equals(false, true));
		$this->assertFalse(var_equals(12, 24));
		$this->assertFalse(var_equals('abc', 'def'));
	}

	#[Test]
	public function var_equals_should_return_false_when_the_arguments_are_primitive_and_they_arent_strictly_equal(): void {
		$this->assertFalse(var_equals(true, 1));
		$this->assertFalse(var_equals(12, '12'));
	}

	#[Test]
	public function var_equals_should_return_false_when_the_both_arguments_are_var_equals_and_have_different_types_and_strict_is_true(): void {
		$this->assertFalse(var_equals(['a' => ['b' => ['c' => 3]]], to_object(['a' => ['b' => ['c' => 3]]]), true));
	}

	#[Test]
	public function var_equals_should_return_true_when_the_both_arguments_are_var_equals_and_have_different_types_and_strict_is_false(): void {
		$this->assertTrue(var_equals(['a' => ['b' => ['c' => 3]]], to_object(['a' => ['b' => ['c' => 3]]]), false));
	}

	#[Test]
	public function var_equals_should_return_false_when_the_arguments_are_not_equal(): void {
		$this->assertFalse(var_equals(['a' => ['b' => ['c' => 3]]], ['a' => ['b' => ['c' => 3, 'd' => 4]]]));
	}

	#[Test]
	public function var_equals_when_the_first_object_implements_Equalable(): void {
		$this->assertTrue(var_equals(new class implements Equalable {
			public function equals(mixed $var): bool {
				return true;
			}
		}, null));
		$this->assertFalse(var_equals(new class implements Equalable {
			public function equals(mixed $var): bool {
				return false;
			}
		}, null));
	}

	#[Test]
	public function var_equals_when_the_second_object_implements_Equalable(): void {
		$this->assertTrue(var_equals(null, new class implements Equalable {
			public function equals(mixed $var): bool {
				return true;
			}
		}));
		$this->assertFalse(var_equals(null, new class implements Equalable {
			public function equals(mixed $var): bool {
				return false;
			}
		}));
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
				return $this->i;
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

	private function getCloneable(int $i): object {
		return new class ($i) {
			public function __construct(public int $i) {}
			public function __clone(): void {
				$this->i++;
			}
		};
	}

	#[BeforeClass]
	public static function beforeClass(): void {
		self::$includePath = get_include_path();
		set_include_path(self::INCLUDE_PATH);
	}

	#[AfterClass]
	public static function afterClass(): void {
		set_include_path(self::$includePath);
	}
}
