<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

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
	public function getDescriptor(): string
	{
		return $this->__toString().",".$this->getState();
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void;

	/**
	 * Get state
	 *
	 * @return string
	 */
	public function getState(): string;

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool;

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function __toString(): string;
}
