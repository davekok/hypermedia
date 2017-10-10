<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use DateTime,stdClass;

final class MonthType extends Type
{
	const type = "month";

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
		$monthNames = [];

		for($i = 1; $i <= 12; $i++)
		{
			$date = new DateTime('01-'. str_pad($i,2,"0",STR_PAD_LEFT) .'-Y');
			$monthNames[] = $date->format('F');
			$monthNames[] = $date->format('M');
		}

		if (is_numeric($value) && !preg_match("/^(0[1-9]|[12]\d|3[01])$/", $value)) return false;
		if (!in_array(ucfirst(strtolower($value)),$monthNames)) return false;

		return true;
	}
}
