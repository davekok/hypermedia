<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

class ColorType
{
	const type = "color";
	
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
		if(!preg_match("/^#?[0-9a-zA-Z]{6}$/",$value)) return false;
		
		return true;
	}
}
