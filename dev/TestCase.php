<?php declare(strict_types=1);


namespace BFITech\ZapCoreDev;


use PHPUnit\Framework\TestCase as PHPUnitTestCase;


/**
 * PHPUnit\\FrameWork\\TestCase wrapper for lazy typer.
 *
 * This class is purely intended for testing, in an attempt to reduce
 * typing long assertion method names over and over again. Only the most
 * commonly-used PHPUnit assertion methods are shortened. Subclass this
 * if you want more.
 *
 * @note **DO NOT** use this for anything other than testing, like,
 *     seriously, bruv!
 * @see https://phpunit.readthedocs.io/en/7.0/assertions.html
 *
 * ### example:
 *
 * @code
 * <?php
 *
 * # normal typing
 * self::assertEquals()($a, $b);
 * self::assertEquals()($a, $c);
 * self::assertNotEquals()($a, $d);
 * self::assertSame()($a, $p);
 * self::assertSame()($d, $q);
 *
 * # less typing
 * self::eq()($a, $b);
 * self::eq()($a, $c);
 * self::ne()($a, $d);
 * self::sm()($a, $p);
 * self::sm()($d, $p);
 *
 * # even-less typing
 * $eq = self::eq();
 * $sm = self::sm();
 * $eq($a, $b);
 * $eq($a, $c);
 * self::ne()($a, $d);
 * $sm($a, $p);
 * $sm($b, $q);
 *
 * # even lazier
 * extract(self::vars())
 * $eq($a, $b);
 * $eq($a, $c);
 * $ne($a, $d);
 * $sm($a, $p);
 * $sm($b, $q);
 *
 * # no, don't shorten it to single-char variable!
 * @endcode
 *
 * @if TRUE
 * @SuppressWarnings(PHPMD.ShortMethodName)
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @endif
 */
abstract class TestCase extends PHPUnitTestCase {

	/** Shorthand for assertEquals. */
	public static function eq() {
		return function($a, $b) {
			self::assertEquals($a, $b);
		};
	}

	/** Shorthand for assertNotEquals. */
	public static function ne() {
		return function($a, $b) {
			self::assertNotEquals($a, $b);
		};
	}

	/** Shorthand for assertTrue. */
	public static function tr() {
		return function($a) {
			self::assertTrue($a);
		};
	}

	/** Shorthand for assertFalse. */
	public static function fl() {
		return function($a) {
			self::assertFalse($a);
		};
	}

	/** Shorthand for assertSame. */
	public static function sm() {
		return function($a, $b) {
			self::assertSame($a, $b);
		};
	}

	/** Shorthand for assertNotSame. */
	public static function ns() {
		return function($a, $b) {
			self::assertNotSame($a, $b);
		};
	}

	/** Shorthand for assertNull. */
	public static function nil() {
		return function($a) {
			self::assertNull($a);
		};
	}

	/**
	 * Set assertion function variables in bulk.
	 *
	 * @note Never use any method name in this class as a variable name
	 *     in your tests.
	 */
	public static function vars() {
		return [
			'eq' => self::eq(),
			'ne' => self::ne(),
			'tr' => self::tr(),
			'fl' => self::fl(),
			'sm' => self::sm(),
			'ns' => self::ns(),
			'nil' => self::nil(),
		];
	}

	/**
	 * Set test data directory.
	 *
	 * Use the directory to put files generated by tests for easier
	 * cleanup and permission setup. In zap*, the directory usually
	 * resolves to `./tests/testdata`.
	 *
	 * @param string $basefile Absolute path of reference file.
	 * @param string $dirname Test directory name. This will be appended
	 *     to directory name of $basefile. Name with uncommon characters
	 *     in it is not allowed.
	 * @return string Absolute path to test data directory.
	 */
	final public static function tdir(
		string $basefile, string $dirname='testdata'
	) {
		if (!is_file($basefile)) {
			throw new \Exception(
				"Basefile for test directory '$basefile' invalid.");
		}
		if (!$dirname || preg_match('![^a-z0-9\.,;\-]!i', $dirname)) {
			throw new \Exception(
				"Testdata directory basename invalid.");
		}
		$dir = dirname($basefile) . '/' . $dirname;
		if (!is_dir($dir) && false === @mkdir($dir, 0755)) {
			throw new \Exception(
				"Cannot create test directory '$dir'.");
		}
		return $dir;
	}

}
