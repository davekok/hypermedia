<?php

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class DayType extends Type
{
	const type = "day";

	/**
	 * Constructor
	 *
	 * @param string|null $state the objects state
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
	 * @param  &$value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_string($value)) {
			$day = trim($value);
			if (!preg_match("/^(0?[1-9]|[12]\d|3[01])$/", $day)) {
				return false;
			}
			$day = (int)$day;
		} else {
			$day = filter_var($value, FILTER_VALIDATE_INT);
			if ($day === false || $day < 1 || $day > 31) {
				return false;
			}
		}
		$value = $day;
		return true;
	}
}
