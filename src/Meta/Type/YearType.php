<?php

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class YearType extends Type
{
	const type = "year";

	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
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
	 * @param  &$value the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$year = filter_var(trim($value), FILTER_VALIDATE_INT);
		if ($year === false || $year === 0) { // there is no year 0
			return false;
		}
		$value = $year;
		return true;
	}
}
