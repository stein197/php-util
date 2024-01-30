<?php
namespace Stein197\Util;

/**
 * Interface, that's utilized by the `dump()` function to create custom object dumps.
 */
interface Dumpable {

	/**
	 * Dump the object.
	 * @param string $indent Indent to use. Empty string means no pretty-printing.
	 * @param int $depth Indentation depth.
	 * @return string Dump.
	 */
	public function dump(string $indent, int $depth): string;
}
