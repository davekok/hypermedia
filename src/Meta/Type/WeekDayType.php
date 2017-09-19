<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

class WeekDayType
{
	const type = "weekday";
	
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
		$days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sun', 'Mon','Tue','Wed','Thu','Fri','Sat',1,2,3,4,5,6,7);
		if(!in_array($value,$days)) return false;
		
		return true;
	}
}
