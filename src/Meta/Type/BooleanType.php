<?php

namespace Sturdy\Activity\Meta\Type;

use stdClass;

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
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
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
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
	{
		$meta->type = self::type;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value bool the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_string($value)) $value = trim($value);
		$boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($boolean === null) return false;
		$value = $boolean;
		return true;
	}
}
