<?php
/**
 * Created by PhpStorm.
 * User: rfakkel
 * Date: 9/18/2017
 * Time: 5:12 PM
 */

namespace Sturdy\Activity\Meta\Type;


class YearType
{
	const type = "year";
	private $year;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$year] = $state;
			if (strlen($year)) $this->time = new \DateTime("01-01-".$year);
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
	public function getYear()
	{
		return $this->year;
	}
	
	/**
	 * @param mixed $year
	 */
	public function setYear($year) : self
	{
		$this->year = $year;
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
		if ($this->year) {
			$meta->year = $this->time->format("d");
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
