# PHP Utilities
## Installation
```
composer require stein197/util
```

## API
| Function | Description |
|----------|-------------|
| `iterate(string \| object \| iterable $var): iterable` | Iterate through iterables - strings, arrays, objects and iterables |
| `property_exists(array \| object $var, string $property): bool` | Check if array or object property exists |
| `&property_get(array \| object &$var, string $property): mixed` | Get a property of an array or object |
| `property_set(array \| object &$var, string $property, mixed $value): bool` | Set a property value for an array or object |
| `property_unset(array \| object &$var, string $property): bool` | Unset an array or object property |
| `to_array(array \| object $var, int $depth = PHP_INT_MAX): array` | Recursively transform a structure to an array |
| `to_object(array \| object $var, int $depth = PHP_INT_MAX): object` | Recursively transform a structure to an object |

## Composer scripts
- `test` Run unit tests
