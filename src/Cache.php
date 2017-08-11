<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Exception;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class dealing with caching.
 */
final class Cache implements ActivityCache
{
	private $cachePool;

	/**
	 * Constructor
	 *
	 * @param $cachePool  the cache pool
	 */
	public function __construct(CacheItemPoolInterface $cachePool)
	{
		$this->cachePool = $cachePool;
	}

	/**
	 * Whether a unit is already cached.
	 *
	 * @param $unit  the unit name
	 * @return true if cached, false otherwise
	 */
	public function hasUnit(string $unit): bool
	{
		return $this->cachePool->getItem($this->dimensionsKey($unit))->isHit();
	}

	/**
	 * Update source unit in cache.
	 *
	 * @param $unit  the source unit to cache
	 */
	public function updateUnit(CacheSourceUnit $unit): void
	{
		if ($unit->isCompiled() === false) {
			$unit->compile();
		}

		$name = $unit->getName();

		// save the order in which the dimensions are stored
		$order = $unit->getDimensions();
		$wildcards = $unit->getWildCardDimensions();
		$item = $this->cachePool->getItem($this->dimensionsKey($name));
		$item->set(serialize([$order, $wildcards]));
		$this->cachePool->saveDeferred($item);

		// save the activities for each dimension
		foreach ($unit->getActivities() as $activity) {
			$dimensions = $this->reorder($activity->dimensions, $order);
			unset($activity->dimensions);
			$item = $this->cachePool->getItem($this->activityKey($name, $dimensions));
			$item->set(serialize($activity));
			$activity->dimensions = $dimensions;
			$this->cachePool->saveDeferred($item);
		}

		// commit cache
		$this->cachePool->commit();
	}

	/**
	 * Get a cached activity
	 *
	 * @param $unit        the unit to retrieve the activity for
	 * @param $dimensions  the dimensions to retrieve the activity for
	 * @return the activity
	 */
	public function getActivity(string $unit, array $dimensions): ?stdClass
	{
		$item = $this->cachePool->getItem($this->dimensionsKey($unit));
		if (!$item->isHit()) {
			return null;
		}
		[$order, $wildcards] = unserialize($item->get());

		$dimensions = $this->reorder($dimensions, $order);

		$item = $this->cachePool->getItem($this->activityKey($unit, $dimensions));

		if (!$item->isHit()) { // try wildcards
			// filter out any dimension that are already wildcards
			foreach ($wildcards as $ix => &$wildcard) {
				if ($dimensions[$wildcard] === true || $dimensions[$wildcard] === null) {
					unset($wildcards[$ix]);
				}
			}

			// iterate all wildcard permutations
			foreach ($this->wildcardPermutations($wildcards) as $wcs) {
				$dup = $dimensions;
				foreach ($wcs as $wc) {
					$dup[$wc] = true; // set dimension to wildcard
				}

				// check if an activity exists
				$item = $this->cachePool->getItem($this->activityKey($unit, $dup));
				if ($item->isHit()) {
					break;
				}
			}
		}

		if (!$item->isHit()) {
			return null;
		}

		$activity = unserialize($item->get());
		if (!$activity) {
			return null;
		}

		return $activity;
	}

	/**
	 * Return all permutations of wildcards without duplicates.
	 *
	 * @param  array  $wildcards  the wildcards to return the permutations for
	 * @return Generator  a generator to iterate the permutations
	 */
	private function wildcardPermutations(array $array): \Generator
	{
		$l = count($array);
		switch ($l) {
			case 0:
				break;
			case 1:
				yield $array;
				break;
			default:
				foreach ($array as $value) {
					yield [$value];
				}
				for ($i = 0; $i < $l; ++$i) {
					$value = array_shift($array);
					foreach ($this->wildcardPermutations($array) as $sub) {
						array_unshift($sub, $value);
						yield $sub;
					}
				}
		}
	}

	/**
	 * Construct dimensions key to use for caching.
	 *
	 * @param $unit  the unit name
	 * @return the cache key
	 */
	public function dimensionsKey(string $unit): string
	{
		return $this->unitKey($unit) . ".dimensions";
	}

	/**
	 * Reorder array, any missing keys will be added and have a value of null.
	 *
	 * @param $array  the original array
	 * @param $order  the order of the new array
	 * @return reordered array
	 */
	public function reorder(array $array, array $order): array
	{
		$reordered = [];
		foreach ($order as $key) {
			$reordered[$key] = $array[$key] ?? null;
		}
		return $reordered;
	}

	/**
	 * Construct activity key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions
	 * @return the cache key
	 */
	public function activityKey(string $unit, array $dimensions): string
	{
		return $this->unitKey($unit) . "|" . hash("sha256", serialize($dimensions));
	}

	/**
	 * Construct a unit key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @return the cache key
	 */
	public function unitKey(string $unit): string
	{
		return "sturdy-activity|" . $unit;
	}
}
