<?php
namespace Stein197\Util;

use Countable;
use Error;
use ReflectionFunction;
use stdClass;
use function array_filter;
use function array_key_exists;
use function array_pop;
use function array_search;
use function explode;
use function is_array;
use function is_callable;
use function is_int;
use function is_iterable;
use function is_object;
use function is_resource;
use function is_string;
use function join;
use function get_include_path;
use function get_object_vars;
use function get_resource_id;
use function get_resource_type;
use function preg_replace;
use function set_include_path;
use function sizeof;
use function spl_object_id;
use function str_repeat;
use function str_split;
use function strlen;
use function trim;
use function var_export;
use const DIRECTORY_SEPARATOR;
use const PATH_SEPARATOR;
use const PHP_INT_MAX;

// TODO: merge(object | array ...$data): object | array
// TODO: traverse(object | iterable $var, callable $f): object | iterable / traverse(object | iterable $var): Generator

/**
 * Dump a variable. Basically it's the same as `var_dump()` or `var_export()`, except that this function:
 * - returns a string instead of printing directly into stdout
 * - allows to pretty print the output for arrays and other structures
 * - prints the type of a resource and the source of a function (if possible)
 * - allows custom dumping for classes implementing the `Dumpable` interface
 * @param mixed $value Value to dump.
 * @param string $origIndent Indentation to use. Empty string means no pretty-printing.
 * @param int $depth Indentation depth to print. Works only if `$pretty` is `true`.
 * @return string Dumped output.
 */
function dump(mixed $value, string $indent = "\t", int $depth = 0): string {
	$origIndent = $indent;
	$indent = str_repeat($origIndent, $depth);
	$lf = $origIndent ? "\n" : '';
	if ($value instanceof Dumpable)
		return $value->dump($origIndent, $depth) . $lf;
	$isStdClass = $value instanceof stdClass;
	if (is_array($value) || $isStdClass) {
		$result = $indent . ($isStdClass ? '(object) ' : '') . '[';
		if (!length($value))
			return $result . ']';
		$nextDepth = $depth + 1;
		$result .= $lf . str_repeat($origIndent, $nextDepth);
		$needIndex = key_first($value) !== 0;
		$prevKey = null;
		$list = [];
		foreach ($value as $k => $v) {
			if ($prevKey !== null)
				$needIndex = !is_int($prevKey) || !is_int($k) || $prevKey + 1 !== $k;
			$list[] = ($needIndex ? dump($k, false) . ' => ' : '') . trim(dump($v, $origIndent, $nextDepth));
			$prevKey = $k;
		}
		return $result . join(',' . $lf . ($origIndent ? str_repeat($origIndent, $nextDepth) : ' '), $list) . $lf . $indent . ']' . $lf;
	}
	if (is_callable($value)) {
		$info = new ReflectionFunction($value);
		return $indent . 'callable#' . spl_object_id($value) . ($info->getFileName() ? '(' . $info->getFileName() . ':' . $info->getStartLine() . ')' : '') . $lf;
	}
	if (is_object($value))
		return $indent . 'object#' . spl_object_id($value) . '(' . $value::class . ')' . $lf;
	if (is_resource($value))
		return $indent . 'resource#' . get_resource_id($value) . '(' . get_resource_type($value) . ')' . $lf;
	return $indent . ($value === null ? 'null' : var_export($value, true)) . $lf;
}

/**
 * Track function calls - inputs and outputs.
 * @param callable $f Function to track.
 * @return CallTracker Tracked function.
 * ```php
 * $f = function_track(fn (int $a, int $b): int => $a + $b);
 * $f(1, 2);
 * $f(3, 4);
 * $f->data(); // [['input' => [1, 2], 'output' => 3], ['input' => [3, 4], 'output' => 7]]
 * ```
 */
function function_track(callable $f): CallTracker {
	return new CallTracker($f);
}

/**
 * Add a path to the end of the include_path. Do nothing if the path is already contained in the include_path.
 * @param string $path Path to append.
 * @return bool `true` if the addition succeeded.
 * ```php
 * get_include_path(); // '.;C:\\php\\pear'
 * include_path_append('another/path');
 * get_include_path(); // '.;C:\\php\\pear;another\\path'
 * ```
 */
function include_path_append(string $path): bool {
	$includePath = get_include_path();
	return include_path_has($path) || $includePath !== false && !!set_include_path($includePath . PATH_SEPARATOR . path_normalize($path));
}

/**
 * Delete a given path from the include_path. Do nothing if the path is not contained in the include_path.
 * @param string $path Path to delete.
 * @return bool `true` if the deletion succeeded.
 * ```php
 * get_include_path(); // '.;C:\\php\\pear'
 * include_path_delete('.');
 * get_include_path(); // 'C:\\php\\pear'
 * ```
 */
function include_path_delete(string $path): bool {
	$index = include_path_index($path);
	return include_path_set($index, null);
}

/**
 * Return a path by the provided index.
 * @param int $index Index to return a path by.
 * @return null|string Path at the index or `null` if there is no paths at the index.
 * ```php
 * get_include_path();  // '.;C:\\php\\pear'
 * include_path_get(0); // '.'
 * ```
 */
function include_path_get(int $index): ?string {
	return @include_path_list()[$index];
}

/**
 * Check if the provided path is contained in the include_path.
 * @param string $path Path to check the existance of.
 * @return bool `true` if the path is contained in the include_path.
 * ```php
 * get_include_path();    // '.;C:\\php\\pear'
 * include_path_has('.'); // true
 * ```
 */
function include_path_has(string $path): bool {
	return include_path_index($path) >= 0;
}

/**
 * Get the index of a provided path that's contained in the include_path.
 * @param string $path Path to return the index of.
 * @return int An index or -1 of the path is not contained in the include_path.
 * ```php
 * get_include_path();      // '.;C:\\php\\pear'
 * include_path_index('.'); // 0
 * ```
 */
function include_path_index(string $path): int {
	$result = array_search(path_normalize($path), include_path_list());
	return $result === false ? -1 : $result;
}

/**
 * Return an array of paths contained in the include_path.
 * @return string[] Array of paths in the include_path.
 * ```php
 * get_include_path();  // '.;C:\\php\\pear'
 * include_path_list(); // ['.', 'C:\\php\\pear']
 * ```
 */
function include_path_list(): array {
	$includePath = get_include_path();
	return $includePath ? explode(PATH_SEPARATOR, $includePath) : [];
}

/**
 * Add a path to the beginning of the include_path. Do nothing if the path is already contained in the include_path.
 * @param string $path Path to prepend.
 * @return bool `true` if the addition succeeded.
 * ```php
 * get_include_path(); // '.;C:\\php\\pear'
 * include_path_prepend('another/path');
 * get_include_path(); // 'another\\path;.;C:\\php\\pear'
 * ```
 */
function include_path_prepend(string $path): bool {
	$includePath = get_include_path();
	return include_path_has($path) || $includePath !== false && !!set_include_path(path_normalize($path) . PATH_SEPARATOR . $includePath);
}

/**
 * Set or unset a path by the given index.
 * @param int $index Index to delete at.
 * @param null|string $path New value or `null` to delete a value.
 * @return bool `true` if deletion succeeded.
 * ```php
 * get_include_path(); // '.;C:\\php\\pear'
 * include_path_set(0, null);
 * get_include_path(); // 'C:\\php\\pear'
 * ```
 */
function include_path_set(int $index, ?string $path): bool {
	$list = include_path_list();
	if (!isset($list[$index]) && $path)
		return false;
	if ($path)
		$list[$index] = path_normalize($path);
	else
		unset($list[$index]);
	return set_include_path(join(PATH_SEPARATOR, array_filter($list, fn (?string $path): bool => $path !== null))) !== false;
}

/**
 * Iterate through iterables - strings, arrays, objects and iterables.
 * @param string|object|iterable $var Variable to iterate through.
 * @return iterable Iterable.
 * ```php
 * foreach (iterator('abc') as $i => $c);
 * foreach (iterator(['a', 'b', 'c']) as $i => $c);
 * foreach (iterator((object) ['a' => 1, 'b' => 2, 'c' => 3]) as $key => $val);
 * ```
 */
function iterate(string | object | iterable $var): iterable {
	return is_iterable($var) ? $var : (is_object($var) ? get_object_vars($var) : (is_array($var) ? $var : str_split($var)));
}

/**
 * Get a length of a variable. For strings it's a string length, for arrays, objects and iterables it's the amount of
 * entries. For classes that implement `Countable`, returns the result of calling `count()` method.
 * @param string|object|iterable $var Variable to get length for.
 * @return int Variable length.
 * ```php
 * length('abc');              // 3
 * length([1, 2, 3]);          // 3
 * length((object) [1, 2, 3]); // 3
 * ```
 */
function length(string | object | iterable $var): int {
	return match (true) {
		is_string($var) => strlen($var),
		is_array($var) || $var instanceof Countable => sizeof($var),
		is_object($var) && !is_iterable($var) => sizeof((array) $var),
		default => sizeof([...$var])
	};
}

/**
 * Check if array or object property exists.
 * @param array|object $var Array or object to check for property existance.
 * @param int|string|array $property Property name.
 * @return bool `true` if property exists.
 * ```php
 * $a = ['a' => 1];
 * $o = (object) $a;
 * property_exists($a, 'a'); // true
 * property_exists($a, 'b'); // false
 * property_exists($o, 'a'); // true
 * property_exists($o, 'b'); // false
 * property_exists(['a' => ['b' => ['c' => 3]]], ['a', 'b', 'c']); // true
 * ```
 */
function property_exists(array | object $var, int | string | array $property): bool {
	$path = is_array($property) ? $property : [$property];
	$last = array_pop($path);
	$cur = &property_get($var, $path);
	return is_array($cur) ? array_key_exists($last, $cur) : !!\property_exists($cur, $last) || isset($cur->{$last});
}

/**
 * Get a property of an array or object.
 * @param array|object $var Array or object to get property value from.
 * @param int|string|array $property Property name.
 * @return mixed Property value or null if the property does not exist.
 * ```php
 * property_get(['a' => 1], 'a');                 // 1
 * property_get((object) ['a' => 1], 'a');        // 1
 * property_get(['a' => ['b' => 2]], ['a', 'b']); // 2
 * ```
 */
function &property_get(array | object &$var, int | string | array $property): mixed {
	$path = is_array($property) ? $property : [$property];
	$cur = &$var;
	foreach ($path as $name) {
		if (!is_struct($cur))
			return null;
		$cur = &property_take($cur, $name);
	}
	return $cur;
}

/**
 * List property names of an array or an object.
 * @param array|object $var Array or object to return properties from.
 * @return (int|string)[] Properties list.
 * ```php
 * property_list(['a', 'b', 'c']);     // [0, 1, 2]
 * property_list((object) ['a' => 1]); // ['a']
 * ```
 */
function property_list(array | object $var): array {
	return array_keys(is_array($var) ? $var : get_object_vars($var));
}

// TODO: Make it accept path
/**
 * Set a property value for an array or object.
 * @param array|object $var Array or object to set property for.
 * @param int|string $property Property name.
 * @param mixed $value Property value.
 * @return bool `true` if the operation is succeeded, `false` otherwise.
 * ```php
 * $a = [];
 * $o = (object) $a;
 * property_set($a, 'a', 1); // true
 * $a; // ['a' => 1]
 * property_set($o, 'a', 1); // true
 * $o; // {a: 1}
 * ```
 */
function property_set(array | object &$var, int | string $property, mixed $value): bool {
	if (is_array($var)) {
		$var[$property] = $value;
		return $var[$property] === $value;
	}
	try {
		$var->{$property} = $value;
		return $var->{$property} === $value;
	} catch (Error) {
		return false;
	}
}

// TODO: Make it accept path
/**
 * Unset an array or object property.
 * @param array|object $var Array or object to unset a property from.
 * @param int|string $property Property name.
 * @return bool `true` if the operation succeeded.
 * ```php
 * $a = ['a' => 1];
 * $o = (object) $a;
 * property_unset($a, 'a'); // true
 * property_unset($o, 'a'); // true
 * ```
 */
function property_unset(array | object &$var, int | string $property): bool {
	if (is_array($var))
		unset($var[$property]);
	else
		try {
			unset($var->{$property});
		} catch (Error) {
			return false;
		}
	return !property_exists($var, $property);
}

/**
 * Recursively transform a structure to an array.
 * @param array|object $var Array or object to transform.
 * @param int $depth Recursion depth. `PHP_INT_MAX` by default.
 * @return array The structure, transformed to an array.
 * ```php
 * to_array((object) ['a' => (object) ['b' => 2]]); // ['a' => ['b' => 2]]
 * ```
 */
function to_array(array | object $var, int $depth = PHP_INT_MAX): array {
	return to_struct('array', $var, $depth);
}

/**
 * Recursively transform a structure to an object.
 * @param array|object $var Array or object to transform.
 * @param int $depth Recursion depth. `PHP_INT_MAX` by default.
 * @return object The structure, transformed to an object.
 * ```php
 * to_object(['a' => ['b' => 2]]); // {a: {b: 2}}
 * ```
 */
function to_object(array | object $var, int $depth = PHP_INT_MAX): object {
	return to_struct('object', $var, $depth);
}

/**
 * Make a deep clone. Arrays and instances of the stdClass will be deeply cloned. For other objects, the `clone`
 * operator is called.
 * @param mixed $var Variable to clone.
 * @param int $depth Max clone depth.
 * @return mixed Cloned object.
 */
function var_clone(mixed $var, int $depth = PHP_INT_MAX): mixed {
	if ($depth <= 0)
		return $var;
	if (!is_array($var) && !($var instanceof stdClass))
		return is_object($var) ? clone $var : $var;
	$depth--;
	$result = is_array($var) ? [] : new stdClass;
	foreach ($var as $k => $v)
		property_set($result, $k, var_clone($v, $depth));
	return $result;
}

/**
 * Compare two variables and check if they are both deeply equal. If any of the arguments implements the `Equalable`
 * interface, the `equals()` method is called instead.
 * @param mixed $a The first variable to compare.
 * @param mixed $b The second variable to compare.
 * @param bool $strict If `false`, arrays and objects will be considered equal if they have the same properties.
 * @return bool `true` if both variables are equal. An array and an stdClass-object could be both equal if both
 *              variables have the same properties and values if `$strict` is false.
 * ```php
 * var_equals(['a' => 1], (object) ['a' => 1]); // true
 * ```
 */
function var_equals(mixed $a, mixed $b, bool $strict = false): bool {
	if ($a === $b)
		return true;
	$isAEqualable = $a instanceof Equalable;
	$isBEqualable = $b instanceof Equalable;
	if ($isAEqualable || $isBEqualable)
		return $isAEqualable && $a->equals($b) || $isBEqualable && $b->equals($a);
	if (!is_struct($a) || !is_struct($b))
		return $a === $b;
	if ($strict && is_array($a) !== is_array($b) || length($a) !== length($b))
		return false;
	foreach ($a as $k => $v)
		if (!var_equals($v, property_get($b, $k), $strict))
			return false;
	return true;
}

// PRIVATE FUNCTIONS

function is_struct(mixed $var): bool {
	return is_array($var) || is_object($var);
}

function key_first(object | iterable $var): null | int | string {
	foreach ($var as $k => $v)
		return $k;
	return null;
}

function path_normalize(string $path): string {
	return preg_replace('/[\\\\\/]+/', DIRECTORY_SEPARATOR, $path);
}

function &property_take(array | object &$var, int | string $property): mixed {
	if (is_array($var))
		return $var[$property];
	try {
		return $var->{$property};
	} catch (Error) {
		return null;
	}
}

function to_struct(string $type, array | object $var, int $depth): array | object {
	if ($depth < 1)
		$depth = 1;
	$result = $type === 'array' ? [] : new stdClass;
	$entries = is_array($var) ? $var : get_object_vars($var);
	$nextDepth = $depth - 1;
	foreach ($entries as $k => $v)
		property_set($result, $k, (is_array($v) || is_object($v)) && $depth > 1 ? to_struct($type, $v, $nextDepth) : $v);
	return $result;
}
