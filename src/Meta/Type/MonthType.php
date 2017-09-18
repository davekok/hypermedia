<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use DateTime,stdClass;

class MonthType
{
	const type = "month";
	private $month;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$month] = $state;
			if (strlen($month)) $this->time = new DateTime("d ".$month." Y");
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
	public function getMonth()
	{
		return $this->month;
	}
	
	/**
	 * @param mixed $month
	 */
	public function setMonth($month) : self
	{
		$this->month = $month;
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
		if ($this->month) {
			$meta->month = $this->time->format("F");
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
		$monthNames = [];
		
		for($i = 1; $i <= 12; $i++)
		{
			$date = new DateTime('01-'. str_pad($i,2,"0",STR_PAD_LEFT) .'-Y');
			$monthNames[] = $date->format('F');
			$monthNames[] = $date->format('M');
		}
		
		if (is_numeric($value) && !preg_match("^(0[1-9]|[12]\d|3[01])$", $value)) return false;
		if (!in_array(ucfirst($value),$monthNames)) return false;
		
		return true;
	}
}
