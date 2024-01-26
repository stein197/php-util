<?php
namespace Stein197\Util;

use Error;
use stdClass;
use function array_key_exists;
use function is_array;
use function is_iterable;
use function is_object;
use function get_object_vars;
use function str_split;
use const PHP_INT_MAX;

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
 * Check if array or object property exists.
 * @param array|object $var Array or object to check for property existance.
 * @param string $property Property name.
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
function property_exists(array | object $var, string $property): bool {
	return is_array($var) ? array_key_exists($property, $var) : !!\property_exists($var, $property) || isset($var->{$property});
}

/**
 * Get a property of an array or object.
 * @param array|object $var Array or object to get property value from.
 * @param string $property Property name.
 * @return mixed Property value or null if the property does not exist.
 * ```php
 * property_get(['a' => 1], 'a');          // 1
 * property_get((object) ['a' => 1], 'a'); // 1
 * ```
 */
function property_get(array | object &$var, string $property): mixed {
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
 * @param string $property Property name.
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
function property_set(array | object &$var, string $property, mixed $value): bool {
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
 * @param string $property Property name.
 * @return bool `true` if the operation succeeded.
 * ```php
 * $a = ['a' => 1];
 * $o = (object) $a;
 * property_unset($a, 'a'); // true
 * property_unset($o, 'a'); // true
 * ```
 */
function property_unset(array | object &$var, string $property): bool {
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
