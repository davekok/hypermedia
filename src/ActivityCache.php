<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface for retrieving activities from cache.
 */
interface ActivityCache
{
	/**
	 * Get actions for activity
	 *
	 * @param $unit        the unit to retrieve the actions for
	 * @param $dimensions  the dimensions to retrieve the actions for
	 * @return the actions
	 */
	public function getActivityActions(string $unit, array $dimensions): array;
}
