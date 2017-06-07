<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * The source unit interface as required by cache
 */
interface CacheSourceUnit
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
	 * Get wild card dimensions
	 *
	 * @return array<string>  the wild card dimensions
	 */
	public function getWildCardDimensions(): array;

	/**
	 * Get activities
	 *
	 * @return array<\stdClass>  the activities
	 */
	public function getActivities(): array;
}
