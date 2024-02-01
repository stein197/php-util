<?php
namespace Stein197\Util;

use Error;
use ReflectionFunction;
use stdClass;
use Stein197\Equalable;
use function array_key_exists;
use function is_array;
use function is_callable;
use function is_int;
use function is_iterable;
use function is_object;
use function is_resource;
use function is_string;
use function join;
use function get_object_vars;
use function get_resource_id;
use function get_resource_type;
use function sizeof;
use function spl_object_id;
use function str_repeat;
use function str_split;
use function strlen;
use function trim;
use function var_export;
use const PHP_INT_MAX;

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
 * Compare two variables and check if they are both deeply equal. If any of the arguments implements the `Equalable`
 * interface, the `equals()` method is called instead.
 * @param mixed $a The first variable to compare.
 * @param mixed $b The second variable to compare.
 * @param bool $strict If `false`, arrays and objects will be considered equal if they have the same properties.
 * @return bool `true` if both variables are equal. An array and an stdClass-object could be both equal if both
 *              variables have the same properties and values if `$strict` is false.
 * ```php
 * equal(['a' => 1], (object) ['a' => 1]); // true
 * ```
 */
function equal(mixed $a, mixed $b, bool $strict = false): bool {
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
		if (!equal($v, property_get($b, $k), $strict))
			return false;
	return true;
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
 * entries.
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
		is_array($var) => sizeof($var),
		$var instanceof stdClass => sizeof((array) $var),
		default => sizeof([...$var])
	};
}

/**
 * Check if array or object property exists.
 * @param array|object $var Array or object to check for property existance.
 * @param int|string $property Property name.
 * @return bool `true` if property exists.
 * ```php
 * $a = ['a' => 1];
 * $o = (object) $a;
 * property_exists($a, 'a'); // true
 * property_exists($a, 'b'); // false
 * property_exists($o, 'a'); // true
 * property_exists($o, 'b'); // false
 * ```
 */
function property_exists(array | object $var, int | string $property): bool {
	return is_array($var) ? array_key_exists($property, $var) : !!\property_exists($var, $property) || isset($var->{$property});
}

/**
 * Get a property of an array or object.
 * @param array|object $var Array or object to get property value from.
 * @param int|string $property Property name.
 * @return mixed Property value or null if the property does not exist.
 * ```php
 * property_get(['a' => 1], 'a');          // 1
 * property_get((object) ['a' => 1], 'a'); // 1
 * ```
 */
function property_get(array | object &$var, int | string $property): mixed {
	if (is_array($var))
		if (property_exists($var, $property))
			return $var[$property];
		else
			return null;
	try {
		return @$var->{$property};
	} catch (Error) {
		return null;
	}
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
	return to_array_or_object('array', $var, $depth);
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
	return to_array_or_object('object', $var, $depth);
}

// PRIVATE FUNCTIONS

function is_struct(mixed $var): bool {
	return is_array($var) || $var instanceof stdClass;
}

function key_first(object | iterable $var): null | int | string {
	foreach ($var as $k => $v)
		return $k;
	return null;
}

function to_array_or_object(string $type, array | object $var, int $depth): array | object {
	if ($depth < 1)
		$depth = 1;
	$result = $type === 'array' ? [] : new stdClass;
	$entries = is_array($var) ? $var : get_object_vars($var);
	$nextDepth = $depth - 1;
	foreach ($entries as $k => $v)
		property_set($result, $k, (is_array($v) || is_object($v)) && $depth > 1 ? to_array_or_object($type, $v, $nextDepth) : $v);
	return $result;
}
