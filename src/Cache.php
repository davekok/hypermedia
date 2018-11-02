<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Interface for retrieving activities from cache.
 */
interface Cache
{
	/**
	 * Get a cached activity item
	 *
	 * @param string $unit   the unit to retrieve the activity for
	 * @param string $class  the class of the activity
	 * @param array  $tags   the tags to retrieve the activity for
	 * @return the activity
	 */
	public function getActivity(string $unit, string $class, array $tags): ?Meta\CacheItem_Activity;

	/**
	 * Get a cached resource item
	 *
	 * @param string $unit   the unit to retrieve the resource meta data for
	 * @param string $class  the name if the resource
	 * @param array  $tags   the tags to retrieve the resource meta data for
	 * @return the resource meta data
	 */
	public function getResource(string $unit, string $class, array $tags): ?Meta\CacheItem_Resource;

	/**
	 * Get a cached root resource item
	 *
	 * @param string $unit   the unit to retrieve the resource meta data for
	 * @param string $class  the name if the resource
	 * @param array  $tags   the tags to retrieve the resource meta data for
	 * @return the resource meta data
	 */
	public function getRootResource(string $unit, array $tags): ?Meta\CacheItem_RootResource;
}
