<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface for retrieving activities from cache.
 */
interface ActivityCache
{
	/**
	 * Get a cached activity
	 *
	 * @param $unit        the unit to retrieve the activity for
	 * @param $dimensions  the dimensions to retrieve the activity for
	 * @return the activity
	 */
	public function getActivity(string $unit, array $dimensions): array;
}
