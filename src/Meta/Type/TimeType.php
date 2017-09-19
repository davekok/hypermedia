<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use DateTime,stdClass;

class TimeType
{
	const type = "time";
	private $time;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$time] = $state;
			if (strlen($time)) $this->time = new \DateTime($time);
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
	 * @return \DateTime
	 */
	public function getDate(): DateTime
	{
		return $this->time;
	}
	
	/**
	 * @param string $time
	 * @return self
	 */
	public function setDate(string $time): self
	{
		$this->time = new DateTime($time);
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
		if ($this->time) {
			$meta->time = $this->time->format("\TH:i:s");
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
		if (!preg_match("^T?(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:|[+-][01]\d:[0-5]\d)$", $value)) return false;
		
		return true;
	}
}
