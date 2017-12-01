<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Link type
 */
final class LinkType extends Type
{
	const type = "link";

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
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
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		return true;
	}
}
