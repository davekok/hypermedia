<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class WeekType extends Type
{
	const type = "week";

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
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value int the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_string($value)) $value = trim($value);
		$week = filter_var($value, FILTER_VALIDATE_INT);
		if ($week === false || $week < 1 || $week > 53) {
			return false;
		}
		$value = $week;
		return true;
	}
}
