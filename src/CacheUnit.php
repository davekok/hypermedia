<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * The unit interface as required by cache
 */
interface CacheUnit
{
	/**
	 * Get the name of the unit.
	 *
	 * @return the name of the unit
	 */
	public function getName(): string;

	/**
	 * Get dimensions
	 *
	 * @return array<string>  the dimensions
	 */
	public function getDimensions(): array;

	/**
	 * Get activities
	 *
	 * @return array<\stdClass>  the activities
	 */
	public function getActivities(): array;
}
