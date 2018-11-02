<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class WeekDayType extends Type
{
	const type = "weekday";
	const weekdays = [
		'sunday'    => 1,
		'monday'    => 2,
		'tuesday'   => 3,
		'wednesday' => 4,
		'thursday'  => 5,
		'friday'    => 6,
		'saturday'  => 7,
		'sun'       => 1,
		'mon'       => 2,
		'tue'       => 3,
		'wed'       => 4,
		'thu'       => 5,
		'fri'       => 6,
		'sat'       => 7,
		1           => 1,
		2           => 2,
		3           => 3,
		4           => 4,
		5           => 5,
		6           => 6,
		7           => 7,
	];

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
	 * @param  &$value string|int the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_int($value)) {
			$weekday = self::weekdays[$value] ?? false;
		} elseif (is_string($value)) {
			$weekday = self::weekdays[strtolower(trim($value))] ?? false;
		}
		if ($weekday === false) return false;
		$value = $weekday;
		return true;
	}
}
