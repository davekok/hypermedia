<?php

namespace Sturdy\Activity\Meta\Type;

/**
 * Class BooleanType
 * @package Sturdy\Activity\Meta\Type
 */
final class BooleanType extends Type
{
	const type = "boolean";

	/**
	 * Constructor
	 *
	 * @param array|null $state  the objects state
	 */
	public function __construct(array $state = null)
	{

	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type;
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$value = trim(filter_var($value, FILTER_VALIDATE_BOOLEAN));
		if ($value === false)
			return false;
		if(is_string($value) && !in_array($value,["true","false"]))
			return false;
		return true;
	}
}
