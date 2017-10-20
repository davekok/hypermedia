<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use DateTime;
use stdClass;

final class MonthType extends Type
{
	const type = "month";
	const monthNames = [
		"january"   =>  1, "jan" =>  1,  1 =>  1,
		"february"  =>  2, "feb" =>  2,  2 =>  2,
		"march"     =>  3, "mar" =>  3,  3 =>  3,
		"april"     =>  4, "apr" =>  4,  4 =>  4,
		"may"       =>  5, "may" =>  5,  5 =>  5,
		"june"      =>  6, "jun" =>  6,  6 =>  6,
		"july"      =>  7, "jul" =>  7,  7 =>  7,
		"august"    =>  8, "aug" =>  8,  8 =>  8,
		"september" =>  9, "sep" =>  9,  9 =>  9,
		"october"   => 10, "oct" => 10, 10 => 10,
		"november"  => 11, "nov" => 11, 11 => 11,
		"december"  => 12, "dec" => 12, 12 => 12,
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
	 */
	public function meta(stdClass $meta): void
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
			$month = self::monthNames[$value] ?? false;
		} elseif (is_string($value)) {
			$month = self::monthNames[strtolower(trim($value))] ?? false;
		}
		if ($month === false) return false;
		$value = $month;
		return true;
	}
}
