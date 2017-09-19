<?php

namespace Sturdy\Activity\Meta\Type;

use DateTime,stdClass;

final class DateTimeType
{
	const type = "datetime";
	private $datetime;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$datetime] = $state;
			if (strlen($datetime)) $this->datetime = new \DateTime($datetime);
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
		return $this->datetime;
	}
	
	/**
	 * @param string $datetime
	 * @return self
	 */
	public function setDate(string $datetime): self
	{
		$this->datetime = new DateTime($datetime);
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
		if ($this->datetime) {
			$meta->date = $this->datetime->format(DATE_ATOM);
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
		if (!preg_match("^(?:[1-9]\d{3}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1\d|2[0-8])|(?:0[13-9]|1[0-2])-(?:29|30)|(?:0[13578]|1[02])-31)|(?:[1-9]\d(?:0[48]|[2468][048]|[13579][26])|(?:[2468][048]|[13579][26])00)-02-29)T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:Z|[+-][01]\d:[0-5]\d)$", $value)) return false;
		
		return true;
	}
}
