<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Type
 */
abstract class Type
{
	public static function createType(string $state): Type
	{
		$state = explode(",", $state);
		$type = array_shift($state);
		$class = __NAMESPACE__."\\".ucfirst($type)."Type";
		return new $class($state);
	}

	/**
	 * Get state
	 *
	 * @return string
	 */
	public abstract function getDescriptor(): string;

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public abstract function meta(stdClass $meta): void;

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public abstract function filter(&$value): bool;
}
