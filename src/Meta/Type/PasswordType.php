<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

class PasswordType
{
	const type = "password";
	private $minimumLength;
	private $maximumLength;
	
	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$min, $max] = $state;
			if (strlen($min) > 0) $this->minimumLength = (int)$min;
			if (strlen($max) > 0) $this->maximumLength = (int)$max;
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
		// Min length of <minimumLength>, max length of <maximumlength>, only allow $@$!%*?&, a-z and A-Z
		if(!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[$@$!%*?&])[A-Za-z\d$@$!%*?&]{". $this->minimumLength .",". $this->maximumLength ."}/$",$value)) return false;
		
		return true;
	}
}
