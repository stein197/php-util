<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use function Stein197\Util\property_unset;
use function Stein197\Util\property_exists;

$var = new class () {
	private array $data;
	public function __get(string $name): mixed {
		return @$this->data[$name];
	}
	public function __set(string $name, mixed $value): void {
		$this->data[$name] = $value;
	}
	public function __unset(string $name): void {}
};
$var->a = 1;
var_dump(property_exists($var, 'a'));
var_dump(property_unset($var, 'a'));
var_dump($var->a);