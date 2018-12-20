<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Mark a class as a Type class
 */
interface Type
{
	/**
	 * Convert to string value
	 *
	 * @return string   string value
	 */
	public function __toString(): string;
}
