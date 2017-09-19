<?php

namespace Sturdy\Activity\Meta\Type;

use stdClass;

class DayType
{
	const type = "day";
	private $day;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$day] = $state;
			if (strlen($day)) $this->time = new \DateTime($day."-01-Y");
		}
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
	 * @return mixed
	 */
	public function getDay()
	{
		return $this->day;
	}
	
	/**
	 * @param mixed $day
	 */
	public function setDay($day) : self
	{
		$this->day = $day;
		return $this;
	}
	
	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
		if ($this->day) {
			$meta->day = $this->time->format("d");
		}
	}
	
	/**
	 * Filter value
	 *
	 * @param  &$value the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (!preg_match("^(0?[1-9]|[12]\d|3[01])$", $value)) return false;
		
		return true;
	}
}
