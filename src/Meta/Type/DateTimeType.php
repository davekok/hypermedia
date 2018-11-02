<?php

namespace Sturdy\Activity\Meta\Type;

use DateTime;
use stdClass;

final class DateTimeType extends Type
{
	const type = "datetime";
	private $minimumRange;
	private $maximumRange;

	/**
	 * Constructor
	 *
	 * @param string|null $state the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			[$min, $max] = explode(",", $state);
			$this->minimumRange = strlen($min) ? $min : null;
			$this->maximumRange = strlen($max) ? $min : null;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":".$this->minimumRange.",".$this->maximumRange;
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
	 * @return string
	 */
	public function getMinimumRange()
	{
		return $this->minimumRange;
	}

	/**
	 * @param string $minimumRange
	 */
	public function setMinimumRange(string $minimumRange)
	{
		$this->minimumRange = $minimumRange;
	}

	/**
	 * @return string
	 */
	public function getMaximumRange()
	{
		return $this->maximumRange;
	}

	/**
	 * @param string $maximumRange
	 */
	public function setMaximumRange(string $maximumRange)
	{
		$this->maximumRange = $maximumRange;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (!is_string($value)) return false;
		if (!preg_match("/^(?:[1-9]\d{3}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1\d|2[0-8])|(?:0[13-9]|1[0-2])-(?:29|30)|(?:0[13578]|1[02])-31)|(?:[1-9]\d(?:0[48]|[2468][048]|[13579][26])|(?:[2468][048]|[13579][26])00)-02-29)T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:Z|[+-][01]\d:[0-5]\d)$/", $value = trim($value))) {
			return false;
		}
		return true;
	}
}
