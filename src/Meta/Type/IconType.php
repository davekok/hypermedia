<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Icon type
 */
final class IconType extends Type
{
	const type = "icon";

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
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
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
		if ($value === null) return true;
		if (!is_scalar($value)) return false;
		$string = filter_var((string)$value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
		if ($string === false || !is_string($value)) {
			return false;
		}
		$value = $string;
		return true;
	}
}
